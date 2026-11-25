<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$stats = [
    'users' => (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'donors' => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'donor'")->fetchColumn(),
    'receivers' => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'receiver'")->fetchColumn(),
    'foods' => (int)$conn->query("SELECT COUNT(*) FROM food_listings")->fetchColumn(),
    'available' => (int)$conn->query("SELECT COUNT(*) FROM food_listings WHERE status = 'available' AND expiry_time > NOW()")->fetchColumn(),
    'claims' => (int)$conn->query("SELECT COUNT(*) FROM claims")->fetchColumn()
];

$recent = $conn->query("
    SELECT f.food_name, f.quantity, f.status, f.created_at, u.name AS donor_name
    FROM food_listings f JOIN users u ON u.id = f.donor_id
    ORDER BY f.created_at DESC LIMIT 8
")->fetchAll();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | FoodLoop</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f8f5;color:#24352b}
header{background:#123522;color:white;padding:20px 5%;display:flex;justify-content:space-between;align-items:center;gap:15px}
.brand{font-size:25px;font-weight:bold;color:#70d69a}a{color:#16834a;text-decoration:none}
header a{background:#16834a;color:white;padding:10px 15px;border-radius:7px}
main{max-width:1200px;margin:35px auto;padding:0 20px}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:18px;margin:25px 0}
.card,.panel{background:white;padding:22px;border-radius:13px;box-shadow:0 5px 20px #00000008}
.card p{margin:0;color:#718078}.card h2{font-size:30px;color:#16834a;margin:10px 0 0}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:13px;border-bottom:1px solid #e9efea}
.wrap{overflow-x:auto}.status{background:#e5f7eb;color:#16834a;padding:5px 9px;border-radius:20px;font-size:12px}
@media(max-width:600px){header{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header><div class="brand">🌱 FoodLoop Admin</div><div>Welcome, <?= e($_SESSION['name']) ?> &nbsp; <a href="../notifications.php">Notifications</a> <a href="../auth/logout.php">Logout</a></div></header>
<main>
<h1>Admin Dashboard</h1>
<p>Overview of FoodLoop platform activity.</p>
<div class="stats">
<div class="card"><p>Total Users</p><h2><?= $stats['users'] ?></h2></div>
<div class="card"><p>Donors</p><h2><?= $stats['donors'] ?></h2></div>
<div class="card"><p>Receivers</p><h2><?= $stats['receivers'] ?></h2></div>
<div class="card"><p>Food Donations</p><h2><?= $stats['foods'] ?></h2></div>
<div class="card"><p>Available Food</p><h2><?= $stats['available'] ?></h2></div>
<div class="card"><p>Total Claims</p><h2><?= $stats['claims'] ?></h2></div>
</div>
<section class="panel">
<h2>Recent Food Donations</h2>
<div class="wrap"><table>
<thead><tr><th>Food</th><th>Donor</th><th>Quantity</th><th>Status</th><th>Created</th></tr></thead>
<tbody>
<?php if ($recent): foreach ($recent as $food): ?>
<tr><td><?= e($food['food_name']) ?></td><td><?= e($food['donor_name']) ?></td><td><?= (int)$food['quantity'] ?></td><td><span class="status"><?= e(ucfirst($food['status'])) ?></span></td><td><?= e($food['created_at']) ?></td></tr>
<?php endforeach; else: ?><tr><td colspan="5">No donations yet.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>