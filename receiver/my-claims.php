<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare("
    SELECT c.*, f.food_name, f.category, f.location,
           f.image, u.name AS donor_name
    FROM claims c
    JOIN food_listings f ON c.listing_id = f.id
    JOIN users u ON f.donor_id = u.id
    WHERE c.receiver_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$claims = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims | FoodLoop</title>
    <link rel="stylesheet" href="receiver.css">
</head>

<body>

    <header class="header">
        <a href="../index.php" class="logo">FoodLoop</a>
        <div>
            Welcome, <?= e($_SESSION['name']) ?>
            <a href="dashboard.php" class="logout">Dashboard</a>
            <a href="../auth/logout.php" class="logout">Logout</a>
        </div>
    </header>

    <main class="container">
        <section class="welcome">
            <p class="tag">RECEIVER DASHBOARD</p>
            <h1>My Food Claims</h1>
            <p>View your claimed donations and schedule food pickup.</p>
        </section>

        <?php if (isset($_GET['pickup']) && $_GET['pickup'] === 'success'): ?>
            <div class="claim-message">Pickup time scheduled successfully!</div>
        <?php elseif (isset($_GET['pickup']) && $_GET['pickup'] === 'error'): ?>
            <div class="claim-message">Unable to schedule pickup. Please check the date and try again.</div>
        <?php endif; ?>

        <div class="food-grid">
            <?php if ($claims): ?>
                <?php foreach ($claims as $claim): ?>
                    <article class="food-card">
                        <?php if ($claim['image']): ?>
                            <img src="../uploads/<?= e($claim['image']) ?>" alt="Food">
                        <?php else: ?>
                            <div class="no-image">FoodLoop</div>
                        <?php endif; ?>

                        <div class="food-info">
                            <span class="category"><?= e($claim['category']) ?></span>
                            <h3><?= e($claim['food_name']) ?></h3>
                            <p><strong>Quantity:</strong> <?= e($claim['quantity']) ?></p>
                            <p><strong>Donor:</strong> <?= e($claim['donor_name']) ?></p>
                            <p><strong>Location:</strong> <?= e($claim['location']) ?></p>
                            <p><strong>Status:</strong> <?= e(ucwords(str_replace('_', ' ', $claim['status']))) ?></p>

                            <?php if ($claim['pickup_time']): ?>
                                <p><strong>Pickup:</strong> <?= e($claim['pickup_time']) ?></p>
                            <?php endif; ?>

                            <?php if (in_array($claim['status'], ['claimed', 'pickup_scheduled'])): ?>
                                <form action="schedule-pickup.php" method="POST">
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <label>Choose pickup date and time</label>
                                    <input type="datetime-local" name="pickup_time" required>
                                    <button class="claim-btn" type="submit">Schedule Pickup</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">You have not claimed any food yet.</p>
            <?php endif; ?>
        </div>
    </main>

</body>

</html>