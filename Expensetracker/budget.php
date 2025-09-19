<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$user_id = $_SESSION['user_id'];

// Fetch month from GET or default current month
$month = $_GET['month'] ?? date('Y-m');

// Handle budget save
if (isset($_POST['save_budget'])) {
    $cat = $_POST['category'];
    $amt = $_POST['budget_amount'];
    $m = $_POST['month'];

    $stmt = $conn->prepare("SELECT id FROM budgets WHERE user_id=? AND category=? AND month=?");
    $stmt->execute([$user_id, $cat, $m]);

    if ($stmt->rowCount() > 0) {
        $stmt2 = $conn->prepare("UPDATE budgets SET budget_amount=? WHERE user_id=? AND category=? AND month=?");
        $stmt2->execute([$amt, $user_id, $cat, $m]);
    } else {
        $stmt2 = $conn->prepare("INSERT INTO budgets (user_id, category, budget_amount, month) VALUES (?,?,?,?)");
        $stmt2->execute([$user_id, $cat, $amt, $m]);
    }
    header("Location: budget.php?month=" . htmlspecialchars($m));
    exit;
}

// Handle budget delete
if (isset($_POST['delete_budget'])) {
    $cat = $_POST['category'];
    $m = $_POST['month'];

    $stmt = $conn->prepare("DELETE FROM budgets WHERE user_id=? AND category=? AND month=?");
    $stmt->execute([$user_id, $cat, $m]);
    header("Location: budget.php?month=" . htmlspecialchars($m));
    exit;
}

// Fetch all categories dynamically (union of budgets + expenses)
$stmt = $conn->prepare("
    SELECT DISTINCT category FROM (
        SELECT category FROM expenses WHERE user_id=? 
        UNION 
        SELECT category FROM budgets WHERE user_id=?
    ) as allcats
");
$stmt->execute([$user_id, $user_id]);
$predefinedCats = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch budgets for this month
$stmt = $conn->prepare("SELECT category, budget_amount FROM budgets WHERE user_id=? AND month=?");
$stmt->execute([$user_id, $month]);
$budgets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Auto-copy previous month budgets if empty
if (empty($budgets)) {
    $prevMonth = date('Y-m', strtotime($month . " -1 month"));
    $stmt = $conn->prepare("SELECT category, budget_amount FROM budgets WHERE user_id=? AND month=?");
    $stmt->execute([$user_id, $prevMonth]);
    $prevBudgets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($prevBudgets)) {
        $insert = $conn->prepare("INSERT INTO budgets (user_id, category, budget_amount, month) VALUES (?,?,?,?)");
        foreach ($prevBudgets as $cat => $amt) {
            $insert->execute([$user_id, $cat, $amt, $month]);
        }
        $budgets = $prevBudgets;
        $autoCopied = true;
    }
}

