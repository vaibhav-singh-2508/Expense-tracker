<?php
session_start();
require 'config.php';
include 'ledger_header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// fetch totals
$totalGive = $conn->prepare("SELECT SUM(amount) FROM ledger WHERE user_id = :uid AND type = 'give'");
$totalGive->execute([':uid' => $_SESSION['user_id']]);
$give = $totalGive->fetchColumn() ?: 0;

$totalGet = $conn->prepare("SELECT SUM(amount) FROM ledger WHERE user_id = :uid AND type = 'take'");
$totalGet->execute([':uid' => $_SESSION['user_id']]);
$get = $totalGet->fetchColumn() ?: 0;

$balance = $get - $give;

// handle search
$search = $_GET['search'] ?? '';
if ($search) {
    $stmt = $conn->prepare("SELECT * FROM customers 
                            WHERE user_id = :uid AND name LIKE :search 
                            ORDER BY created_at DESC");
    $stmt->execute([
        ':uid' => $_SESSION['user_id'],
        ':search' => "%$search%"
    ]);
} else {
    // fetch all customers
    $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid' => $_SESSION['user_id']]);
}
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customer balances
$balStmt = $conn->prepare("
    SELECT customer_id, 
           SUM(CASE WHEN type='give' THEN amount ELSE -amount END) as balance
    FROM ledger 
    WHERE user_id = :uid 
    GROUP BY customer_id
");
$balStmt->execute([':uid' => $_SESSION['user_id']]);
$balances = $balStmt->fetchAll(PDO::FETCH_KEY_PAIR); // customer_id => balance

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];

    // First delete ledger entries related to this customer (to prevent foreign key issues)
    $delLedger = $conn->prepare("DELETE FROM ledger WHERE user_id = :uid AND customer_id = :cid");
    $delLedger->execute([':uid' => $_SESSION['user_id'], ':cid' => $delete_id]);

    // Now delete customer
    $delCustomer = $conn->prepare("DELETE FROM customers WHERE user_id = :uid AND id = :cid");
    $delCustomer->execute([':uid' => $_SESSION['user_id'], ':cid' => $delete_id]);

// Refresh page to show updated list
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Ledger Dashboard</title>
        <link href="css/ledger_style.css" rel="stylesheet" type="text/css"/>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background:linear-gradient(180deg,#0b1022, #0f172a 40%, #0b1022);
            }
            .container {
                width: 90%;
                max-width: 1000px;
                margin: 30px auto;
                background: #fff;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            h1, h2 {
                text-align: center;
                margin-bottom: 20px;
                color: #333;
            }
            .totals {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            .box {
                flex: 1;
                margin: 10px;
                padding: 20px;
                border-radius: 10px;
                font-size: 18px;
                font-weight: bold;
                text-align: center;
                color: white;
            }
            .green {
                background: #28a745;
            }
            .red {
                background: #dc3545;
            }

            .ledger-actions {
                margin: 20px 0;
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .btn-ledger {
                background: #007bff;
                color: #fff;
                padding: 10px 18px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: bold;
                transition: 0.3s ease;
            }
            .btn-ledger:hover {
                background: #0056b3;
            }

            .ledger-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .ledger-table th, .ledger-table td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: center;
            }
            .ledger-table th {
                background: #007bff;
                color: white;
            }
            .ledger-table tr {
                cursor: pointer;
                transition: background 0.2s;
            }
            .ledger-table tr:hover {
                background: #f1f1f1;
            }

            .search-box {
                text-align: center;
                margin: 15px 0;
            }
            .search-box input {
                padding: 8px;
                width: 250px;
                border-radius: 6px;
                border: 1px solid #ccc;
            }
            .search-box button {
                padding: 8px 12px;
                border: none;
                border-radius: 6px;
                background: #007bff;
                color: #fff;
                cursor: pointer;
            }
            .search-box button:hover {
                background: #0056b3;
            }
            .btn-action {
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 14px;
                text-decoration: none;
                margin: 0 3px;
                transition: 0.3s;
            }

            .btn-action.edit {
                background: #ffc107;
                color: #111;
            }
            .btn-action.edit:hover {
                background: #e0a800;
                color: #fff;
            }

            .btn-action.delete {
                background: #dc3545;
                color: #fff;
            }
            .btn-action.delete:hover {
                background: #b02a37;
            }
            .balance-positive {
                background: #17a2b8;
                color: #fff;
            }
            .balance-negative {
                background: #6c757d;
                color: #fff;
            }
        </style>
        <script>
            function goToCustomer(id) {
                window.location.href = "customer.php?id=" + id;
            }
        </script>
    </head>
    <body>
        <div class="container">
            <h1>Ledger Dashboard</h1>

            <!-- Totals -->
            <div class="totals">
                <div class="box green">You Will Get: ₹<?= number_format($get, 2) ?></div>
                <div class="box red">You Will Give: ₹<?= number_format($give, 2) ?></div>

                <?php if ($balance >= 0): ?>
                    <div class="box" style="background:#17a2b8;">
                        Net Balance: ₹<?= number_format($balance, 2) ?> (Profit)
                    </div>
                <?php else: ?>
                    <div class="box" style="background:#6c757d;">
                        Net Balance: ₹<?= number_format(abs($balance), 2) ?> (Loss)
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions 
            <div class="ledger-actions">
                <a href="add_customer.php" class="btn-ledger">➕ Add Person</a>
                <a href="ledger_report.php" class="btn-ledger">📊 Report</a>
            </div> -->

            <!-- Search -->
            <div class="search-box">
                <form method="get">
                    <input type="text" name="search"  id="search "placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit">Search</button>
                </form>
            </div>

            <!-- Customers List -->
            <h2>Customers List</h2>
            <table class="ledger-table">
                <tr>
                    <th>Name</th><th>Phone</th><th>Email</th><th>Created</th><th>Balance</th><th>Action</th>
                </tr>
                <?php if ($customers): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr onclick="goToCustomer(<?= $c['id'] ?>)">
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['phone']) ?></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= $c['created_at'] ?></td>
                            <td>
    <?php 
        $bal = $balances[$c['id']] ?? 0;
        if ($bal > 0) {
            echo "<span style='color:red;font-weight:bold;'> You Will Give ₹".number_format($bal,2)."</span>";
        } elseif ($bal < 0) {
            echo "<span style='color:green;font-weight:bold;'>You Will Get ₹".number_format(abs($bal),2)."</span>";
        } else {
            echo "<span style='color:gray;'>Settled</span>";
        }
    ?>
</td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-action delete" onclick="return confirm('Are you sure you want to delete this customer?');">🗑 Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No matching records found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </body>
</html>
<?php include 'ledger_footer.php'; ?>