
<?php
require_once "../config/database.php";
require_once "../config/session.php";

if (isset($_SESSION['user_id'])) {
    header("Location: ../" . $_SESSION['role'] . "/dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email = ? LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        header("Location: ../" . $user['role'] . "/dashboard.php");
        exit;

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
            Registration successful! Please login.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message"><?= htmlspecialchars($error) ?></div>
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
