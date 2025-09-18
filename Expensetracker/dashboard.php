<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$user_id = $_SESSION['user_id'];

// --- Filters ---
$search = $_GET['search'] ?? '';
$search_query = "%$search%";

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$category = $_GET['category'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';

// Build conditions
$conditions_income = ["user_id = ?"];
$params_income = [$user_id];

$conditions_expense = ["user_id = ?"];
$params_expense = [$user_id];

if ($search) {
    $conditions_income[] = "(amount LIKE ? OR income_date LIKE ?)";
    $params_income[] = $search_query;
    $params_income[] = $search_query;

    $conditions_expense[] = "(amount LIKE ? OR category LIKE ? OR expense_date LIKE ?)";
    $params_expense[] = $search_query;
    $params_expense[] = $search_query;
    $params_expense[] = $search_query;
}

if ($from && $to) {
    $conditions_income[] = "income_date BETWEEN ? AND ?";
    $params_income[] = $from;
    $params_income[] = $to;

    $conditions_expense[] = "expense_date BETWEEN ? AND ?";
    $params_expense[] = $from;
    $params_expense[] = $to;
}

if ($category) {
    $conditions_expense[] = "category = ?";
    $params_expense[] = $category;
}

if ($min_amount !== '' && $max_amount !== '') {
    $conditions_income[] = "amount BETWEEN ? AND ?";
    $params_income[] = $min_amount;
    $params_income[] = $max_amount;

    $conditions_expense[] = "amount BETWEEN ? AND ?";
    $params_expense[] = $min_amount;
    $params_expense[] = $max_amount;
}

// Default: current month data if no filters
if (!$search && !$from && !$to && !$category && $min_amount === '' && $max_amount === '') {
    $month_start = date("Y-m-01");
    $month_end = date("Y-m-t");
    $conditions_income[] = "income_date BETWEEN ? AND ?";
    $params_income[] = $month_start;
    $params_income[] = $month_end;

    $conditions_expense[] = "expense_date BETWEEN ? AND ?";
    $params_expense[] = $month_start;
    $params_expense[] = $month_end;
}

// Final query
$sql = "
    SELECT id, amount, 'Income' AS category, income_date AS record_date, 'IN' AS type, 'income' AS source 
    FROM incomes 
    WHERE " . implode(" AND ", $conditions_income) . "
    UNION ALL
    SELECT id, amount, category, expense_date AS record_date, 'OUT' AS type, 'expense' AS source 
    FROM expenses 
    WHERE " . implode(" AND ", $conditions_expense) . "
    ORDER BY record_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge($params_income, $params_expense));
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_income = 0;
$total_expense = 0;
foreach ($records as $r) {
    if ($r['type'] === 'IN')
        $total_income += $r['amount'];
    else
        $total_expense += $r['amount'];
}
$balance = $total_income - $total_expense;

