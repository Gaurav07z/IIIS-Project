
<?php
require_once "../config/database.php";
require_once "../config/session.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

$id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
    COUNT(*) AS total,
    SUM(status = 'available' AND expiry_time > NOW()) AS available,
    SUM(status = 'completed') AS completed
    FROM food_listings
    WHERE donor_id = ?
");
$stmt->execute([$id]);
$stats = $stmt->fetch();

$stmt = $conn->prepare("
    SELECT * FROM food_listings
    WHERE donor_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$id]);
$foods = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - FoodLoop</title>
    <link rel="stylesheet" href="donor.css">
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <div class="brand">🌱 FoodLoop</div>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="add-food.php">Add Donation</a>
        <a href="my-donations.php">My Donations</a>
        <a href="../index.php">Visit Website</a>
        <a href="claims.php">Manage Claims</a>
        <a href="../auth/logout.php">Logout</a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Welcome, <?= e($_SESSION['name']) ?>!</h1>
                <p>Manage your food donations.</p>
            </div>

            <a href="add-food.php" class="button">+ Add Food</a>
        </div>

        <div class="stats">

            <div class="stat-card">
                <p>Total Donations</p>
                <h2><?= (int)$stats['total'] ?></h2>
            </div>

            <div class="stat-card">
                <p>Available Donations</p>
                <h2><?= (int)$stats['available'] ?></h2>
            </div>

            <div class="stat-card">
                <p>Completed Donations</p>
                <h2><?= (int)$stats['completed'] ?></h2>
            </div>

        </div>

        <div class="panel">

            <h3>Recent Donations</h3>

            <table>
                <thead>
                    <tr>
                        <th>Food</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($foods): ?>

                    <?php foreach ($foods as $food): ?>
                    <tr>
                        <td><?= e($food['food_name']) ?></td>
                        <td><?= (int)$food['quantity'] ?></td>
                        <td><?= e($food['location']) ?></td>
                        <td>
                            <span class="status">
                                <?= e(ucfirst($food['status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No donations added yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </main>

</div>

</body>
</html>
