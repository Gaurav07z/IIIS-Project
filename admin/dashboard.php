<?php
require_once "../config/session.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
?>

<h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
<h2>Admin Dashboard</h2>

<p>Manage FoodLoop platform here.</p>

<a href="../auth/logout.php">Logout</a>