// Fetch categories dynamically (from both income & expense tables)
$cat_stmt = $conn->prepare("
    SELECT DISTINCT category FROM expenses WHERE user_id = :uid
    UNION
    SELECT DISTINCT 'Income' as category FROM incomes WHERE user_id = :uid
");
$cat_stmt->execute([':uid' => $user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Total you owe
$stmt = $conn->prepare("SELECT SUM(share_amount) as total_owe FROM shared_expenses WHERE user_id = ? AND is_settled = 0 AND expense_id IN (SELECT id FROM expenses WHERE user_id != ?)");
$stmt->execute([$user_id, $user_id]);
$total_owe = $stmt->fetchColumn() ?? 0;

// Total others owe you
$stmt = $conn->prepare("SELECT SUM(share_amount) as total_owed FROM shared_expenses se JOIN expenses e ON se.expense_id = e.id WHERE e.user_id = ? AND se.user_id != ? AND se.is_settled = 0");
$stmt->execute([$user_id, $user_id]);
$total_owed = $stmt->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Dashboard | Expense Manager</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            /* Reset */
            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: 'Segoe UI', system-ui, Roboto, sans-serif;
                background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
                color: #fff;
                line-height: 1.6;
            }

            /* Header */
            header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 28px;
                background: rgba(0,0,0,0.25);
                backdrop-filter: blur(8px);
                z-index: 100;
            }
            header h1 {
                font-size: 1.5rem;
                font-weight: 700;
            }
            nav ul {
                display: flex;
                gap: 20px;
                list-style: none;
            }
            nav a {
                text-decoration: none;
                color: #fff;
                font-weight: 600;
                position: relative;
            }
            nav a::after {
                content: '';
                position: absolute;
                width: 0;
                height: 2px;
                left: 0;
                bottom: -4px;
                background: #f39c12;
                transition: width 0.3s;
            }
            nav a:hover::after {
                width: 100%;
            }

            /* Buttons */
            .btn {
                padding: 10px 20px;
                border-radius: 10px;
                font-weight: 700;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease-in-out;
            }
            .btn.success {
                background: #28a745;
                color: #fff;
            }
            .btn.success:hover {
                filter: brightness(1.1);
            }
            .btn.danger {
                background: #dc3545;
                color: #fff;
            }
            .btn.danger:hover {
                filter: brightness(1.1);
            }

            /* Dark Mode Toggle */
            .toggle-btn {
                padding: 6px 14px;
                border-radius: 999px;
                border: 2px solid #fff;
                background: transparent;
                color: #fff;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.25s ease;
            }
            .toggle-btn:hover {
                background: #fff;
                color: #5a00f0;
            }

            /* Main Layout */
            main {
                padding: 100px 20px 40px;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            h2.page-title {
                font-size: 2rem;
                margin-bottom: 20px;
                text-shadow: 0 2px 6px rgba(0,0,0,0.25);
            }

            /* Dashboard Summary */
            .dashboard-summary {
                background: #fff;
                color: #333;
                padding: 20px;
                border-radius: 12px;
                width: 90%;
                margin: 20px auto;
                text-align: center;
            }
            .dashboard-summary p {
                font-size: 18px;
                margin: 10px 0;
            }
            .dashboard-summary a {
                color: #f39c12;
                font-weight: bold;
                text-decoration: none;
            }
            .dashboard-summary a:hover {
                text-decoration: underline;
            }

            /* Card */
            .card {
                background: rgba(255,255,255,0.12);
                backdrop-filter: blur(10px);
                border-radius: 16px;
                padding: 20px;
                margin-bottom: 24px;
                box-shadow: 0 10px 26px rgba(0,0,0,0.2);
            }

            /* Search Filters */
            form input, form select {
                padding: 8px 12px;
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,0.35);
                background: rgba(255,255,255,0.18);
                color: #fff;
                outline: none;
                transition: border-color 0.2s ease;
            }
            form input:focus, form select:focus {
                border-color: #f39c12;
            }
            /* Dropdown category text color */
            form select {
                color: #000; /* black text */
                background: rgba(255, 255, 255, 0.9); /* light background for visibility */
                border: 1px solid rgba(0,0,0,0.2);
                padding: 8px 12px;
                border-radius: 8px;
                outline: none;
            }

            /* Optional: placeholder color for dropdown (for first 'All Categories' option) */
            form select option {
                color: #333;
            }
            /* Table */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            thead {
                background: rgba(0,0,0,0.4);
                color: #fff;
            }
            th, td {
                padding: 12px;
                border: 1px solid rgba(255,255,255,0.2);
                text-align: center;
            }
            tbody tr:hover {
                background: rgba(255,255,255,0.08);
                cursor: pointer;
            }
            .badge {
                padding: 6px 12px;
                border-radius: 8px;
                font-weight: 700;
            }
            .badge.in {
                background: #28a745;
            }
            .badge.out {
                background: #dc3545;
            }

            /* Totals Grid */
            .totals {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
            }
            .totals .box {
                flex: 1;
                min-width: 220px;
                padding: 20px;
                border-radius: 16px;
                text-align: center;
                font-weight: 700;
            }
            .totals .green {
                background: #28a745;
            }
            .totals .red {
                background: #dc3545;
            }
            .totals .light {
                background: rgba(255,255,255,0.85);
                color: #000;
            }

            /* Dark Mode */
            body.dark {
                background: linear-gradient(135deg,#0d0d0d,#1a1a1a);
                color: #eee;
            }
            body.dark header {
                background: rgba(255,255,255,0.05);
            }
            body.dark .card {
                background: rgba(255,255,255,0.08);
            }
            body.dark #searchInput {
                background: rgba(255,255,255,0.12);
                border-color: rgba(255,255,255,0.25);
            }
            body.dark .light {
                background: rgba(255,255,255,0.15);
                color: #eee;
            }

        </style>
    </head>
    <body>
        <header>
            <h1>Expense Manager</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="budget.php">Budget</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="report.php">Report</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
            <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
        </header>

        <main>
            <div class="container">
                <h2 class="page-title">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</h2>
                <div class="dashboard-summary">
                    <p>You Owe: ₹<?= number_format($total_owe, 2) ?></p>
                    <p>Owed to You: ₹<?= number_format($total_owed, 2) ?></p>
                    <a href="activity.php">View Activity →</a>
                </div>
                <div class="card">
                    <div style="display:flex; flex-wrap:wrap; gap:14px; margin-bottom:16px;">
                        <a href="add_income.php" class="btn success">+ Cash In</a>
                        <a href="add_expense.php" class="btn danger">- Cash Out</a>

                        <form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                            <input type="text" name="search" id="searchInput" placeholder="Search records..." value="<?= htmlspecialchars($search) ?>">

                            From :<input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                            To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">

                            <select name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" 
                                            <?= ($_GET['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="number" name="min_amount" placeholder="Min ₹" value="<?= htmlspecialchars($min_amount) ?>" style="width:90px;">
                            <input type="number" name="max_amount" placeholder="Max ₹" value="<?= htmlspecialchars($max_amount) ?>" style="width:90px;">

                            <button type="submit" class="btn success">Apply</button>
                            <a href="dashboard.php" class="btn danger">Reset</a>
                        </form>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $rec): ?>
                                <tr onclick="window.location = '<?= $rec['source'] === 'expense' ? "edit_expense.php?id={$rec['id']}" : "edit_income.php?id={$rec['id']}" ?>'">
                                    <td>₹<?= number_format($rec['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($rec['category']) ?></td>
                                    <td><?= $rec['record_date'] ?></td>
                                    <td><span class="badge <?= $rec['type'] === 'IN' ? 'in' : 'out' ?>"><?= $rec['type'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals">
                        <div class="box green">
                            <h3>Total Cash In</h3>
                            ₹<?= number_format($total_income, 2) ?>
                        </div>
                        <div class="box red">
                            <h3>Total Cash Out</h3>
                            ₹<?= number_format($total_expense, 2) ?>
                        </div>
                        <div class="box light">
                            <h3>Cash in Hand</h3>
                            ₹<?= number_format($balance, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script>
            function toggleMode() {
                const dark = document.body.classList.toggle('dark');
                try {
                    localStorage.setItem('em_theme_dark', dark ? '1' : '0');
                } catch (e) {
                }
            }
            (function initTheme() {
                try {
                    if (localStorage.getItem('em_theme_dark') === '1') {
                        document.body.classList.add('dark');
                    }
                } catch (e) {
                }
            })();

            // Search filter
            document.getElementById("searchInput").addEventListener("input", function () {
                const term = this.value.toLowerCase();
                document.querySelectorAll("table tbody tr").forEach(tr => {
                    const text = tr.textContent.toLowerCase();
                    tr.style.display = text.includes(term) ? "" : "none";
                });
            });
        </script>
    </body>
</html>
