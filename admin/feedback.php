<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check whether a user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Verify admin role directly from the database
try {
    $adminStmt = $conn->prepare("
        SELECT id, name, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $adminStmt->execute([$_SESSION['user_id']]);
    $admin = $adminStmt->fetch();

    if (!$admin || $admin['role'] !== 'admin') {
        // Clear session cookie
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header("Location: ../auth/login.php");
        exit;
    }

    // Refresh session information from database
    $_SESSION['role'] = $admin['role'];
    $_SESSION['name'] = $admin['name'];

} catch (PDOException $e) {
    error_log("Admin feedback authorization error: " . $e->getMessage());
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}

// Fetch all feedback
try {
    $stmt = $conn->query("
        SELECT
            fb.id,
            fb.rating,
            fb.comment,
            fb.created_at,
            u.name AS receiver_name,
            u.email AS receiver_email,
            f.food_name,
            f.category,
            donor.name AS donor_name
        FROM feedback fb
        JOIN users u ON fb.user_id = u.id
        JOIN food_listings f ON fb.listing_id = f.id
        JOIN users donor ON f.donor_id = donor.id
        ORDER BY fb.created_at DESC
    ");

    $feedbacks = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Feedback fetching error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load feedback. Please try again later.");
}

$totalFeedback = count($feedbacks);

$averageRating = 0;

if ($totalFeedback > 0) {
    $averageRating = array_sum(
        array_column($feedbacks, 'rating')
    ) / $totalFeedback;
}

// Secure HTML output
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback | FoodLoop</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f8f5;
            color: #24352b;
        }

        .header {
            background: #123522;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 25px;
            font-weight: bold;
            color: #70d69a;
        }

        .header nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .header nav a {
            display: inline-block;
            background: #16834a;
            color: white;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 7px;
            font-size: 15px;
            transition: background 0.3s;
        }

        .header nav a:hover {
            background: #116b3c;
        }

        .header nav a.active {
            background: #0d6939;
            outline: 2px solid #70d69a;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .container h1 {
            margin-bottom: 25px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card,
        .feedback-card,
        .empty {
            background: white;
            padding: 25px;
            border-radius: 13px;
            box-shadow: 0 5px 20px #00000008;
        }

        .stat-card p {
            color: #718078;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            color: #16834a;
            font-size: 30px;
        }

        .feedback-card {
            margin-bottom: 18px;
        }

        .feedback-card h3 {
            margin-bottom: 12px;
        }

        .details {
            color: #64736a;
            line-height: 1.9;
        }

        .rating {
            color: #e5a000;
            font-size: 22px;
            margin: 12px 0;
        }

        .comment {
            background: #f3f8f4;
            padding: 15px;
            border-radius: 8px;
            margin-top: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .empty {
            text-align: center;
            color: #718078;
        }

        @media (max-width: 650px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header nav {
                width: 100%;
            }

            .header nav a {
                flex: 1;
                text-align: center;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <div class="brand">🌱 FoodLoop Admin</div>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="../notifications.php">Notifications</a>
            <a href="feedback.php" class="active">Feedback</a>
            <a href="../auth/logout.php">Logout</a>
        </nav>
    </header>

    <main class="container">

        <h1>Feedback Management</h1>

        <section class="stats">

            <div class="stat-card">
                <p>Total Feedback</p>
                <h2><?= $totalFeedback ?></h2>
            </div>

            <div class="stat-card">
                <p>Average Rating</p>
                <h2>
                    <?= $totalFeedback > 0
                        ? number_format($averageRating, 1) . " / 5"
                        : "0 / 5" ?>
                </h2>
            </div>

        </section>

        <h2 style="margin-bottom:20px;">All Receiver Feedback</h2>

        <?php if (empty($feedbacks)): ?>

            <div class="empty">
                <h3>No feedback available yet.</h3>
                <p>Receiver feedback will appear here after submission.</p>
            </div>

        <?php else: ?>

            <?php foreach ($feedbacks as $feedback): ?>

                <article class="feedback-card">

                    <h3><?= e($feedback['food_name']) ?></h3>

                    <div class="details">

                        <strong>Receiver:</strong>
                        <?= e($feedback['receiver_name']) ?><br>

                        <strong>Email:</strong>
                        <?= e($feedback['receiver_email']) ?><br>

                        <strong>Donor:</strong>
                        <?= e($feedback['donor_name']) ?><br>

                        <strong>Category:</strong>
                        <?= e($feedback['category']) ?><br>

                        <strong>Date:</strong>
                        <?= e($feedback['created_at']) ?>

                    </div>

                    <div class="rating">

                        <?= str_repeat("★", (int) $feedback['rating']) ?>
                        <?= str_repeat("☆", 5 - (int) $feedback['rating']) ?>

                        <span style="color:#64736a;font-size:15px;">
                            (<?= (int) $feedback['rating'] ?>/5)
                        </span>

                    </div>

                    <div class="comment">

                        <strong>Receiver Comment:</strong><br>

                        <?= $feedback['comment'] !== null && $feedback['comment'] !== ''
                            ? nl2br(e($feedback['comment']))
                            : 'No comment provided.' ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>

</body>

</html>