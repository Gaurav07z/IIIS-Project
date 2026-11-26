<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check donor login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

$donorId = $_SESSION['user_id'];

// Check donor verification
$stmt = $conn->prepare("
    SELECT verification_status
    FROM users
    WHERE id = ? AND role = 'donor'
    LIMIT 1
");
$stmt->execute([$donorId]);
$user = $stmt->fetch();

if (!$user || $user['verification_status'] !== 'verified') {
    header("Location: ../auth/login.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fetch donor claims
$stmt = $conn->prepare("
    SELECT c.*, f.food_name, f.location,
           u.name AS receiver_name,
           u.email AS receiver_email,
           u.phone AS receiver_phone
    FROM claims c
    JOIN food_listings f ON c.listing_id = f.id
    JOIN users u ON c.receiver_id = u.id
    WHERE f.donor_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$donorId]);
$claims = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims | FoodLoop</title>
    <link rel="stylesheet" href="donor.css">

    <style>
        .claim-card {
            background: white;
            padding: 22px;
            border-radius: 13px;
            margin-bottom: 18px;
            box-shadow: 0 5px 20px #00000008;
        }

        .claim-card h3 {
            margin-bottom: 12px;
        }

        .claim-card p {
            margin: 9px 0;
            color: #65736a;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .actions form {
            display: inline;
        }

        .secondary {
            background: #64756b;
        }

        .danger {
            background: #c74747;
        }

        .status {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="layout">

        <aside class="sidebar">
            <div class="brand">🌱 FoodLoop</div>
            <a href="dashboard.php">Dashboard</a>
            <a href="add-food.php">Add Donation</a>
            <a href="my-donations.php">My Donations</a>
            <a href="../notifications.php">Notifications</a>
            <a href="claims.php" class="active">Manage Claims</a>
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="main">
            <div class="topbar">
                <div>
                    <h1>Manage Claims</h1>
                    <p>Review receiver requests and manage food pickups.</p>
                </div>
            </div>
            <?php if (isset($_GET['updated'])): ?>
                <div class="message">Claim status updated successfully!</div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="message">Unable to update this claim. Please try again.</div>
            <?php endif; ?>
            <?php if ($claims): ?>
                <?php foreach ($claims as $claim): ?>
                    <div class="claim-card">
                        <h3><?= e($claim['food_name']) ?></h3>
                        <p><strong>Receiver:</strong> <?= e($claim['receiver_name']) ?></p>
                        <p><strong>Email:</strong> <?= e($claim['receiver_email']) ?></p>
                        <p><strong>Phone:</strong> <?= e($claim['receiver_phone'] ?: 'Not provided') ?></p>
                        <p><strong>Quantity:</strong> <?= e($claim['quantity']) ?></p>
                        <p><strong>Location:</strong> <?= e($claim['location']) ?></p>
                        <p><strong>Pickup time:</strong> <?= e($claim['pickup_time'] ?: 'Not scheduled') ?></p>
                        <p>
                            <strong>Status:</strong>
                            <?= e(ucwords(str_replace('_', ' ', $claim['status']))) ?>
                        </p>
                        <div class="actions">
                            <?php if ($claim['status'] === 'pickup_scheduled'): ?>
                                <form action="update-claim.php" method="POST">
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                    <input type="hidden" name="action" value="collect">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <button class="button" type="submit">Mark Collected</button>
                                </form>

                                <form action="update-claim.php" method="POST"
                                    onsubmit="return confirm('Reject this pickup request?')">
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <button class="button danger" type="submit">Reject</button>
                                </form>

                            <?php elseif ($claim['status'] === 'collected'): ?>

                                <form action="update-claim.php" method="POST">
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <button class="button" type="submit">Complete Donation</button>
                                </form>

                            <?php else: ?>
                                <span class="status">
                                    <?= e(ucwords(str_replace('_', ' ', $claim['status']))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="panel">No food claims received yet.</div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>