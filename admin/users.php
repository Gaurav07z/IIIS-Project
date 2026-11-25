<?php
require_once "../config/session.php";
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['csrf_token'] ?? '';
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Invalid request. Please refresh and try again.";
    } elseif (!$userId || !in_array($action, ['verified', 'rejected'], true)) {
        $error = "Invalid user or action.";
    } else {
        $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('donor', 'receiver')");
        $stmt->execute([$userId]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = "User not found.";
        } else {
            $update = $conn->prepare("UPDATE users SET verification_status = ? WHERE id = ?");
            $update->execute([$action, $userId]);

            $message = "User verification status updated.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';

$sql = "SELECT id, name, email, phone, role, organization, verification_status, created_at
        FROM users WHERE role IN ('donor', 'receiver')";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR organization LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
if (in_array($status, ['pending', 'verified', 'rejected'], true)) {
    $sql .= " AND verification_status = ?";
    $params[] = $status;
}
if (in_array($role, ['donor', 'receiver'], true)) {
    $sql .= " AND role = ?";
    $params[] = $role;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | FoodLoop</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f8f5;color:#24352b}
header{background:#123522;color:white;padding:18px 5%;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
.brand{font-size:24px;font-weight:bold;color:#70d69a}header a,.btn{display:inline-block;background:#16834a;color:white;padding:10px 14px;border:0;border-radius:7px;text-decoration:none;cursor:pointer}
main{max-width:1250px;margin:35px auto;padding:0 20px}.panel{background:white;padding:22px;border-radius:13px;box-shadow:0 5px 20px #00000008}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}.filters input,.filters select{padding:11px;border:1px solid #dce6df;border-radius:7px;min-width:160px}
table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;padding:13px;border-bottom:1px solid #e9efea;font-size:14px}
.wrap{overflow-x:auto}.status{padding:5px 9px;border-radius:20px;font-size:12px;background:#fff4d8;color:#8a6500}
.status.verified{background:#e5f7eb;color:#16834a}.status.rejected{background:#fde8e7;color:#b42318}
.actions{display:flex;gap:7px}.approve{background:#16834a}.reject{background:#b42318}
.message{padding:12px;margin:15px 0;border-radius:8px;background:#e5f7eb;color:#16834a}.error{background:#fde8e7;color:#b42318}
.small{color:#718078;font-size:13px}
@media(max-width:600px){header{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header>
    <div class="brand">🌱 FoodLoop Admin</div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>
<main>
    <h1>Manage Users</h1>
    <p class="small">Review donor and receiver accounts and update their verification status.</p>

    <?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>

    <section class="panel">
        <form class="filters" method="GET">
            <input type="search" name="search" placeholder="Search name, email, organization" value="<?= e($search) ?>">
            <select name="role">
                <option value="">All roles</option>
                <option value="donor" <?= $role === 'donor' ? 'selected' : '' ?>>Donors</option>
                <option value="receiver" <?= $role === 'receiver' ? 'selected' : '' ?>>Receivers</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button class="btn" type="submit">Search</button>
            <a class="btn" href="users.php">Reset</a>
        </form>

        <p class="small"><?= count($users) ?> user(s) found</p>
        <div class="wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Organization</th><th>Joined</th><th>Verification</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($users): foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['name']) ?><br><span class="small"><?= e($user['phone']) ?></span></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e(ucfirst($user['role'])) ?></td>
                    <td><?= e($user['organization'] ?: '-') ?></td>
                    <td><?= e($user['created_at']) ?></td>
                    <td><span class="status <?= e($user['verification_status']) ?>"><?= e(ucfirst($user['verification_status'])) ?></span></td>
                    <td>
                        <form class="actions" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                            <button class="btn approve" name="action" value="verified" type="submit">Verify</button>
                            <button class="btn reject" name="action" value="rejected" type="submit" onclick="return confirm('Reject this account verification?')">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
</body>
</html>
