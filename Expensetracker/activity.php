<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$user_id = $_SESSION['user_id'];

// Expenses you owe
$stmt = $conn->prepare("SELECT se.id as share_id, e.note as description, e.amount as total_amount, se.share_amount, u.name as paid_by
                        FROM shared_expenses se
                        JOIN expenses e ON se.expense_id = e.id
                        JOIN users u ON e.user_id = u.id
                        WHERE se.user_id = ? AND se.is_settled = 0 AND e.user_id != ?");
$stmt->execute([$user_id, $user_id]);
$owe_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expenses others owe you
$stmt = $conn->prepare("SELECT se.id as share_id, e.note as description, e.amount as total_amount, se.share_amount, u.name as owes_user
                        FROM shared_expenses se
                        JOIN expenses e ON se.expense_id = e.id
                        JOIN users u ON se.user_id = u.id
                        WHERE e.user_id = ? AND se.user_id != ? AND se.is_settled = 0");
$stmt->execute([$user_id, $user_id]);
$owed_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shared Expense Activity</title>
<style>
    body { font-family:'Segoe UI', sans-serif; background: linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:#fff; margin:0; padding:0; }
    header { background: rgba(0,0,0,0.85); padding:15px 40px; display:flex; justify-content: space-between; align-items:center; }
    header h1 { margin:0; font-size:26px; }
    nav ul { list-style:none; display:flex; gap:20px; margin:0; padding:0; }
    nav ul li a { color:#fff; text-decoration:none; font-weight:500; }
    nav ul li a:hover { color:#f39c12; }
    h2, h3 { text-align:center; margin-top:20px; }
    table { width:90%; margin:15px auto; border-collapse:collapse; background:#fff; color:#333; border-radius:12px; overflow:hidden; }
    th, td { padding:12px; text-align:center; border-bottom:1px solid #ccc; }
    th { background:#f39c12; color:#fff; }
    input[type="submit"] { padding:6px 12px; background:#f39c12; color:#fff; border:none; border-radius:8px; cursor:pointer; }
    input[type="submit"]:hover { background:#d35400; }
    .back { text-align:center; margin:20px; }
    .back a { color:#f39c12; text-decoration:none; font-weight:500; }
    .back a:hover { text-decoration:underline; }
</style>
</head>
<body>

<header>
    <h1>Expense Manager</h1>
    <nav>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="ledger_dashboard.php">Ledger</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="report.php">Report</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<h2>Shared Expense Activity</h2>

<h3>Expenses Others Owe You</h3>
<table>
    <tr>
        <th>Description</th>
        <th>Total Amount</th>
        <th>Share Amount</th>
        <th>Owes User</th>
        <th>Action</th>
    </tr>
    <?php foreach($owed_records as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td><?= $row['total_amount'] ?></td>
        <td><?= $row['share_amount'] ?></td>
        <td><?= htmlspecialchars($row['owes_user']) ?></td>
        <td>
            <form method="POST" action="settle_share.php">
                <input type="hidden" name="share_id" value="<?= $row['share_id'] ?>">
                <input type="submit" value="Mark Settled">
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="back">
    <a href="dashboard.php">⬅ Back to Dashboard</a>
</div>

</body>
</html>
