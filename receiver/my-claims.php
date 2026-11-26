
<?php
require_once "../config/session.php";
require_once "../config/database.php";

// Check receiver login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

$receiverId = (int) $_SESSION['user_id'];

// Check receiver verification
$stmt = $conn->prepare("
    SELECT name, verification_status
    FROM users
    WHERE id = ? AND role = 'receiver'
    LIMIT 1
");
$stmt->execute([$receiverId]);
$user = $stmt->fetch();

if (!$user || $user['verification_status'] !== 'verified') {
    unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['name']);
    header("Location: ../auth/login.php");
    exit;
}

$_SESSION['name'] = $user['name'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fetch receiver's claims and feedback status
$stmt = $conn->prepare("
    SELECT
        c.*,
        f.food_name,
        f.category,
        f.location,
        f.image,
        u.name AS donor_name,
        fb.id AS feedback_id
    FROM claims c
    JOIN food_listings f ON c.listing_id = f.id
    JOIN users u ON f.donor_id = u.id
    LEFT JOIN feedback fb
        ON fb.listing_id = f.id
        AND fb.user_id = c.receiver_id
    WHERE c.receiver_id = ?
      AND u.verification_status = 'verified'
    ORDER BY c.created_at DESC
");

$stmt->execute([$receiverId]);
$claims = $stmt->fetchAll();

// Pickup messages
$pickupMessages = [
    'success' => 'Pickup time scheduled successfully!',
    'error' => 'Unable to schedule pickup. Please check the date and try again.'
];

$pickupStatus = $_GET['pickup'] ?? '';
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
            <a href="../notifications.php" class="logout">Notifications</a>
            <a href="../auth/logout.php" class="logout">Logout</a>
        </div>
    </header>

    <main class="container">

        <section class="welcome">
            <p class="tag">RECEIVER DASHBOARD</p>
            <h1>My Food Claims</h1>
            <p>View your claimed donations and schedule food pickup.</p>
        </section>

        <?php if (isset($pickupMessages[$pickupStatus])): ?>
            <div class="claim-message">
                <?= e($pickupMessages[$pickupStatus]) ?>
            </div>
        <?php elseif ($pickupStatus !== ''): ?>
            <div class="claim-message">
                Something went wrong. Please try again.
            </div>
        <?php endif; ?>

        <h2>My Donations</h2>

        <div class="food-grid">

            <?php if ($claims): ?>

                <?php foreach ($claims as $claim): ?>

                    <article class="food-card">

                        <?php if (!empty($claim['image'])): ?>
                            <img
                                src="../uploads/<?= e(basename($claim['image'])) ?>"
                                alt="<?= e($claim['food_name']) ?>">
                        <?php else: ?>
                            <div class="no-image">FoodLoop</div>
                        <?php endif; ?>

                        <div class="food-info">

                            <span class="category">
                                <?= e($claim['category']) ?>
                            </span>

                            <h3><?= e($claim['food_name']) ?></h3>

                            <p>
                                <strong>Quantity:</strong>
                                <?= (int) $claim['quantity'] ?>
                            </p>

                            <p>
                                <strong>Donor:</strong>
                                <?= e($claim['donor_name']) ?>
                            </p>

                            <p>
                                <strong>Location:</strong>
                                <?= e($claim['location']) ?>
                            </p>

                            <p>
                                <strong>Status:</strong>
                                <?= e(ucwords(str_replace('_', ' ', $claim['status']))) ?>
                            </p>

                            <?php if (!empty($claim['pickup_time'])): ?>
                                <p>
                                    <strong>Pickup:</strong>
                                    <?= e($claim['pickup_time']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (in_array($claim['status'], ['claimed', 'pickup_scheduled'], true)): ?>

                                <form action="schedule-pickup.php" method="POST">

                                    <input type="hidden" name="claim_id"
                                        value="<?= (int) $claim['id'] ?>">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>">

                                    <label>Choose pickup date and time</label>

                                    <input type="datetime-local"
                                        name="pickup_time" required>

                                    <button class="claim-btn" type="submit">
                                        Schedule Pickup
                                    </button>

                                </form>

                                <form action="cancel-claim.php" method="POST"
                                    onsubmit="return confirm('Are you sure you want to cancel this claim?');">

                                    <input type="hidden" name="claim_id"
                                        value="<?= (int) $claim['id'] ?>">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>">

                                    <button type="submit" class="cancel-btn">
                                        Cancel Claim
                                    </button>

                                </form>

                            <?php endif; ?>

                            <?php if ($claim['status'] === 'completed'): ?>

                                <?php if (empty($claim['feedback_id'])): ?>

                                    <a href="feedback.php?listing_id=<?= (int) $claim['listing_id'] ?>"
                                        class="feedback-btn">
                                        Give Feedback
                                    </a>

                                <?php else: ?>

                                    <button type="button" class="feedback-submitted" disabled>
                                        Feedback Submitted
                                    </button>

                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty">
                    You have not claimed any food yet.
                </p>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>
