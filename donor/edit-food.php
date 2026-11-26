
<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

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

function validDateTime($value)
{
    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);

    return $date && $date->format('Y-m-d\TH:i') === $value
        ? $date->getTimestamp()
        : false;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: my-donations.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT * FROM food_listings
     WHERE id = ? AND donor_id = ? AND status = 'available'"
);
$stmt->execute([$id, $_SESSION['user_id']]);
$food = $stmt->fetch();

if (!$food) {
    header("Location: my-donations.php?error=unavailable");
    exit;
}

$error = "";
$categories = [
    'Vegetarian',
    'Non-Vegetarian',
    'Bakery',
    'Fruits',
    'Dairy',
    'Other'
];

$newImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please try again.");
        }

        $name = trim($_POST['food_name'] ?? '');
        $category = $_POST['category'] ?? '';
        $quantity = filter_var(
            $_POST['quantity'] ?? '',
            FILTER_VALIDATE_INT
        );
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');

        $expiryInput = trim($_POST['expiry_time'] ?? '');
        $preparedInput = trim($_POST['prepared_at'] ?? '');

        $expiry = validDateTime($expiryInput);
        $prepared = $preparedInput !== ''
            ? validDateTime($preparedInput)
            : null;

        if (
            $name === '' ||
            strlen($name) > 150 ||
            !in_array($category, $categories, true) ||
            $quantity === false ||
            $quantity < 1 ||
            $location === '' ||
            strlen($location) > 255 ||
            strlen($description) > 2000 ||
            $expiry === false ||
            $expiry <= time() ||
            ($preparedInput !== '' &&
                ($prepared === false || $prepared > time()))
        ) {
            throw new Exception(
                "Please enter valid food details and a future expiry time."
            );
        }

        $image = $food['image'];
        $uploadDir = "../uploads/";

        if (
            isset($_FILES['image']) &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Image upload failed.");
            }

            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                throw new Exception("Image must be under 5 MB.");
            }

            if (!is_dir($uploadDir) &&
                !mkdir($uploadDir, 0755, true) &&
                !is_dir($uploadDir)) {
                throw new Exception("Upload folder could not be created.");
            }

            $tmpFile = $_FILES['image']['tmp_name'];

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpFile);

            $types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($types[$mime]) || getimagesize($tmpFile) === false) {
                throw new Exception(
                    "Only valid JPG, PNG and WEBP images are allowed."
                );
            }

            $newImage = bin2hex(random_bytes(16)) . "." . $types[$mime];

            if (!move_uploaded_file($tmpFile, $uploadDir . $newImage)) {
                throw new Exception("Could not save uploaded image.");
            }

            $image = $newImage;
        }

        $stmt = $conn->prepare("
            UPDATE food_listings
            SET food_name = ?,
                category = ?,
                quantity = ?,
                description = ?,
                prepared_at = ?,
                expiry_time = ?,
                location = ?,
                image = ?
            WHERE id = ?
              AND donor_id = ?
              AND status = 'available'
              AND expiry_time > NOW()
        ");

        $stmt->execute([
            $name,
            $category,
            $quantity,
            $description,
            $prepared !== null
                ? date('Y-m-d H:i:s', $prepared)
                : null,
            date('Y-m-d H:i:s', $expiry),
            $location,
            $image,
            $id,
            $_SESSION['user_id']
        ]);

        if ($stmt->rowCount() === 0) {
            // A zero row count can also mean that no values changed.
            $verify = $conn->prepare("
                SELECT id FROM food_listings
                WHERE id = ?
                  AND donor_id = ?
                  AND status = 'available'
                  AND expiry_time > NOW()
            ");
            $verify->execute([$id, $_SESSION['user_id']]);

            if (!$verify->fetch()) {
                throw new Exception(
                    "This donation is no longer available for editing."
                );
            }
        }

        if (
            $newImage &&
            !empty($food['image']) &&
            is_file($uploadDir . basename($food['image']))
        ) {
            unlink($uploadDir . basename($food['image']));
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: my-donations.php?updated=1");
        exit;

    } catch (Throwable $ex) {
        if ($newImage && is_file("../uploads/" . $newImage)) {
            unlink("../uploads/" . $newImage);
        }

        error_log("FoodLoop edit donation error: " . $ex->getMessage());

        $error = $ex instanceof PDOException
            ? "Could not update donation. Please try again."
            : $ex->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donation | FoodLoop</title>
    <link rel="stylesheet" href="donor.css">

    <style>
        .form-box {
            max-width: 750px;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce7de;
            border-radius: 8px;
            font: inherit;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .error {
            padding: 12px;
            background: #fff0ed;
            color: #c0392b;
            margin-bottom: 18px;
            border-radius: 8px;
        }

        @media(max-width:550px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="layout">

        <aside class="sidebar">
            <div class="brand">🌱 FoodLoop</div>
            <a href="dashboard.php">Dashboard</a>
            <a href="add-food.php">Add Donation</a>
            <a href="my-donations.php" class="active">My Donations</a>
            <a href="claims.php">Manage Claims</a>
            <a href="../notifications.php">Notifications</a>
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="main">

            <div class="topbar">
                <div>
                    <h1>Edit Donation</h1>
                    <p>Update your available food listing.</p>
                </div>
            </div>

            <div class="form-box">

                <?php if ($error): ?>
                    <div class="error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="field">
                        <label>Food Name</label>
                        <input
                            name="food_name"
                            maxlength="150"
                            required
                            value="<?= e($_POST['food_name'] ?? $food['food_name']) ?>">
                    </div>

                    <div class="form-grid">

                        <div class="field">
                            <label>Category</label>
                            <select name="category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option
                                        value="<?= e($cat) ?>"
                                        <?= (($_POST['category'] ?? $food['category']) === $cat)
                                            ? 'selected'
                                            : '' ?>>
                                        <?= e($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label>Quantity</label>
                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                required
                                value="<?= e($_POST['quantity'] ?? $food['quantity']) ?>">
                        </div>

                        <div class="field">
                            <label>Prepared At</label>
                            <input
                                type="datetime-local"
                                name="prepared_at"
                                value="<?= e(
                                    $_POST['prepared_at'] ??
                                    ($food['prepared_at']
                                        ? date('Y-m-d\TH:i', strtotime($food['prepared_at']))
                                        : '')
                                ) ?>">
                        </div>

                        <div class="field">
                            <label>Expiry Time</label>
                            <input
                                type="datetime-local"
                                name="expiry_time"
                                min="<?= date('Y-m-d\TH:i', time() + 60) ?>"
                                required
                                value="<?= e(
                                    $_POST['expiry_time'] ??
                                    date('Y-m-d\TH:i', strtotime($food['expiry_time']))
                                ) ?>">
                        </div>

                    </div>

                    <div class="field">
                        <label>Pickup Location</label>
                        <input
                            name="location"
                            maxlength="255"
                            required
                            value="<?= e($_POST['location'] ?? $food['location']) ?>">
                    </div>

                    <div class="field">
                        <label>Description</label>
                        <textarea
                            name="description"
                            rows="4"
                            maxlength="2000"><?= e($_POST['description'] ?? $food['description']) ?></textarea>
                    </div>

                    <div class="field">
                        <label>Replace Image (optional)</label>
                        <input
                            type="file"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>

                    <button class="button" type="submit">Save Changes</button>
                    <a href="my-donations.php">Cancel</a>

                </form>

            </div>

        </main>
    </div>

</body>
</html>
