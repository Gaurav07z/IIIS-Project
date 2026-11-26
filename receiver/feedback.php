<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'receiver') {
    header("Location: ../auth/login.php");
    exit;
}

$receiverId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT verification_status
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT);

if (!$listingId) {
    header("Location: my-claims.php");
    exit;
}

// Check completed claim and existing feedback
$stmt = $conn->prepare("
    SELECT f.id, f.food_name, f.category, u.name AS donor_name,
           fb.id AS feedback_id
    FROM claims c
    JOIN food_listings f ON c.listing_id = f.id
    JOIN users u ON f.donor_id = u.id
    LEFT JOIN feedback fb
        ON fb.listing_id = f.id AND fb.user_id = c.receiver_id
    WHERE c.receiver_id = ?
      AND c.listing_id = ?
      AND c.status = 'completed'
    LIMIT 1
");

$stmt->execute([$receiverId, $listingId]);
$food = $stmt->fetch();

if (!$food || $food['feedback_id']) {
    header("Location: my-claims.php");
    exit;
}

$error = '';
$rating = 5;
$comment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
        $comment = trim($_POST['comment'] ?? '');

        if (!$rating || $rating < 1 || $rating > 5) {
            $error = "Please select a rating between 1 and 5.";
        } elseif (strlen($comment) > 1000) {
            $error = "Feedback cannot exceed 1000 characters.";
        } else {

            try {
                // Recheck completed claim and insert feedback atomically
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    SELECT id
                    FROM claims
                    WHERE receiver_id = ?
                      AND listing_id = ?
                      AND status = 'completed'
                    FOR UPDATE
                ");
                $stmt->execute([$receiverId, $listingId]);

                if (!$stmt->fetch()) {
                    throw new Exception("Completed claim not found.");
                }

                try {
                    $conn->beginTransaction();

                    $stmt = $conn->prepare("
        SELECT id
        FROM claims
        WHERE receiver_id = ?
          AND listing_id = ?
          AND status = 'completed'
        FOR UPDATE
    ");
                    $stmt->execute([$receiverId, $listingId]);

                    if (!$stmt->fetch()) {
                        throw new Exception("Completed claim not found.");
                    }

                    $stmt = $conn->prepare("
        INSERT INTO feedback
        (user_id, listing_id, rating, comment)
        VALUES (?, ?, ?, ?)
    ");

                    $stmt->execute([
                        $receiverId,
                        $listingId,
                        $rating,
                        $comment !== '' ? $comment : null
                    ]);

                    $conn->commit();

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: my-claims.php?feedback=success");
                    exit;

                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    error_log("FoodLoop feedback error: " . $e->getMessage());
                    $error = "Feedback could not be submitted. It may already exist.";
                }

                $stmt->execute([
                    $receiverId,
                    $listingId,
                    $rating,
                    $comment
                ]);

                $conn->commit();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: my-claims.php?feedback=success");
                exit;

            } catch (Throwable $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log("FoodLoop feedback error: " . $e->getMessage());
                $error = "Feedback could not be submitted. It may already exist.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback | FoodLoop</title>
    <link rel="stylesheet" href="receiver.css">
</head>

<body>

    <header class="header">
        <a href="../index.php" class="logo">FoodLoop</a>

        <div>
            <a href="dashboard.php" class="logout">Dashboard</a>
            <a href="my-claims.php" class="logout">My Claims</a>
            <a href="../auth/logout.php" class="logout">Logout</a>
        </div>
    </header>

    <main class="container">

        <section class="welcome">
            <p class="tag">FOODLOOP FEEDBACK</p>
            <h1>Share Your Experience</h1>
            <p>Your feedback helps improve our food donation community.</p>
        </section>

        <div class="feedback-container">

            <h2><?= e($food['food_name']) ?></h2>

            <p><strong>Category:</strong> <?= e($food['category']) ?></p>
            <p><strong>Donor:</strong> <?= e($food['donor_name']) ?></p>

            <?php if ($error !== ''): ?>
                <div class="claim-message">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="feedback.php?listing_id=<?= (int) $listingId ?>">

                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <label for="rating">Rate Your Experience</label>

                <select name="rating" id="rating" required>
                    <option value="5" <?= $rating == 5 ? 'selected' : '' ?>>★★★★★ - Excellent</option>
                    <option value="4" <?= $rating == 4 ? 'selected' : '' ?>>★★★★ - Very Good</option>
                    <option value="3" <?= $rating == 3 ? 'selected' : '' ?>>★★★ - Good</option>
                    <option value="2" <?= $rating == 2 ? 'selected' : '' ?>>★★ - Fair</option>
                    <option value="1" <?= $rating == 1 ? 'selected' : '' ?>>★ - Poor</option>
                </select>

                <label for="comment">Write Your Feedback</label>

                <textarea name="comment" id="comment" rows="5" maxlength="1000"
                    placeholder="Share your experience..."><?= e($comment) ?></textarea>

                <button type="submit" class="claim-btn">
                    Submit Feedback
                </button>

                <a href="my-claims.php" class="feedback-back">
                    Back to My Claims
                </a>

            </form>

        </div>

    </main>

</body>

</html>