// Fetch expenses for this month
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ? GROUP BY category");
$stmt->execute([$user_id, $startDate, $endDate]);
$rawExpenses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate expenses including unknown categories as "Other"
$expenses = [];
$otherSpent = 0;
foreach ($rawExpenses as $cat => $amt) {
    if (isset($budgets[$cat])) {
        $expenses[$cat] = $amt;
    } else {
        $otherSpent += $amt;
    }
}
if (isset($budgets['Other'])) {
    $expenses['Other'] = $otherSpent;
} elseif ($otherSpent > 0) {
    $budgets['Other'] = 0;
    $expenses['Other'] = $otherSpent;
}
foreach ($budgets as $cat => $amt) {
    if (!isset($expenses[$cat])) {
        $expenses[$cat] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Budget | Expense Manager</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: #f4f6f9;
                color: #333;
            }
            nav {
                background: #2c5364;
                padding: 15px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            nav a {
                color: #fff;
                text-decoration: none;
                margin-left: 20px;
                font-weight: 500;
            }
            nav a:hover {
                text-decoration: underline;
            }

            .container {
                width: 90%;
                max-width: 1200px;
                margin: 30px auto;
            }
            .alert {
                padding: 12px;
                background: #d1f7d6;
                border-left: 5px solid #28a745;
                margin-bottom: 20px;
                border-radius: 6px;
            }

            .card {
                background: #fff;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 25px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .card h4 {
                margin-bottom: 15px;
            }

            form {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                align-items: flex-end;
            }
            label {
                display: block;
                margin-bottom: 6px;
                font-weight: bold;
                font-size: 14px;
            }
            input, select, button {
                padding: 8px 10px;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 14px;
                width: 100%;
            }
            button {
                background: #007bff;
                color: #fff;
                border: none;
                cursor: pointer;
                transition: 0.3s;
            }
            button:hover {
                background: #0056b3;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: center;
            }
            th {
                background: #2c5364;
                color: #fff;
            }
            tr:nth-child(even) {
                background: #f9f9f9;
            }

            .progress {
                width: 100%;
                background: #eee;
                border-radius: 8px;
                overflow: hidden;
                height: 18px;
            }
            .progress-bar {
                height: 18px;
                text-align: center;
                color: #fff;
                font-size: 12px;
                line-height: 18px;
                min-width: 30px;
            }
            .bg-success {
                background: #28a745;
            }
            .bg-warning {
                background: #ffc107;
                color:#000;
            }
            .bg-danger {
                background: #dc3545;
            }
            .btn-sm {
                padding: 5px 8px;
                font-size: 13px;
                border-radius: 4px;
            }
            .form-inline {
                display: flex;
                gap: 5px;
                align-items: center;
                justify-content: center;
            }
            .form-inline input {
                max-width: 100px;
            }
        </style>
    </head>
    <body>

        <nav>
            <div class="brand"><a href="#">Expense Manager</a></div>
            <div class="menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="report.php">Reports</a>
                <a href="profile.php">Profile</a>
            </div>
        </nav>

        <div class="container">
            <?php if (!empty($autoCopied)): ?>
                <div class="alert">Budgets auto-copied from <?= htmlspecialchars(date('F Y', strtotime($prevMonth))) ?> ✅</div>
            <?php endif; ?>

            <div class="card">
                <h4>Add / Update Budget</h4>
                <form method="POST">
                    <div>
                        <label>Month</label>
                        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" required>
                    </div><br>
                    <div>
                        <label>Category</label>
                        <input type="text" name="category" list="categories" required>
                        <datalist id="categories">
                            <?php foreach ($predefinedCats as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div><br>
                    <div>
                        <label>Budget Amount</label>
                        <input type="number" step="0.01" name="budget_amount" required>
                    </div><br>
                    <div>
                        <button type="submit" name="save_budget">Save Budget</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h4>Budget Overview (<?= htmlspecialchars($month) ?>)</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Budget</th>
                            <th>Expense</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($predefinedCats as $cat):
                            $budgetAmt = $budgets[$cat] ?? 0;
                            $spent = $expenses[$cat] ?? 0;
                            $percent = ($budgetAmt > 0) ? round(($spent / $budgetAmt) * 100) : 0;
                            $color = $percent > 100 ? 'bg-danger' : ($percent > 75 ? 'bg-warning' : 'bg-success');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($cat) ?></td>
                                <td>
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                                        <input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>">
                                        <input type="number" step="0.01" name="budget_amount" value="<?= htmlspecialchars($budgetAmt) ?>">
                                        <button type="submit" name="save_budget" class="btn-sm">✔</button>
                                    </form>
                                </td>
                                <td>₹<?= number_format($spent, 2) ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar <?= $color ?>" style="width: <?= min($percent, 100) ?>%"><?= $percent ?>%</div>
                                    </div>
                                </td>
                            </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
                <canvas id="budgetChart" height="120"></canvas>
            </div>
        </div>

        <script src="js/chart.min.js"></script>
        <script>
            const ctx = document.getElementById('budgetChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($predefinedCats) ?>,
                    datasets: [
                        {label: 'Budget', data: <?= json_encode(array_map(fn($c) => $budgets[$c] ?? 0, $predefinedCats)) ?>, backgroundColor: 'rgba(0,123,255,0.7)'},
                        {label: 'Expense', data: <?= json_encode(array_map(fn($c) => $expenses[$c] ?? 0, $predefinedCats)) ?>, backgroundColor: 'rgba(220,53,69,0.7)'}
                    ]
                },
                options: {responsive: true, plugins: {legend: {position: 'top'}}}
            });
        </script>

    </body>
</html>
