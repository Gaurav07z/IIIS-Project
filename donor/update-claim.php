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

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request.");
}

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

$transitions = [
    'confirm' => ['pickup_scheduled', 'pickup_confirmed'],
    'reject' => ['pickup_scheduled', 'cancelled'],
    'collect' => ['pickup_confirmed', 'collected'],
    'complete' => ['collected', 'completed']
];

if (!$claimId || !isset($transitions[$action])) {
    header("Location: claims.php?error=1");
    exit;
}

[$oldStatus, $newStatus] = $transitions[$action];

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT c.*, f.donor_id, f.food_name
        FROM claims c
        JOIN food_listings f ON c.listing_id = f.id
        WHERE c.id = ? AND f.donor_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$claimId, $_SESSION['user_id']]);
    $claim = $stmt->fetch();

    if (!$claim || $claim['status'] !== $oldStatus) {
        throw new Exception("Invalid claim status.");
    }

    $stmt = $conn->prepare("
        UPDATE claims SET status = ? WHERE id = ?
    ");
    $stmt->execute([$newStatus, $claimId]);

    if ($action === 'reject') {
        $stmt = $conn->prepare("
            UPDATE food_listings
            SET status = CASE
                WHEN expiry_time > NOW() THEN 'available'
                ELSE 'expired'
            END
            WHERE id = ? AND donor_id = ?
        ");
        $stmt->execute([$claim['listing_id'], $_SESSION['user_id']]);
    }

    if ($action === 'complete') {
        $stmt = $conn->prepare("
            UPDATE food_listings SET status = 'completed'
            WHERE id = ? AND donor_id = ?
        ");
        $stmt->execute([$claim['listing_id'], $_SESSION['user_id']]);
    }

    $messages = [
        'confirm' => 'The donor confirmed your pickup request for ',
        'reject' => 'The donor rejected your pickup request for ',
        'collect' => 'Your food has been marked as collected: ',
        'complete' => 'Your food donation has been completed: '
    ];

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, 'claim_update')
    ");
    $stmt->execute([
        $claim['receiver_id'],
        $messages[$action] . $claim['food_name']
    ]);

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
