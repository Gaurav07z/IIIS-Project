<?php
require_once "config/session.php";
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Verify user account and role
$stmt = $conn->prepare(
    "SELECT id, role, verification_status FROM users WHERE id = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['donor', 'receiver', 'admin'], true)) {
    session_unset();
    session_destroy();
    header("Location: auth/login.php");
    exit;
}

// Donor and receiver accounts must be verified
if (
    in_array($user['role'], ['donor', 'receiver'], true) &&
    $user['verification_status'] !== 'verified'
) {
    session_unset();
    session_destroy();
    header("Location: auth/login.php?error=verification");
    exit;
}

$_SESSION['role'] = $user['role'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        header("Location: notifications.php?error=invalid");
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'read_all') {

            $stmt = $conn->prepare(
                "UPDATE notifications
                 SET read_status = 1
                 WHERE user_id = ?"
            );
            $stmt->execute([$userId]);

        } elseif ($action === 'read' || $action === 'delete') {

            $notificationId = filter_var(
                $_POST['notification_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$notificationId || $notificationId < 1) {
                throw new Exception("Invalid notification.");
            }

            if ($action === 'read') {
                $stmt = $conn->prepare(
                    "UPDATE notifications
                     SET read_status = 1
                     WHERE id = ? AND user_id = ?"
                );
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM notifications
                     WHERE id = ? AND user_id = ?"
                );
            }

            $stmt->execute([$notificationId, $userId]);

        } else {
            throw new Exception("Invalid action.");
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: notifications.php?success=1");
        exit;

    } catch (Throwable $ex) {
        error_log("FoodLoop notification error: " . $ex->getMessage());

        header("Location: notifications.php?error=action");
        exit;
    }
}

// Fetch notifications
$stmt = $conn->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// Count unread notifications
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ? AND read_status = 0
");
$stmt->execute([$userId]);
$unreadCount = (int) $stmt->fetchColumn();

// Role-based dashboard
$dashboard = [
    'donor' => 'donor/dashboard.php',
    'receiver' => 'receiver/dashboard.php',
    'admin' => 'admin/dashboard.php'
];

$dashboardUrl = $dashboard[$user['role']];
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
            color: #24352b;
            font-family: Arial, sans-serif;
        }

        .notification-container {
            max-width: 850px;
            margin: 45px auto;
            padding: 20px;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .notification {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid #16834a;
            box-shadow: 0 5px 20px #00000008;
        }

        .notification.unread {
            background: #e5f7eb;
        }

        .notification small {
            display: block;
            color: #718078;
            margin-top: 10px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .notification-button {
            padding: 9px 15px;
            border: none;
            border-radius: 7px;
            background: #16834a;
            color: white;
            cursor: pointer;
        }

        .delete-button {
            background: #c74747;
        }

        .back-link {
            color: #16834a;
            text-decoration: none;
        }

        .empty {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }

        .count {
            background: #16834a;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .error-message {
            background: #fff0ed;
            color: #c0392b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        @media(max-width: 600px) {
            .notification-container {
                margin: 20px auto;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <main class="notification-container">

        <a class="back-link" href="<?= e($dashboardUrl) ?>">
            ← Back to Dashboard
        </a>

        <div class="notification-header">

            <div>
                <h1>My Notifications</h1>
                <p>Stay updated about your FoodLoop activities.</p>

                <span class="count">
                    <?= $unreadCount ?> Unread
                </span>
            </div>

            <?php if ($unreadCount > 0): ?>
                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <input type="hidden"
                           name="action"
                           value="read_all">

                    <button class="notification-button" type="submit">
                        Mark All as Read
                    </button>

                </form>
            <?php endif; ?>

        </div>

        <?php if (isset($_GET['success'])): ?>
            <p class="empty">Notification updated successfully!</p>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                Unable to process your request. Please try again.
            </div>
        <?php endif; ?>

        <?php if ($notifications): ?>

            <?php foreach ($notifications as $notification): ?>

                <div class="notification <?= !$notification['read_status'] ? 'unread' : '' ?>">

                    <strong>
                        <?= $notification['read_status']
                            ? 'Notification'
                            : 'New Notification' ?>
                    </strong>

                    <p><?= e($notification['message']) ?></p>

                    <small>
                        <?= e(date(
                            'd M Y, h:i A',
                            strtotime($notification['created_at'])
                        )) ?>
                    </small>

                    <div class="notification-actions">

                        <?php if (!$notification['read_status']): ?>

                            <form method="POST">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= e($_SESSION['csrf_token']) ?>">

                                <input type="hidden"
                                       name="action"
                                       value="read">

                                <input type="hidden"
                                       name="notification_id"
                                       value="<?= (int) $notification['id'] ?>">

                                <button class="notification-button" type="submit">
                                    Mark as Read
                                </button>

                            </form>

                        <?php endif; ?>

                        <form method="POST"
                              onsubmit="return confirm('Delete this notification?')">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= e($_SESSION['csrf_token']) ?>">

                            <input type="hidden"
                                   name="action"
                                   value="delete">

                            <input type="hidden"
                                   name="notification_id"
                                   value="<?= (int) $notification['id'] ?>">

                            <button class="notification-button delete-button" type="submit">
                                Delete
                            </button>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">
                <h3>No Notifications Yet</h3>
                <p>New FoodLoop updates will appear here.</p>
            </div>

        <?php endif; ?>

    </main>

</body>
</html>
