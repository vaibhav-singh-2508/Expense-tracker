<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
include 'header.php';

$user_id = $_SESSION['user_id'];

// default date range: last 30 days
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'both'; // 'income','expense','both'
$category = $_GET['category'] ?? ''; // optional category filter

// sanitize and validate dates
function valid_date($d) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

if (!valid_date($from_date))
    $from_date = date('Y-m-d', strtotime('-30 days'));
if (!valid_date($to_date))
    $to_date = date('Y-m-d');

// Predefined categories
$predefinedCats = ['Food', 'Travel', 'Shopping', 'Bills'];

// Fetch distinct categories/sources based on type
$filterCats = [];
if ($type === 'income') {
    $stmt = $conn->prepare("SELECT DISTINCT source AS cat FROM incomes WHERE user_id = ? ORDER BY source");
    $stmt->execute([$user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($type === 'expense') {
    $stmt = $conn->prepare("SELECT DISTINCT category AS cat FROM expenses WHERE user_id = ? ORDER BY category");
    $stmt->execute([$user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else { // both
    $stmt = $conn->prepare("
        SELECT DISTINCT category AS cat FROM expenses WHERE user_id = ? 
        UNION 
        SELECT DISTINCT source AS cat FROM incomes WHERE user_id = ? 
        ORDER BY cat
    ");
    $stmt->execute([$user_id, $user_id]);
    $filterCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Add "Other" if there are any custom categories not in predefined
$customCats = array_filter($filterCats, fn($c) => !in_array($c, $predefinedCats));
if (!empty($customCats)) {
    $filterCats[] = 'Other';
}


// Build SQL queries for incomes and expenses
$sqlParts = [];
$params = [];

// Income
if ($type === 'income' || $type === 'both') {
    $incomeParams = [$user_id, $from_date, $to_date];
    $sqlInc = "SELECT id, amount, source AS category, income_date AS record_date, 'IN' AS type, 'income' AS source_table
               FROM incomes
               WHERE user_id = ? AND income_date BETWEEN ? AND ?";

    if ($category !== '' && $category !== 'Other') {
        $sqlInc .= " AND source = ?";
        $incomeParams[] = $category;
    } elseif ($category === 'Other') {
        $placeholders = implode(',', array_fill(0, count($predefinedCats), '?'));
        $sqlInc .= " AND source NOT IN ($placeholders)";
        $incomeParams = array_merge($incomeParams, [$user_id, $from_date, $to_date], $predefinedCats);
    }

    $sqlParts[] = $sqlInc;
    $params = array_merge($params, $incomeParams);
}

// Expense
if ($type === 'expense' || $type === 'both') {
    $expenseParams = [$user_id, $from_date, $to_date];
    $sqlExp = "SELECT id, amount, category, expense_date AS record_date, 'OUT' AS type, 'expense' AS source_table
               FROM expenses
               WHERE user_id = ? AND expense_date BETWEEN ? AND ?";

    if ($category !== '' && $category !== 'Other') {
        $sqlExp .= " AND category = ?";
        $expenseParams[] = $category;
    } elseif ($category === 'Other') {
        $placeholders = implode(',', array_fill(0, count($predefinedCats), '?'));
        $sqlExp .= " AND category NOT IN ($placeholders)";
        $expenseParams = array_merge($expenseParams, [$user_id, $from_date, $to_date], $predefinedCats);
    }

    $sqlParts[] = $sqlExp;
    $params = array_merge($params, $expenseParams);
}

// Final query
$finalSql = implode(" UNION ALL ", $sqlParts) . " ORDER BY record_date DESC, id DESC";
$stmt = $conn->prepare($finalSql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals, monthly data, category breakdown
$total_income = 0;
$total_expense = 0;
$monthly = [];
$byCategory = [];

foreach ($records as $r) {
    $amt = (float) $r['amount'];
    $month = date('Y-m', strtotime($r['record_date']));
    if (!isset($monthly[$month]))
        $monthly[$month] = ['income' => 0, 'expense' => 0];

    if ($r['type'] === 'IN') {
        $total_income += $amt;
        $monthly[$month]['income'] += $amt;
    } else {
        $total_expense += $amt;
        $monthly[$month]['expense'] += $amt;
    }

    // Category chart for both income and expense
    $cat = $r['category'];
    if (!in_array($cat, $predefinedCats))
        $cat = 'Other';
    if (!isset($byCategory[$cat]))
        $byCategory[$cat] = 0;
    $byCategory[$cat] += $amt;
}

$balance = $total_income - $total_expense;

// Prepare chart data
ksort($monthly);
$months = array_keys($monthly);
$incomeSeries = array_map(fn($m) => $m['income'], $monthly);
$expenseSeries = array_map(fn($m) => $m['expense'], $monthly);

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
            body {
                background: linear-gradient(135deg, #0f172a, #1e293b);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            /* Card / box styling */
            .card, .box, .table-container {
                background: #1e293b;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.4);
                padding: 20px;
            }

            /* Headings */
            h1, h2, h3, h4 {
                color: #f8fafc;
                font-weight: bold;
            }

            /* Buttons */
            .btn-primary {
                background: #2563eb;
                border: none;
            }
            .btn-primary:hover {
                background: #1d4ed8;
            }
            .btn-secondary {
                background: #334155;
                border: none;
                color: #f1f5f9;
            }
            .btn-secondary:hover {
                background: #475569;
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
            .btn {
                padding:10px 14px;
                border-radius:6px;
                text-decoration:none;
                color:#fff;
                display:inline-block;
                cursor:pointer;
                border:none;
            }
            .btn-primary {
                background:#007bff;
            }
            .btn-success {
                background:#28a745;
            }
            .btn-danger {
                background:#dc3545;
            }
            .summary {
                display:flex;
                gap:12px;
                flex-wrap:wrap;
                margin: 16px 0;
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
                margin-top: 12px;
            }
            .actions {
                display:flex;
                gap:8px;
                align-items:center;
            }
            .export-btn {
                padding:8px 12px;
                border-radius:6px;
                cursor:pointer;
                border:none;
            }
            .small-muted {
                color:#666;
                font-size:0.9rem;
            }
            @media (max-width:700px){
                .filters{
                    flex-direction:column;
                    align-items:stretch;
                }
                .summary{
                    flex-direction:column;
                }
            }
        </style>
    </head>
    <body>
        <main class="container">
            <h2>Reports</h2>

            <!-- Filter Form -->
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
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($category === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                            </option>
<?php endforeach; ?>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <button type="button" id="resetBtn" class="btn" style="background:#6c757d">Reset</button>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="summary">
                <div class="card">
                    <div class="small-muted">Total Income</div>
                    <div style="font-size:1.4rem; font-weight:700; color:#198754">₹<?= number_format($total_income, 2) ?></div>
                </div>
                <div class="card">
                    <div class="small-muted">Total Expense</div>
                    <div style="font-size:1.4rem; font-weight:700; color:#dc3545">₹<?= number_format($total_expense, 2) ?></div>
                </div>
                <div class="card">
                    <div class="small-muted">Cash in Hand</div>
                    <div style="font-size:1.4rem; font-weight:700;">₹<?= number_format($balance, 2) ?></div>
                </div>
                <div class="card">
                    <div class="small-muted">Records</div>
                    <div style="font-size:1.1rem; font-weight:700;"><?= count($records) ?></div>
                </div>
            </div>

            <!-- Charts -->
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                <div style="flex:1; min-width:320px; background:#fff; padding:12px; border-radius:8px;">
                    <canvas id="monthlyChart" height="220"></canvas>
                </div>

                <div style="width:360px; background:#fff; padding:12px; border-radius:8px;">
                    <canvas id="categoryChart" height="220"></canvas>
                </div>
            </div>

            <!-- Export Buttons -->
            <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div class="small-muted">Export filtered results:</div>
                <div>
                    <button class="export-btn btn-success" id="exportCsv">Export CSV</button>
                    <button class="export-btn btn-primary" id="exportPdf">Export PDF</button>
                </div>
            </div>

            <!-- Records Table -->
            <div class="table-wrap">
                <table id="reportTable" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th>Amount (₹)</th>
                            <th>Category / Source</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Source Table</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($records)): ?>
                            <?php foreach ($records as $r): ?>
                                <?php
                                $displayCat = $r['category'];
                                if (!in_array($displayCat, $predefinedCats))
                                    $displayCat = 'Other';
                                ?>
                                <tr>
                                    <td style="text-align:right;"><?= number_format($r['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($displayCat) ?></td>
                                    <td><?= htmlspecialchars($r['record_date']) ?></td>
                                    <td><?= $r['type'] ?></td>
                                    <td><?= htmlspecialchars($r['source_table']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:18px;">No records found for selected range.</td></tr>
<?php endif; ?>
                    </tbody>
                </table>
                <center style="color: white;"><a href="dashboard.php">Go To Dashboard</a></center>
            </div>
        </main>

        <script>
            document.querySelector('select[name="type"]').addEventListener('change', function () {
                document.querySelector('select[name="category"]').value = '';
            });
            // Reset button
            document.getElementById('resetBtn').addEventListener('click', function () {
                window.location = 'report.php';
            });

            // Chart data from PHP
            const months = <?= json_encode($months) ?>;
            const incomeSeries = <?= json_encode($incomeSeries) ?>;
            const expenseSeries = <?= json_encode($expenseSeries) ?>;
            const catLabels = <?= json_encode($catLabels) ?>;
            const catValues = <?= json_encode($catValues) ?>;

            // Monthly Chart
            const ctxM = document.getElementById('monthlyChart').getContext('2d');
            new Chart(ctxM, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {label: 'Income', data: incomeSeries, backgroundColor: 'rgba(40,167,69,0.8)'},
                        {label: 'Expense', data: expenseSeries, backgroundColor: 'rgba(220,53,69,0.8)'}
                    ]
                },
                options: {responsive: true, scales: {y: {beginAtZero: true}}}
            });

            // Category Chart
            const ctxC = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctxC, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{data: catValues, backgroundColor: catLabels.map((_, i) => `hsl(${(i * 50) % 360} 70% 45%)`)}]
                },
                options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
            });

            // Export CSV
            function tableToCSV(filename = 'report.csv') {
                const rows = Array.from(document.querySelectorAll('#reportTable tr'));
                const csv = rows.map(row => {
                    const cols = Array.from(row.querySelectorAll('th, td')).map(td => `"${td.innerText.replace(/"/g, '""').trim()}"`);
                    return cols.join(',');
                }).join('\n');
                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
            }
            document.getElementById('exportCsv').addEventListener('click', function () {
                tableToCSV('expense_report_<?= date('Ymd_His') ?>.csv');
            });

            // Export PDF
            document.getElementById('exportPdf').addEventListener('click', async function () {
                const reportEl = document.querySelector('main.container');
                const originalWidth = reportEl.style.width;
                reportEl.style.width = '1100px';
                const canvas = await html2canvas(reportEl, {scale: 2});
                const imgData = canvas.toDataURL('image/png');
                const {jsPDF} = window.jspdf;
                const pdf = new jsPDF('p', 'pt', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const imgWidth = pageWidth - 40;
                const imgHeight = canvas.height * (imgWidth / canvas.width);
                let position = 20;
                if (imgHeight <= pageHeight - 40) {
                    pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
                } else {
                    let remainingHeight = imgHeight;
                    let sourceY = 0;
                    const ratio = canvas.width / imgWidth;
                    while (remainingHeight > 0) {
                        const pageCanvas = document.createElement('canvas');
                        pageCanvas.width = canvas.width;
                        pageCanvas.height = Math.min(canvas.height - sourceY, Math.floor((pageHeight - 40) * ratio));
                        const ctx = pageCanvas.getContext('2d');
                        ctx.drawImage(canvas, 0, sourceY, pageCanvas.width, pageCanvas.height, 0, 0, pageCanvas.width, pageCanvas.height);
                        const pageData = pageCanvas.toDataURL('image/png');
                        pdf.addImage(pageData, 'PNG', 20, 20, imgWidth, pageCanvas.height * (imgWidth / pageCanvas.width));
                        remainingHeight -= pageCanvas.height;
                        sourceY += pageCanvas.height;
                        if (remainingHeight > 0)
                            pdf.addPage();
                    }
                }
                pdf.save('expense_report_<?= date('Ymd_His') ?>.pdf');
                reportEl.style.width = originalWidth;
            });
        </script>

<?php include 'footer.php'; ?>
    </body>
</html>
