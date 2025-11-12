<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
include 'header.php';

$user_id = $_SESSION['user_id'];

// default date range
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'both';
$category = $_GET['category'] ?? '';

// validate date format
function valid_date($d) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

if (!valid_date($from_date))
    $from_date = date('Y-m-d', strtotime('-30 days'));
if (!valid_date($to_date))
    $to_date = date('Y-m-d');

// predefined categories
$predefinedCats = ['Food', 'Travel', 'Shopping', 'Bills'];

// fetch categories/sources for dropdown
$filterCats = [];
if ($type === 'income') {
    $stmt = $conn->prepare("SELECT DISTINCT source AS cat FROM incomes WHERE user_id = ? ORDER BY source");
    $stmt->execute([$user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($type === 'expense') {
    $stmt = $conn->prepare("SELECT DISTINCT category AS cat FROM expenses WHERE user_id = ? ORDER BY category");
    $stmt->execute([$user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $stmt = $conn->prepare("
        SELECT DISTINCT category AS cat FROM expenses WHERE user_id = ? 
        UNION 
        SELECT DISTINCT source AS cat FROM incomes WHERE user_id = ? 
        ORDER BY cat
    ");
    $stmt->execute([$user_id, $user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// add “Other” if needed
$customCats = array_filter($filterCats, fn($c) => !in_array($c, $predefinedCats));
if (!empty($customCats)) {
    $filterCats[] = 'Other';
}

// build queries
$sqlParts = [];
$params = [];

// Income records
if ($type === 'income' || $type === 'both') {
    $incParams = [$user_id, $from_date, $to_date];
    $sqlInc = "SELECT id, amount, source AS category, income_date AS record_date, 'IN' AS type, 'income' AS source_table 
               FROM incomes 
               WHERE user_id = ? AND income_date BETWEEN ? AND ?";

    if ($category !== '' && $category !== 'Other') {
        $sqlInc .= " AND source = ?";
        $incParams[] = $category;
    } elseif ($category === 'Other') {
        $placeholders = implode(',', array_fill(0, count($predefinedCats), '?'));
        $sqlInc .= " AND source NOT IN ($placeholders)";
        $incParams = array_merge($incParams, $predefinedCats);
    }
    $sqlParts[] = $sqlInc;
    $params = array_merge($params, $incParams);
}

// Expense records
if ($type === 'expense' || $type === 'both') {
    $expParams = [$user_id, $from_date, $to_date];
    $sqlExp = "SELECT id, amount, category, expense_date AS record_date, 'OUT' AS type, 'expense' AS source_table 
               FROM expenses 
               WHERE user_id = ? AND expense_date BETWEEN ? AND ?";

    if ($category !== '' && $category !== 'Other') {
        $sqlExp .= " AND category = ?";
        $expParams[] = $category;
    } elseif ($category === 'Other') {
        $placeholders = implode(',', array_fill(0, count($predefinedCats), '?'));
        $sqlExp .= " AND category NOT IN ($placeholders)";
        $expParams = array_merge($expParams, $predefinedCats);
    }
    $sqlParts[] = $sqlExp;
    $params = array_merge($params, $expParams);
}

// final query
$finalSql = implode(" UNION ALL ", $sqlParts) . " ORDER BY record_date DESC, id DESC";
$stmt = $conn->prepare($finalSql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// totals
$total_income = 0;
$total_expense = 0;
$monthly = [];
$byCategory = [];

foreach ($records as $r) {
    $amt = (float) $r['amount'];
    $month = date('Y-m', strtotime($r['record_date']));

    if (!isset($monthly[$month])) {
        $monthly[$month] = ['income' => 0, 'expense' => 0];
    }

    if ($r['type'] === 'IN') {
        $total_income += $amt;
        $monthly[$month]['income'] += $amt;
    } else {
        $total_expense += $amt;
        $monthly[$month]['expense'] += $amt;
    }

    // categorize properly based on source table
if ($r['source_table'] === 'income') {
    $cat = $r['category'] ?: 'Income';
    $color = 'rgba(40,167,69,0.8)'; // green for income
} else {
    $cat = $r['category'] ?: 'Expense';
    if (!in_array($cat, $predefinedCats)) {
        $cat = 'Other';
    }
    $color = 'rgba(220,53,69,0.8)'; // red for expense
}

if (!isset($byCategory[$cat])) {
    $byCategory[$cat] = 0;
}
$byCategory[$cat] += $amt;

}

$balance = $total_income - $total_expense;

ksort($monthly);
$months = array_keys($monthly);
$incomeSeries = array_column($monthly, 'income');
$expenseSeries = array_column($monthly, 'expense');

$catLabels = array_keys($byCategory);
$catValues = array_values($byCategory);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Reports - Expense Tracker</title>
        <script src="js/chart.min.js"></script>
        <script src="js/jspdf.umd.min.js"></script>
        <script src="js/html2canvas.min.js"></script>
        <style>
<?php // exactly same CSS styling as your version  ?>
            body {
                background: linear-gradient(135deg, #0f172a, #1e293b);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .card, .box, .table-container {
                background: #1e293b;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.4);
                padding: 20px;
            }
            h1, h2, h3, h4 {
                color: #f8fafc;
                font-weight: bold;
            }
            .btn-primary {
                background: #2563eb;
                border: none;
            }
            .btn-primary:hover {
                background: #1d4ed8;
            }
            .container {
                max-width: 1100px;
                margin: 20px auto;
                padding: 0 15px;
            }
            .filters {
                background:#fff;
                padding:16px;
                border-radius:8px;
                box-shadow:0 2px 6px rgba(0,0,0,0.05);
                margin-bottom:16px;
                display:flex;
                gap:12px;
                flex-wrap:wrap;
                align-items:center;
            }
            .filters label {
                font-weight:600;
                margin-right:6px;
            }
            .filters input[type="date"], .filters select {
                padding:8px 10px;
                border:1px solid #ccc;
                border-radius:6px;
            }
            .summary {
                display:flex;
                gap:12px;
                flex-wrap:wrap;
                margin:16px 0;
            }
            .card {
                flex:1;
                min-width:200px;
                background:#fff;
                padding:14px;
                border-radius:8px;
                box-shadow:0 2px 6px rgba(0,0,0,0.05);
                text-align:center;
            }
            table {
                width:100%;
                border-collapse:collapse;
                background:#fff;
            }
            th, td {
                padding:10px;
                border:1px solid #eee;
                text-align:center;
            }
            thead th {
                background:#343a40;
                color:#fff;
            }
            .table-wrap {
                overflow-x:auto;
                margin-top:12px;
            }
        </style>
    </head>
    <body>
        <main class="container">
            <h2>Reports</h2>
            <!-- Filter form -->
            <form method="GET" class="filters" id="filterForm">
                <div>
                    <label>From</label>
                    <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div>
                    <label>To</label>
                    <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div>
                    <label>Type</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="both" <?= $type === 'both' ? 'selected' : '' ?>>Both</option>
                        <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
                <div>
                    <label>Category</label>
                    <select name="category">
                        <option value="">All</option>
                        <?php foreach ($filterCats as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($category === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <button type="button" id="resetBtn" class="btn" style="background:#6c757d">Reset</button>
                </div>
            </form>

            <!-- summary cards -->
            <div class="summary">
                <div class="card"><div>Total Income</div><div style="font-size:1.4rem;color:#198754">₹<?= number_format($total_income, 2) ?></div></div>
                <div class="card"><div>Total Expense</div><div style="font-size:1.4rem;color:#dc3545">₹<?= number_format($total_expense, 2) ?></div></div>
                <div class="card"><div>Cash in Hand</div><div style="font-size:1.4rem;">₹<?= number_format($balance, 2) ?></div></div>
                <div class="card"><div>Records</div><div><?= count($records) ?></div></div>
            </div>

            <!-- charts -->
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                <div style="flex:1;min-width:320px;background:#fff;padding:12px;border-radius:8px;">
                    <canvas id="monthlyChart" height="220"></canvas>
                </div>
                <div style="width:360px;background:#fff;padding:12px;border-radius:8px;">
                    <canvas id="categoryChart" height="220"></canvas>
                </div>
            </div>

            <!-- table -->
            <div class="table-wrap">
                <table id="reportTable" style="margin-top:12px;">
                    <thead><tr><th>Amount (₹)</th><th>Category/Source</th><th>Date</th><th>Type</th><th>Table</th></tr></thead>
                    <tbody>
<?php if (!empty($records)): ?>
    <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><?= number_format($r['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['category'] ?: 'Other') ?></td>
                                    <td><?= htmlspecialchars($r['record_date']) ?></td>
                                    <td><?= $r['type'] ?></td>
                                    <td><?= $r['source_table'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No records found</td></tr>
<?php endif; ?>
                    </tbody>
                </table>
                <center style="color:white;"><a href="dashboard.php">Go To Dashboard</a></center>
            </div>
        </main>

        <script>
            document.getElementById('resetBtn').onclick = () => window.location = 'report.php';
            const months =<?= json_encode($months) ?>, inc =<?= json_encode($incomeSeries) ?>, exp =<?= json_encode($expenseSeries) ?>;
            new Chart(document.getElementById('monthlyChart'), {type: 'bar', data: {labels: months, datasets: [{label: 'Income', data: inc, backgroundColor: 'rgba(40,167,69,0.8)'}, {label: 'Expense', data: exp, backgroundColor: 'rgba(220,53,69,0.8)'}]}, options: {responsive: true, scales: {y: {beginAtZero: true}}}});
            const catLabels = <?= json_encode($catLabels) ?>;
const catValues = <?= json_encode($catValues) ?>;
const colors = catLabels.map(label => 
    label.toLowerCase().includes('income') ? 'rgba(40,167,69,0.8)' : 
    (label.toLowerCase().includes('expense') || label.toLowerCase().includes('other')) ? 'rgba(220,53,69,0.8)' :
    'rgba(54,162,235,0.8)'
);

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catValues,
            backgroundColor: colors
        }]
    },
    options: {plugins: {legend: {position: 'bottom'}}}
});
        </script>
<?php include 'footer.php'; ?>
    </body>
</html>
