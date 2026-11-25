
<?php
require_once "../config/database.php";
require_once "../config/session.php";

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if (
        $name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !in_array($role, ['donor', 'receiver'], true) ||
        strlen($password) < 8
    ) {
        $error = "Enter valid details. Password must be at least 8 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, phone, organization, role, password)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $email,
                $phone,
                $organization,
                $role,
                $hash
            ]);

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
        <div class="message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required maxlength="100">
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required maxlength="150">
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" maxlength="20">
        </div>

        <div class="form-group">
            <label>Organization</label>
            <input type="text" name="organization" maxlength="150">
        </div>

        <div class="form-group">
            <label>Select Account Type</label>
            <select name="role" required>
                <option value="">Choose Role</option>
                <option value="donor">Food Donor</option>
                <option value="receiver">Food Receiver</option>
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
