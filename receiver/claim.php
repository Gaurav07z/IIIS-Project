<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request. Please go back and try again.");
}

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

if (!$listingId) {
    die("Invalid food listing.");
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT * FROM food_listings
        WHERE id = ? FOR UPDATE
    ");
    $stmt->execute([$listingId]);
    $food = $stmt->fetch();

    if (
        !$food || $food['status'] !== 'available' ||
        strtotime($food['expiry_time']) <= time()
    ) {
        $conn->rollBack();
        header("Location: dashboard.php?claim=unavailable");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id FROM claims
        WHERE listing_id = ? AND receiver_id = ?
    ");
    $stmt->execute([$listingId, $_SESSION['user_id']]);

    if ($stmt->fetch()) {
        $conn->rollBack();
        header("Location: dashboard.php?claim=duplicate");
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO claims (listing_id, receiver_id, quantity, status)
        VALUES (?, ?, ?, 'claimed')
    ");
    $stmt->execute([
        $listingId,
        $_SESSION['user_id'],
        $food['quantity']
    ]);

    $stmt = $conn->prepare("
        UPDATE food_listings
        SET status = 'claimed'
        WHERE id = ?
    ");
    $stmt->execute([$listingId]);

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim')
    ");
    $stmt->execute([
        $food['donor_id'],
        $_SESSION['name'] . " has claimed your food donation: " . $food['food_name']
    ]);

    $conn->commit();

    header("Location: dashboard.php?claim=success");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("Unable to process claim. Please try again.");
}