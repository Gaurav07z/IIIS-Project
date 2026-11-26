<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

// Check donor verification
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare(
    "SELECT * FROM food_listings WHERE donor_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$foods = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Donations - FoodLoop</title>
    <link rel="stylesheet" href="donor.css">

    <style>
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .small-btn {
            padding: 8px 11px;
            border: 0;
            border-radius: 7px;
            background: #16834a;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }

        .delete-btn {
            background: #c74747;
        }

        .table-wrap {
            overflow-x: auto;
        }
    </style>
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

            <?php if (isset($_GET['updated'])): ?>
                <div class="message">Donation updated successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="message">Donation deleted successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="message">
                    This donation cannot be changed or deleted. Check its status and claims.
                </div>
            <?php endif; ?>

            <div class="panel table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Food Name</th>
                            <th>Quantity</th>
                            <th>Category</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($foods): ?>
                            <?php foreach ($foods as $food): ?>
                                <tr>
                                    <td><?= e($food['food_name']) ?></td>
                                    <td><?= (int) $food['quantity'] ?></td>
                                    <td><?= e($food['category']) ?></td>
                                    <td>
                                        <?= e(date('d M Y, h:i A', strtotime($food['expiry_time']))) ?>
                                    </td>
                                    <td>
                                        <span class="status">
                                            <?= e(ucfirst($food['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if (
                                                $food['status'] === 'available' &&
                                                strtotime($food['expiry_time']) > time()
                                            ): ?>
                                                <a class="small-btn" href="edit-food.php?id=<?= (int) $food['id'] ?>">
                                                    Edit
                                                </a>
                                                <form method="POST" action="delete-food.php"
                                                    onsubmit="return confirm('Delete this donation?')">
                                                    <input type="hidden" name="id" value="<?= (int) $food['id'] ?>">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <button class="small-btn delete-btn" type="submit">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span>Not editable</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No food donations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>

</html>