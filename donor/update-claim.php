<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: claims.php");
    exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request.");
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$allowed = ['confirm', 'reject', 'collect', 'complete'];

if (!$claimId || !in_array($action, $allowed, true)) {
    header("Location: claims.php?error=1");
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT c.*, f.donor_id, f.food_name, f.id AS food_id, f.expiry_time
        FROM claims c
        JOIN food_listings f ON c.listing_id = f.id
        WHERE c.id = ? AND f.donor_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$claimId, $_SESSION['user_id']]);
    $claim = $stmt->fetch();

    if (!$claim) {
        throw new Exception("Claim not found.");
    }

    $oldStatus = $claim['status'];
    $newStatus = $oldStatus;

    if ($action === 'confirm' && $oldStatus === 'pickup_scheduled') {
        $message = "The donor confirmed your pickup for: ";
    } elseif ($action === 'reject' && $oldStatus === 'pickup_scheduled') {
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

    if ($newStatus !== $oldStatus) {
        $stmt = $conn->prepare("UPDATE claims SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $claimId]);
    }

    if ($action === 'reject') {
        $foodStatus = strtotime($claim['expiry_time']) > time() ? 'available' : 'expired';
        $stmt = $conn->prepare("UPDATE food_listings SET status = ? WHERE id = ? AND donor_id = ?");
        $stmt->execute([$foodStatus, $claim['food_id'], $_SESSION['user_id']]);
    } elseif ($action === 'complete') {
        $stmt = $conn->prepare("UPDATE food_listings SET status = 'completed' WHERE id = ? AND donor_id = ?");
        $stmt->execute([$claim['food_id'], $_SESSION['user_id']]);
    }

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim_update')
    ");
    $stmt->execute([$claim['receiver_id'], $message . $claim['food_name']]);

    $conn->commit();
    header("Location: claims.php?updated=1");
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: claims.php?error=1");
    exit;
}