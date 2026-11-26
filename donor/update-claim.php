<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check donor login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

$donorId = $_SESSION['user_id'];

// Check donor verification
$stmt = $conn->prepare("
    SELECT verification_status
    FROM users
    WHERE id = ? AND role = 'donor'
    LIMIT 1
");
$stmt->execute([$donorId]);
$user = $stmt->fetch();

if (!$user || $user['verification_status'] !== 'verified') {
    header("Location: ../auth/login.php");
    exit;
}

// POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: claims.php");
    exit;
}

// CSRF protection
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    die("Invalid request.");
}

// Validate input
$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

$allowed = ['reject', 'collect', 'complete'];

if (!$claimId || !in_array($action, $allowed, true)) {
    header("Location: claims.php?error=1");
    exit;
}

try {
    $conn->beginTransaction();

    // Get claim belonging to this donor and lock its row
    $stmt = $conn->prepare("
        SELECT c.*, f.donor_id, f.food_name,
               f.id AS food_id, f.expiry_time, f.status AS food_status
        FROM claims c
        JOIN food_listings f ON c.listing_id = f.id
        WHERE c.id = ? AND f.donor_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$claimId, $donorId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        throw new Exception("Claim not found.");
    }

    $oldStatus = $claim['status'];
    $newStatus = '';
    $message = '';

    // Validate claim status and action
    if ($action === 'reject' && $oldStatus === 'pickup_scheduled') {
        $newStatus = 'cancelled';
        $message = "The donor rejected your pickup request for: ";

    } elseif ($action === 'collect' && $oldStatus === 'pickup_scheduled') {
        $newStatus = 'collected';
        $message = "Your food has been marked as collected: ";

    } elseif ($action === 'complete' && $oldStatus === 'collected') {
        $newStatus = 'completed';
        $message = "Your food donation has been completed: ";

    } else {
        throw new Exception("Invalid claim status.");
    }

    // Update claim
    $stmt = $conn->prepare("
        UPDATE claims
        SET status = ?
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $claimId]);

    // Update food listing
    if ($action === 'reject') {
        $foodStatus = strtotime($claim['expiry_time']) > time()
            ? 'available'
            : 'expired';

        $stmt = $conn->prepare("
            UPDATE food_listings
            SET status = ?
            WHERE id = ? AND donor_id = ?
        ");
        $stmt->execute([$foodStatus, $claim['food_id'], $donorId]);

    } elseif ($action === 'complete') {
        $stmt = $conn->prepare("
            UPDATE food_listings
            SET status = 'completed'
            WHERE id = ? AND donor_id = ?
        ");
        $stmt->execute([$claim['food_id'], $donorId]);
    }

    // Notify receiver
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim_update')
    ");
    $stmt->execute([
        $claim['receiver_id'],
        $message . $claim['food_name']
    ]);

    $conn->commit();

    header("Location: claims.php?updated=1");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("FoodLoop claim update error: " . $e->getMessage());

    header("Location: claims.php?error=1");
    exit;
}
