<?php
require_once "../config/session.php";
require_once "../config/database.php";
$error = "";

// Check existing login session and current verification status
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare(
        "SELECT id, role, verification_status FROM users WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (
        $currentUser &&
        in_array($currentUser['role'], ['admin', 'donor', 'receiver'], true) &&
        (
            $currentUser['role'] === 'admin' ||
            $currentUser['verification_status'] === 'verified'
        )
    ) {
        header("Location: ../" . $currentUser['role'] . "/dashboard.php");
        exit;
    }

    // Clear invalid or unverified session
    $_SESSION = [];
    session_regenerate_id(true);

    if ($currentUser && $currentUser['role'] !== 'admin') {
        $status = $currentUser['verification_status'] ?? 'pending';

        if ($status === 'pending') {
            $error = "Your account is waiting for admin verification.";
        } elseif ($status === 'rejected') {
            $error = "Your account verification was rejected. Please contact the administrator.";
        } else {
            $error = "Your account is not verified. Please contact the administrator.";
        }
    }
}

// Login process
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        if ($user['role'] !== 'admin') {
            $status = $user['verification_status'] ?? 'pending';

            if ($status === 'pending') {
                $error = "Your account is waiting for admin verification.";
            } elseif ($status === 'rejected') {
                $error = "Your account verification was rejected. Please contact the administrator.";
            } elseif ($status !== 'verified') {
                $error = "Your account status is invalid. Please contact the administrator.";
            }
        }

        if ($error === '') {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../" . $user['role'] . "/dashboard.php");
            exit;
        }

    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FoodLoop</title>
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<div class="auth-box">

    <div class="logo">
        <i class="fa-solid fa-seedling"></i> FoodLoop
    </div>

    <h2>Welcome Back!</h2>
    <p class="subtitle">Login to continue your journey.</p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="message success">
            Registration successful! Your account is waiting for admin verification.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="auth-btn">
            Login to FoodLoop
        </button>

    </form>

    <div class="auth-footer">
        Don't have an account?
        <a href="register.php">Register</a>
    </div>

    <div class="auth-footer">
        <a href="../index.php">Back to Home</a>
    </div>

</div>

</body>
</html>
