<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-claims.php");
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request.");
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$pickup = trim($_POST['pickup_time'] ?? '');

try {
    $date = new DateTime($pickup);
    $now = new DateTime();

    if (!$claimId || !$pickup || $date <= $now) {
        throw new Exception("Invalid pickup time.");
    }

    $stmt = $conn->prepare("
        UPDATE claims
        SET pickup_time = ?, status = 'pickup_scheduled'
        WHERE id = ? AND receiver_id = ?
        AND status IN ('claimed', 'pickup_scheduled')
    ");

    $stmt->execute([
        $date->format('Y-m-d H:i:s'),
        $claimId,
        $_SESSION['user_id']
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Claim could not be updated.");
    }

    $stmt = $conn->prepare("
        SELECT f.donor_id, f.food_name
        FROM claims c
        JOIN food_listings f ON c.listing_id = f.id
        WHERE c.id = ?
    ");
    $stmt->execute([$claimId]);
    $food = $stmt->fetch();

    if ($food) {
        $message = $_SESSION['name'] . " scheduled pickup for "
            . $food['food_name'] . " at "
            . $date->format('d M Y, h:i A');

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type)
            VALUES (?, ?, 'pickup')
        ");
        $stmt->execute([$food['donor_id'], $message]);
    }

    header("Location: my-claims.php?pickup=success");
    exit;

} catch (Throwable $e) {
    header("Location: my-claims.php?pickup=error");
    exit;
}
