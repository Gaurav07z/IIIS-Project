<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-donations.php");
    exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request.");
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: my-donations.php?error=invalid");
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT f.image
        FROM food_listings f
        WHERE f.id = ? AND f.donor_id = ?
          AND f.status IN ('available', 'cancelled', 'expired')
          AND NOT EXISTS (SELECT 1 FROM claims c WHERE c.listing_id = f.id)
        FOR UPDATE
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $food = $stmt->fetch();

    if (!$food) {
        throw new Exception("This donation cannot be deleted because it may have an associated claim.");
    }

    $stmt = $conn->prepare("
        DELETE FROM food_listings
        WHERE id = ? AND donor_id = ?
          AND NOT EXISTS (SELECT 1 FROM claims c WHERE c.listing_id = food_listings.id)
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Donation was not deleted.");
    }

    $conn->commit();

    if ($food['image'] && is_file("../uploads/" . basename($food['image']))) {
        unlink("../uploads/" . basename($food['image']));
    }

    header("Location: my-donations.php?deleted=1");
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: my-donations.php?error=delete");
    exit;
}