<?php
require_once "../config/database.php";
require_once "../config/session.php";

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

$error = "";

$categories = [
    'Vegetarian',
    'Non-Vegetarian',
    'Bakery',
    'Fruits',
    'Dairy',
    'Other'
];

$form = [
    'food_name' => '',
    'category' => '',
    'quantity' => '',
    'description' => '',
    'prepared_at' => '',
    'expiry_time' => '',
    'location' => ''
];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    $name = $form['food_name'];
    $category = $form['category'];
    $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT);
    $description = $form['description'];
    $prepared = $form['prepared_at'];
    $expiry = $form['expiry_time'];
    $location = $form['location'];

    $image = null;
    $imagePath = "../uploads/";

    try {
        // CSRF validation
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid form request. Please refresh the page.");
        }

        // Required field validation
        if (
            $name === '' ||
            strlen($name) > 150 ||
            !in_array($category, $categories, true) ||
            !$quantity ||
            $quantity < 1 ||
            $location === '' ||
            strlen($location) > 255 ||
            strlen($description) > 2000 ||
            !$expiry
        ) {
            throw new Exception("Please fill all required fields correctly.");
        }

        // Date validation
        $expiryDate = DateTime::createFromFormat('Y-m-d\TH:i', $expiry);
        $preparedDate = $prepared
            ? DateTime::createFromFormat('Y-m-d\TH:i', $prepared)
            : null;

        if (!$expiryDate || $expiryDate->format('Y-m-d\TH:i') !== $expiry) {
            throw new Exception("Invalid expiry date.");
        }

        if ($expiryDate->getTimestamp() <= time()) {
            throw new Exception("Expiry time must be in the future.");
        }

        if (
            $prepared && (
                !$preparedDate ||
                $preparedDate->format('Y-m-d\TH:i') !== $prepared ||
                $preparedDate->getTimestamp() > time()
            )
        ) {
            throw new Exception("Invalid preparation time.");
        }

        // Create upload directory if required
        if (!is_dir($imagePath)) {
            if (!mkdir($imagePath, 0755, true) && !is_dir($imagePath)) {
                throw new Exception("Upload directory could not be created.");
            }
        }

        // Image upload validation
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

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);

            $types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($types[$mime])) {
                throw new Exception("Only JPG, PNG and WEBP images are allowed.");
            }

            if (@getimagesize($_FILES['image']['tmp_name']) === false) {
                throw new Exception("Invalid image file.");
            }

            $image = bin2hex(random_bytes(16)) . "." . $types[$mime];

            if (
                !move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $imagePath . $image
                )
            ) {
                throw new Exception("Image upload failed.");
            }
        }

        // Save donation
        $stmt = $conn->prepare("
            INSERT INTO food_listings
            (donor_id, food_name, category, quantity, description,
             prepared_at, expiry_time, location, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $donorId,
            $name,
            $category,
            $quantity,
            $description,
            $preparedDate ? $preparedDate->format('Y-m-d H:i:s') : null,
            $expiryDate->format('Y-m-d H:i:s'),
            $location,
            $image
        ]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: my-donations.php?added=1");
        exit;

    } catch (Throwable $e) {

        if ($image && is_file($imagePath . $image)) {
            unlink($imagePath . $image);
        }

        error_log("FoodLoop donation error: " . $e->getMessage());

        $error = $e instanceof PDOException
            ? "Could not save donation. Please try again."
            : $e->getMessage();
    }
}

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
    <title>Add Food - FoodLoop</title>
    <link rel="stylesheet" href="donor.css">

    <style>
        .form-box {
            max-width: 750px;
            background: white;
            padding: 30px;
            border-radius: 15px;
        }

        .field {
            margin-bottom: 18px;
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

        .field textarea {
            resize: vertical;
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

        @media(max-width: 550px) {
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
            <a href="add-food.php" class="active">Add Donation</a>
            <a href="my-donations.php">My Donations</a>
            <a href="claims.php">Manage Claims</a>
            <a href="../auth/logout.php">Logout</a>
        </aside>

        <main class="main">

            <div class="topbar">
                <div>
                    <h1>Add Food Donation</h1>
                    <p>Share your surplus food with others.</p>
                </div>
            </div>

            <div class="form-box">
                <?php if ($error): ?>
                    <div class="error"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="field">
                        <label>Food Name *</label>
                        <input type="text" name="food_name" maxlength="150" value="<?= e($form['food_name']) ?>"
                            required>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $form['category'] === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Quantity (meals/items) *</label>
                            <input type="number" name="quantity" min="1" value="<?= e($form['quantity']) ?>" required>
                        </div>
                        <div class="field">
                            <label>Preparation Time</label>
                            <input type="datetime-local" name="prepared_at" max="<?= date('Y-m-d\TH:i') ?>"
                                value="<?= e($form['prepared_at']) ?>">
                        </div>

                        <div class="field">
                            <label>Pickup Deadline *</label>
                            <input type="datetime-local" name="expiry_time" min="<?= date('Y-m-d\TH:i', time() + 60) ?>"
                                value="<?= e($form['expiry_time']) ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Pickup Location *</label>
                        <input type="text" name="location" maxlength="255" value="<?= e($form['location']) ?>" required>
                    </div>
                    <div class="field">
                        <label>Food Description</label>
                        <textarea name="description" rows="4" maxlength="2000"><?= e($form['description']) ?></textarea>
                    </div>

                    <div class="field">
                        <label>Food Image (Maximum 5 MB)</label>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>
                    <button class="button" type="submit">
                        Publish Donation
                    </button>
                    <a href="dashboard.php" style="margin-left:12px">
                        Cancel
                    </a>
                </form>
            </div>
        </main>
    </div>
</body>

</html>