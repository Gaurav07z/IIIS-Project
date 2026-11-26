<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

// Verify donor account
$check = $conn->prepare(
    "SELECT verification_status FROM users WHERE id = ? AND role = 'donor'"
);
$check->execute([$_SESSION['user_id']]);
$donor = $check->fetch();

if (!$donor || $donor['verification_status'] !== 'verified') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php?error=verification");
    exit;
}

// Allow POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-donations.php");
    exit;
}

// Validate CSRF token
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: my-donations.php?error=invalid");
    exit;
}

// Validate donation ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: my-donations.php?error=invalid");
    exit;
}

try {
    $conn->beginTransaction();

    // Lock and check the donation
    $stmt = $conn->prepare("
        SELECT f.id, f.image
        FROM food_listings f
        WHERE f.id = ?
          AND f.donor_id = ?
          AND f.status IN ('available', 'cancelled', 'expired')
          AND NOT EXISTS (
              SELECT 1
              FROM claims c
              WHERE c.listing_id = f.id
          )
        FOR UPDATE
    ");

    $stmt->execute([$id, $_SESSION['user_id']]);
    $food = $stmt->fetch();

    if (!$food) {
        throw new Exception("Donation cannot be deleted.");
    }

    // Delete donation
    $stmt = $conn->prepare("
        DELETE FROM food_listings
        WHERE id = ?
          AND donor_id = ?
          AND status IN ('available', 'cancelled', 'expired')
          AND NOT EXISTS (
              SELECT 1
              FROM claims c
              WHERE c.listing_id = food_listings.id
          )
    ");

    $stmt->execute([$id, $_SESSION['user_id']]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Donation was not deleted.");
    }

    $conn->commit();

    // Delete image only after successful database deletion
    if (
        !empty($food['image']) &&
        basename($food['image']) === $food['image']
    ) {
        $imagePath = "../uploads/" . $food['image'];

        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }

    // Generate a fresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: my-donations.php?deleted=1");
    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("FoodLoop delete donation error: " . $e->getMessage());

    header("Location: my-donations.php?error=delete");
    exit;
}
