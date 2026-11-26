<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check receiver login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

$id = (int) $_SESSION['user_id'];

// Check receiver verification
$stmt = $conn->prepare("
    SELECT name, verification_status
    FROM users
    WHERE id = ? AND role = 'receiver'
    LIMIT 1
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user || $user['verification_status'] !== 'verified') {
    unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['name']);
    header("Location: ../auth/login.php");
    exit;
}

$_SESSION['name'] = $user['name'];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch available food from verified donors
$stmt = $conn->query("
    SELECT f.*, u.name AS donor_name
    FROM food_listings f
    JOIN users u ON f.donor_id = u.id
    WHERE f.status = 'available'
      AND f.expiry_time > NOW()
      AND u.role = 'donor'
      AND u.verification_status = 'verified'
    ORDER BY f.created_at DESC
");

$foods = $stmt->fetchAll();

// Claim messages
$messages = [
    'success' => 'Food claimed successfully!',
    'unavailable' => 'Sorry, this food is no longer available.',
    'duplicate' => 'You have already claimed this food.'
];

$claimMessage = $_GET['claim'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receiver Dashboard | FoodLoop</title>
    <link rel="stylesheet" href="receiver.css">
</head>

<body>

    <header class="header">
        <a href="../index.php" class="logo">FoodLoop</a>

        <div>
            Welcome, <?= e($_SESSION['name']) ?>

            <a href="my-claims.php" class="logout">My Claims</a>
            <a href="../notifications.php" class="logout">Notifications</a>
            <a href="../auth/logout.php" class="logout">Logout</a>
        </div>
    </header>
    <main class="container">
        <section class="welcome">
            <p class="tag">RECEIVER DASHBOARD</p>
            <h1>Find food. Reduce waste.</h1>
            <p>
                Explore available food donations and connect with donors
                in your area.
            </p>
        </section>
        <?php if (isset($messages[$claimMessage])): ?>
            <div class="claim-message">
                <?= e($messages[$claimMessage]) ?>
            </div>
        <?php elseif ($claimMessage !== ''): ?>
            <div class="claim-message">
                Please try again.
            </div>
        <?php endif; ?>
        <h2>Available Food Donations</h2>
        <div class="food-grid">
            <?php if (count($foods) > 0): ?>
                <?php foreach ($foods as $food): ?>
                    <article class="food-card">
                        <?php if (!empty($food['image'])): ?>
                            <img src="../uploads/<?= e(basename($food['image'])) ?>" alt="<?= e($food['food_name']) ?>">
                        <?php else: ?>
                            <div class="no-image">FoodLoop</div>
                        <?php endif; ?>
                        <div class="food-info">
                            <span class="category">
                                <?= e($food['category']) ?>
                            </span>
                            <h3><?= e($food['food_name']) ?></h3>
                            <p>
                                <?= e($food['description'] ?? '') ?>
                            </p>
                            <p>
                                <strong>Quantity:</strong>
                                <?= (int) $food['quantity'] ?>
                            </p>
                            <p>
                                <strong>Donor:</strong>
                                <?= e($food['donor_name']) ?>
                            </p>
                            <p>
                                <strong>Location:</strong>
                                <?= e($food['location']) ?>
                            </p>
                            <p>
                                <strong>Expires:</strong>
                                <?= e($food['expiry_time']) ?>
                            </p>

                            <form action="claim.php" method="POST">
                                <input type="hidden" name="listing_id" value="<?= (int) $food['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="claim-btn">
                                    Claim Food
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">
                    No food donations are available right now.
                </p>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>