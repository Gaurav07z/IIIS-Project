<?php
require_once "../config/session.php";
require_once "../config/database.php";

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-claims.php");
    exit;
}

// Validate CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: my-claims.php?cancel=error");
    exit;
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);

try {
    if (!$claimId) {
        throw new Exception("Invalid claim ID.");
    }

    $conn->beginTransaction();

    // Find and lock receiver's claim and food listing
    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.status,
            f.id AS listing_id,
            f.food_name,
            f.donor_id,
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
        throw new Exception("This claim cannot be cancelled.");
    }

    // Cancel claim
    $stmt = $conn->prepare("
        UPDATE claims
        SET status = 'cancelled', pickup_time = NULL
        WHERE id = ? AND receiver_id = ?
    ");

    $stmt->execute([$claimId, $receiverId]);

    // Make food available again if it has not expired
    $foodStatus = strtotime($claim['expiry_time']) > time()
        ? 'available'
        : 'expired';

    $stmt = $conn->prepare("
        UPDATE food_listings
        SET status = ?
        WHERE id = ? AND status = 'claimed'
    ");

    $stmt->execute([$foodStatus, $claim['listing_id']]);

    // Notify donor
    $message = $receiver['name']
        . " cancelled the claim for "
        . $claim['food_name'] . ".";

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim_cancelled')
    ");

    $stmt->execute([$claim['donor_id'], $message]);

    $conn->commit();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: my-claims.php?cancel=success");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("FoodLoop claim cancellation error: " . $e->getMessage());

    header("Location: my-claims.php?cancel=error");
    exit;
}
