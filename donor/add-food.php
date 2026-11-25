<?php
require_once "../config/database.php";
require_once "../config/session.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);
    $description = trim($_POST['description'] ?? '');
    $prepared = $_POST['prepared_at'] ?? '';
    $expiry = $_POST['expiry_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $image = null;

    try {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid form request.");
        }

        if (
            $name === '' || $category === '' ||
            !$quantity || $quantity < 1 ||
            $location === '' || !$expiry
        ) {
            throw new Exception("Please fill all required fields correctly.");
        }

        $expiryDate = strtotime($expiry);
        $preparedDate = $prepared ? strtotime($prepared) : null;

        if (!$expiryDate || $expiryDate <= time()) {
            throw new Exception("Expiry time must be in the future.");
        }

        if ($prepared && (!$preparedDate || $preparedDate > time())) {
            throw new Exception("Invalid preparation time.");
        }

        if (!is_dir("../uploads")) {
            mkdir("../uploads", 0755, true);
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

            if (
                $_FILES['image']['error'] !== UPLOAD_ERR_OK ||
                $_FILES['image']['size'] > 5 * 1024 * 1024
            ) {
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

            $image = bin2hex(random_bytes(16)) . "." . $types[$mime];

            if (
                !move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    "../uploads/" . $image
                )
            ) {
                throw new Exception("Image upload failed.");
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO food_listings
            (donor_id, food_name, category, quantity, description,
             prepared_at, expiry_time, location, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $name,
            $category,
            $quantity,
            $description,
            $prepared ? date('Y-m-d H:i:s', $preparedDate) : null,
            date('Y-m-d H:i:s', $expiryDate),
            $location,
            $image
        ]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: my-donations.php?added=1");
        exit;

    } catch (Throwable $e) {
        if ($image && is_file("../uploads/" . $image)) {
            unlink("../uploads/" . $image);
        }
        $error = $e instanceof PDOException
            ? "Could not save donation. Please try again."
            : $e->getMessage();
    }
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
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="field">
                        <label>Food Name *</label>
                        <input type="text" name="food_name" maxlength="150" required>
                    </div>

                    <div class="form-grid">

                        <div class="field">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option>Vegetarian</option>
                                <option>Non-Vegetarian</option>
                                <option>Bakery</option>
                                <option>Fruits</option>
                                <option>Dairy</option>
                                <option>Other</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Quantity (meals/items) *</label>
                            <input type="number" name="quantity" min="1" required>
                        </div>

                        <div class="field">
                            <label>Preparation Time</label>
                            <input type="datetime-local" name="prepared_at" max="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="field">
                            <label>Pickup Deadline *</label>
                            <input type="datetime-local" name="expiry_time" min="<?= date('Y-m-d\TH:i', time() + 60) ?>"
                                required>
                        </div>

                    </div>

                    <div class="field">
                        <label>Pickup Location *</label>
                        <input type="text" name="location" maxlength="255" required>
                    </div>

                    <div class="field">
                        <label>Food Description</label>
                        <textarea name="description" rows="4" maxlength="2000"></textarea>
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