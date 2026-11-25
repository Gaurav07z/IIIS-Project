<?php
require_once "config/session.php";
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request.");
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET read_status = 1
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);

    header("Location: notifications.php?read=1");
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

$dashboard = [
    'donor' => 'donor/dashboard.php',
    'receiver' => 'receiver/dashboard.php',
    'admin' => 'admin/dashboard.php'
];

$dashboardUrl = $dashboard[$_SESSION['role']] ?? 'index.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | FoodLoop</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f4f8f5;
            font-family: Arial, sans-serif;
            color: #24352b;
        }

        .notification-container {
            max-width: 850px;
            margin: 50px auto;
            padding: 20px;
        }

        .notification {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border-left: 5px solid #16834a;
            box-shadow: 0 5px 20px #00000008;
        }

        .unread {
            background: #e5f7eb;
        }

        .notification small {
            display: block;
            color: #718078;
            margin-top: 10px;
        }

        .notification-button {
            background: #16834a;
            color: white;
            border: none;
            border-radius: 7px;
            padding: 12px 18px;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #16834a;
            text-decoration: none;
        }

        .empty {
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
    </style>
</head>

<body>

    <main class="notification-container">
        <a class="back-link" href="<?= e($dashboardUrl) ?>">← Back to Dashboard</a>

        <h1>My Notifications</h1>
        <p>Stay updated about your FoodLoop activities.</p>

        <?php if (isset($_GET['read'])): ?>
            <p class="empty">All notifications marked as read.</p>
        <?php endif; ?>

        <?php if ($notifications): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification <?= !$notification['read_status'] ? 'unread' : '' ?>">
                    <strong>
                        <?= !$notification['read_status'] ? 'New Notification' : 'Notification' ?>
                    </strong>
                    <p><?= e($notification['message']) ?></p>
                    <small><?= e($notification['created_at']) ?></small>
                </div>
            <?php endforeach; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <button class="notification-button" type="submit">
                    Mark All as Read
                </button>
            </form>
        <?php else: ?>
            <div class="empty">You don't have any notifications yet.</div>
        <?php endif; ?>
    </main>

</body>

</html>