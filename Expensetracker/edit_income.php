<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'config.php';

$user_id = $_SESSION['user_id'];
$id = intval($_GET['id'] ?? 0);
$msg = "";

// Fetch existing record
$stmt = $conn->prepare("SELECT amount, source, income_date FROM incomes WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $id, ':user_id' => $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found or access denied.");
}

$amount = $row['amount'];
$source = $row['source'];
$income_date = $row['income_date'];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $amount = $_POST['amount'] ?? 0;
    $source = $_POST['source'] ?? '';
    $income_date = $_POST['income_date'] ?? '';

    $update = $conn->prepare("UPDATE incomes SET amount = :amount, source = :source, income_date = :income_date WHERE id = :id AND user_id = :user_id");
    $success = $update->execute([
        ':amount' => $amount,
        ':source' => $source,
        ':income_date' => $income_date,
        ':id' => $id,
        ':user_id' => $user_id
    ]);

    $msg = $success ? "✅ Income updated successfully!" : "❌ Update failed.";
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delete = $conn->prepare("DELETE FROM incomes WHERE id = :id AND user_id = :user_id");
    $delete->execute([':id' => $id, ':user_id' => $user_id]);
    header("Location: dashboard.php?msg=deleted");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Income - Expense Tracker</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #0f0f0f, #1a1a1a, #333);
      color: #fff;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(90deg, #0d0d0d, #1a1a1a, #262626);
      padding: 15px 30px;
      color: #fff;
    }

    header h1 {
      margin: 0;
      font-size: 22px;
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 20px;
      margin: 0;
      padding: 0;
    }

    nav ul li a {
      color: #fff;
      text-decoration: none;
      font-weight: 500;
    }

    nav ul li a:hover {
      color: #00bcd4;
    }

    .toggle-btn {
      background: #444;
      color: #fff;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
    }

    .toggle-btn:hover {
      background: #666;
    }

    .form-container {
      max-width: 600px;
      margin: 50px auto;
      background-color: #fff;
      color: #000;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      overflow: hidden;
    }

    .form-header {
      background-color: #28a745;
      color: white;
      padding: 15px;
      text-align: center;
    }

    .form-body {
      padding: 25px;
    }

    .form-body label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
    }

    .form-body input[type="text"],
    .form-body input[type="number"],
    .form-body input[type="date"] {
      width: 100%;
      padding: 10px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 15px;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 12px;
      font-size: 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      margin-bottom: 10px;
    }

    .btn-success {
      background-color: #28a745;
      color: white;
    }

    .btn-success:hover {
      background-color: #218838;
    }

    .btn-danger {
      background-color: #dc3545;
      color: white;
    }

    .btn-danger:hover {
      background-color: #c82333;
    }

    .btn-link {
      text-align: center;
      display: inline-block;
      margin-top: 10px;
      color: #007bff;
      text-decoration: none;
    }

    .btn-link:hover {
      text-decoration: underline;
    }

    .alert {
      padding: 12px;
      background-color: #e2e3e5;
      border: 1px solid #ccc;
      border-radius: 6px;
      margin-top: 15px;
      text-align: center;
      color: #000;
    }
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
    <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
</header>

<main>
  <div class="form-container">
    <div class="form-header">
      <h4>Edit Income Entry</h4>
    </div>
    <div class="form-body">
      <form method="POST" onsubmit="return confirm('Are you sure you want to update this income entry?');">
        <label>Amount</label>
        <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($amount) ?>" required />

        <label>Source</label>
        <input type="text" name="source" value="<?= htmlspecialchars($source) ?>" required />

        <label>Date</label>
        <input type="date" name="income_date" value="<?= htmlspecialchars($income_date) ?>" required />

        <button type="submit" name="update" class="btn btn-success">Update Income</button>
        <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('⚠ Are you sure you want to delete this entry?');">Delete Income</button>
      </form>

      <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="text-center">
        <a href="dashboard.php" class="btn-link">⬅ Back to Dashboard List</a>
      </div>
    </div>
  </div>
</main>

<script>
function toggleMode() {
    document.body.classList.toggle("dark-mode");
}
</script>

</body>
</html>
