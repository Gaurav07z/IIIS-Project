<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';

    if (in_array($role, ['admin', 'donor', 'receiver'], true)) {
        header("Location: ../" . $role . "/dashboard.php");
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } elseif (
        $name === '' ||
        strlen($name) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        strlen($phone) > 20 ||
        strlen($organization) > 150 ||
        !in_array($role, ['donor', 'receiver'], true) ||
        strlen($password) < 8
    ) {
        $error = "Please enter valid details. Password must be at least 8 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users
                (name, email, phone, organization, role, password, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $organization,
                $role,
                $hash
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header("Location: login.php?registered=1");
            exit;

        } catch (PDOException $e) {
            $error = $e->getCode() == 23000
                ? "Email already registered."
                : "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FoodLoop</title>
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<div class="auth-box">

    <div class="logo">
        <i class="fa-solid fa-seedling"></i> FoodLoop
    </div>

    <h2>Create Account</h2>
    <p class="subtitle">Join us and help reduce food waste.</p>

    <?php if ($error): ?>
        <div class="message">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required maxlength="100"
                value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required maxlength="150"
                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" maxlength="20"
                value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label>Organization</label>
            <input type="text" name="organization" maxlength="150"
                value="<?= htmlspecialchars($_POST['organization'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label>Select Account Type</label>
            <select name="role" required>
                <option value="">Choose Role</option>
                <option value="donor" <?= ($_POST['role'] ?? '') === 'donor' ? 'selected' : '' ?>>Food Donor</option>
                <option value="receiver" <?= ($_POST['role'] ?? '') === 'receiver' ? 'selected' : '' ?>>Food Receiver</option>
            </select>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" minlength="8" required>
        </div>

        <button class="auth-btn" type="submit">
            Create Account
        </button>

    </form>

    <div class="auth-footer">
        Already registered?
        <a href="login.php">Login</a>
    </div>

    <div class="auth-footer">
        <a href="../index.php" class="back-home">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
    </div>

</div>

</body>
</html>
