<?php
session_start();
require 'config.php';
require 'functions.php'; // contains addNotification()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* =========================
   1. Current & Last Month Totals
   ========================= */
$currentMonthStmt = $conn->prepare("
    SELECT SUM(amount)     
    FROM expenses 
    WHERE user_id = :user_id 
    AND MONTH(expense_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE())
");
$currentMonthStmt->execute([':user_id' => $user_id]);
$currentTotal = $currentMonthStmt->fetchColumn() ?? 0;

$lastMonthStmt = $conn->prepare("
    SELECT SUM(amount) 
    FROM expenses 
    WHERE user_id = :user_id 
    AND MONTH(expense_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$lastMonthStmt->execute([':user_id' => $user_id]);
$lastTotal = $lastMonthStmt->fetchColumn() ?? 0;

/* =========================
   2. Prevent Duplicate Notifications
   ========================= */
function notificationExists($conn, $user_id, $message) {
    $check = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND message = :msg AND DATE(created_at) = CURDATE()");
    $check->execute([':uid' => $user_id, ':msg' => $message]);
    return $check->fetchColumn() > 0;
}

/* =========================
   3. Expense Trend Notifications
   ========================= */
if ($currentTotal > $lastTotal) {
    $msg = "📈 Your expenses this month have increased compared to last month.";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "warning");
    }
} elseif ($currentTotal < $lastTotal && $lastTotal > 0) {
    $msg = "📉 Your expenses this month are lower than last month.";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "success");
    }
} elseif ($currentTotal == $lastTotal && $currentTotal > 0) {
    $msg = "📊 Your expenses this month are the same as last month.";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "info");
    }
}

/* =========================
   4. Monthly Budget Check
   ========================= */
$monthlyLimit = 5000;
if ($currentTotal >= $monthlyLimit) {
    $msg = "🚨 You have crossed your monthly limit of ₹5000!";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "danger");
    }
} elseif ($currentTotal >= ($monthlyLimit * 0.8)) {
    $msg = "⚠ You’ve reached 80% of your monthly budget of ₹5000.";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "warning");
    }
}

/* =========================
   5. Cash in Hand
   ========================= */
$incomeStmt = $conn->prepare("SELECT SUM(amount) FROM incomes WHERE user_id = :user_id");
$incomeStmt->execute([':user_id' => $user_id]);
$totalIncome = $incomeStmt->fetchColumn() ?? 0;

$expenseStmt = $conn->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = :user_id");
$expenseStmt->execute([':user_id' => $user_id]);
$totalExpense = $expenseStmt->fetchColumn() ?? 0;

$cashInHand = $totalIncome - $totalExpense;

if ($cashInHand < 0) {
    $msg = "💸 Your cash in hand is negative. Please review your expenses.";
    if (!notificationExists($conn, $user_id, $msg)) {
        addNotification($conn, $user_id, $msg, "danger");
    }
}

/* =========================
   6. Fetch All Notifications
   ========================= */
$allStmt = $conn->prepare("
    SELECT message, type, created_at, is_read
    FROM notifications
    WHERE user_id = :uid
    ORDER BY created_at DESC
");
$allStmt->execute([':uid' => $user_id]);
$allNotifications = $allStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Notifications</title>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        header {
            background: #111827;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 20px;
        }
        nav ul li a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
        }
        nav ul li a:hover {
            color: #38bdf8;
        }
        .toggle-btn {
            background: #2563eb;
            border: none;
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-title {
            color: #fff;
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        .notification-box {
            background: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        .notification-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
        }
        .notification-box.unread {
            border-left: 8px solid #ffc107;
            background: #fffbea;
        }
        .message {
            font-size: 1.2rem;
            margin-bottom: 12px;
            font-weight: 500;
            color: #222;
        }
        .time {
            font-size: 0.9rem;
            color: #666;
            margin-top: auto;
            text-align: right;
        }
        /* color types */
        .type-success { border-left: 8px solid #28a745; }
        .type-warning { border-left: 8px solid #ffc107; }
        .type-danger  { border-left: 8px solid #dc3545; }
        .type-info    { border-left: 8px solid #17a2b8; }
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
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
        <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
    </header>

    <div class="container">
        <h2 class="page-title">🔔 All Notifications</h2>

        <?php
        if (!empty($allNotifications)) {
            foreach ($allNotifications as $n) {
                $readClass = $n['is_read'] == 0 ? 'unread' : '';
                $typeClass = "type-" . htmlspecialchars($n['type']);
                echo "<div class='notification-box $readClass $typeClass'>
                        <div class='message'>" . htmlspecialchars($n['message']) . "</div>
                        <div class='time'>" . $n['created_at'] . "</div>
                      </div>";
            }
        } else {
            echo "<div class='notification-box type-success'>
                    <div class='message'>✅ You’re on track this month!</div>
                  </div>";
        }
        ?>
    </div>

    <script>
        function toggleMode() {
            document.body.classList.toggle("dark-mode");
        }
    </script>
</body>
</html>