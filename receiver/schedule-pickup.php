<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check receiver login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

$receiverId = (int) $_SESSION['user_id'];

// Check receiver verification
$stmt = $conn->prepare("
    SELECT name, verification_status
    FROM users
    WHERE id = ? AND role = 'receiver'
    LIMIT 1
");
$stmt->execute([$receiverId]);
$receiver = $stmt->fetch();

if (!$receiver || $receiver['verification_status'] !== 'verified') {
    unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['name']);
    header("Location: ../auth/login.php");
    exit;
}

$_SESSION['name'] = $receiver['name'];

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-claims.php");
    exit;
}

// Validate CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: my-claims.php?pickup=error");
    exit;
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$pickup = trim($_POST['pickup_time'] ?? '');

try {

    if (!$claimId || $pickup === '') {
        throw new Exception("Invalid input.");
    }

    // Strictly validate date and time
    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $pickup);
    $dateErrors = DateTime::getLastErrors();

    if (
        !$date ||
        ($dateErrors !== false &&
            ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
        $date->format('Y-m-d\TH:i') !== $pickup
    ) {
        throw new Exception("Invalid date.");
    }

    if ($date <= new DateTime()) {
        throw new Exception("Pickup time must be in the future.");
    }

    $conn->beginTransaction();

    // Lock claim and listing while updating
    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.status,
            f.food_name,
            f.donor_id,
            f.status AS food_status,
            f.expiry_time
        FROM claims c
        JOIN food_listings f ON c.listing_id = f.id
        WHERE c.id = ? AND c.receiver_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$claimId, $receiverId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        throw new Exception("Claim not found.");
    }

    if (!in_array($claim['status'], ['claimed', 'pickup_scheduled'], true)) {
        throw new Exception("This claim cannot be scheduled.");
    }

    if ($claim['food_status'] !== 'claimed') {
        throw new Exception("Food is not available for pickup.");
    }

    if (strtotime($claim['expiry_time']) <= time()) {
        throw new Exception("Food has expired.");
    }

    // Update pickup schedule
    $stmt = $conn->prepare("
        UPDATE claims
        SET pickup_time = ?, status = 'pickup_scheduled'
        WHERE id = ? AND receiver_id = ?
    ");
    $stmt->execute([
        $date->format('Y-m-d H:i:s'),
        $claimId,
        $receiverId
    ]);

    // Notify donor
    $message = $receiver['name'] . " scheduled pickup for "
        . $claim['food_name'] . " at "
        . $date->format('d M Y, h:i A');
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'pickup')
    ");
    $stmt->execute([
        $claim['donor_id'],
        $message
    ]);
    $conn->commit();
    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: my-claims.php?pickup=success");
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("FoodLoop pickup scheduling error: " . $e->getMessage());
    header("Location: my-claims.php?pickup=error");
    exit;
}
