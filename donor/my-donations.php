<?php
require_once "../config/database.php";
require_once "../config/session.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM food_listings
    WHERE donor_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$foods = $stmt->fetchAll();

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
    <title>My Donations - FoodLoop</title>
    <link rel="stylesheet" href="donor.css">
</head>

<body>

    <div class="layout">

        <aside class="sidebar">
            <div class="brand">🌱 FoodLoop</div>
            <a href="dashboard.php">Dashboard</a>
            <a href="add-food.php">Add Donation</a>
            <a href="claims.php">Manage Claims</a>
            <a href="my-donations.php" class="active">My Donations</a>
            <a href="../notifications.php">Notifications</a>
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="main">

            <div class="topbar">
                <div>
                    <h1>My Donations</h1>
                    <p>Manage your food listings.</p>
                </div>

                <a href="add-food.php" class="button">+ Add Food</a>
            </div>

            <?php if (isset($_GET['added'])): ?>
                <div class="message">Food donation added successfully!</div>
            <?php endif; ?>

            <div class="panel">

                <table>
                    <thead>
                        <tr>
                            <th>Food Name</th>
                            <th>Quantity</th>
                            <th>Category</th>
                            <th>Expiry</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($foods): ?>

                            <?php foreach ($foods as $food): ?>

                                <tr>
                                    <td><?= e($food['food_name']) ?></td>
                                    <td><?= (int) $food['quantity'] ?></td>
                                    <td><?= e($food['category']) ?></td>
                                    <td><?= e(date('d M Y, h:i A', strtotime($food['expiry_time']))) ?></td>
                                    <td>
                                        <span class="status">
                                            <?= e(ucfirst($food['status'])) ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">No food donations found.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </main>

    </div>

</body>

</html>