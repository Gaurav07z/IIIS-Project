<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check receiver login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

$receiverId = (int) $_SESSION['user_id'];

// Verify receiver account
$stmt = $conn->prepare("
    SELECT name, verification_status
    FROM users
    WHERE id = ? AND role = 'receiver'
    LIMIT 1
");
$stmt->execute([$receiverId]);
$receiver = $stmt->fetch();

if (!$receiver || $receiver['verification_status'] !== 'verified') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php?error=verification");
    exit;
}

// Allow POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// CSRF protection
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit("Invalid request. Please try again.");
}

// Validate listing ID
$listingId = filter_var(
    $_POST['listing_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$listingId || $listingId < 1) {
    header("Location: dashboard.php?claim=invalid");
    exit;
}

try {
    $conn->beginTransaction();

    // Lock food listing
    $stmt = $conn->prepare("
        SELECT *
        FROM food_listings
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$listingId]);
    $food = $stmt->fetch();

    // Check availability and expiry
    if (
        !$food ||
        $food['status'] !== 'available' ||
        strtotime($food['expiry_time']) <= time()
    ) {
        $conn->rollBack();
        header("Location: dashboard.php?claim=unavailable");
        exit;
    }

    // Prevent duplicate claims
    $stmt = $conn->prepare("
        SELECT id
        FROM claims
        WHERE listing_id = ? AND receiver_id = ?
    ");
    $stmt->execute([$listingId, $receiverId]);

    if ($stmt->fetch()) {
        $conn->rollBack();
        header("Location: dashboard.php?claim=duplicate");
        exit;
    }

    // Create claim
    $stmt = $conn->prepare("
        INSERT INTO claims
            (listing_id, receiver_id, quantity, status)
        VALUES (?, ?, ?, 'claimed')
    ");

    $stmt->execute([
        $listingId,
        $receiverId,
        $food['quantity']
    ]);

    // Update food listing
    $stmt = $conn->prepare("
        UPDATE food_listings
        SET status = 'claimed'
        WHERE id = ? AND status = 'available'
    ");
    $stmt->execute([$listingId]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Food listing is no longer available.");
    }

    // Notify donor
    $message = $receiver['name'] .
        " has claimed your food donation: " .
        $food['food_name'];

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim')
    ");

    $stmt->execute([
        $food['donor_id'],
        $message
    ]);

    $conn->commit();

    // Refresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: dashboard.php?claim=success");
    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("FoodLoop claim error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to process claim. Please try again.");
}
