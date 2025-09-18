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

// ----------------------
// Handle Delete
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delete = $conn->prepare("DELETE FROM expenses WHERE id = :id AND user_id = :user_id");
    $delete->execute([':id' => $id, ':user_id' => $user_id]);
    header("Location: dashboard.php?msg=deleted");
    exit;
}

// ----------------------
// Handle Update
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $amount = $_POST['amount'] ?? 0;
    $category = $_POST['category'] ?? '';
    $expense_date = $_POST['expense_date'] ?? '';
    $note = $_POST['note'] ?? '';

    $update = $conn->prepare("
        UPDATE expenses 
        SET amount = :amount, category = :category, expense_date = :expense_date, note = :note 
        WHERE id = :id AND user_id = :user_id
    ");
    $success = $update->execute([
        ':amount' => $amount,
        ':category' => $category,
        ':expense_date' => $expense_date,
        ':note' => $note,
        ':id' => $id,
        ':user_id' => $user_id
    ]);

    $msg = $success ? "✅ Expense updated successfully!" : "❌ Update failed.";
}

// ----------------------
// Fetch Existing Record
// ----------------------
$stmt = $conn->prepare("SELECT amount, category, expense_date, note FROM expenses WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $id, ':user_id' => $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Record not found or access denied.");
}

$amount = $row['amount'];
$category = $row['category'];
$expense_date = $row['expense_date'];
$note = $row['note'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Expense - Expense Tracker</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #1a1a1a, #0d0d0d);
      color: #fff;
    }
    header {
      background: linear-gradient(90deg, #0f0f0f, #1c1c1c);
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: white;
    }
    header h1 {
      margin: 0;
      font-size: 24px;
      font-weight: bold;
    }
    nav ul {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      gap: 20px;
    }
    nav ul li {
      display: inline;
    }
    nav ul li a {
      color: white;
      text-decoration: none;
      font-weight: bold;
    }
    nav ul li a:hover {
      text-decoration: underline;
    }
    .toggle-btn {
      background: transparent;
      border: 1px solid #fff;
      color: #fff;
      padding: 6px 12px;
      border-radius: 5px;
      cursor: pointer;
    }
    .form-container {
      max-width: 600px;
      margin: 50px auto;
      background-color: #fff;
      color: #000;
      border: 1px solid #ccc;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .form-header {
      background-color: #dc3545;
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
    .form-body input[type="date"],
    .form-body textarea,
    .form-body select {
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
    .btn-danger {
      background-color: #dc3545;
      color: white;
    }
    .btn-danger:hover {
      background-color: #c82333;
    }
    .btn-outline-danger {
      background-color: white;
      color: #dc3545;
      border: 1px solid #dc3545;
    }
    .btn-outline-danger:hover {
      background-color: #f8d7da;
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
      color: black;
    }
    .form-footer {
      padding: 10px;
      text-align: center;
      background-color: #f8f9fa;
    }
  </style>
  <script>
    function toggleMode() {
      document.body.classList.toggle("dark-mode");
    }
  </script>
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
      <h4>Edit Expense Entry</h4>
    </div>
    <div class="form-body">
      <form method="POST" onsubmit="return confirm('Are you sure you want to update this expense entry?');">
        <label>Amount</label>
        <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($amount) ?>" required />

        <label>Category</label>
        <select name="category" required>
          <?php foreach(['Food','Travel','Shopping','Bills','Other'] as $cat): ?>
            <option value="<?= $cat ?>" <?= $category == $cat ? 'selected' : '' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>

        <label>Date</label>
        <input type="date" name="expense_date" value="<?= htmlspecialchars($expense_date) ?>" required />

        <label>Note</label>
        <textarea name="note" rows="3"><?= htmlspecialchars($note) ?></textarea>

        <button type="submit" name="update" class="btn btn-danger">Update Expense</button>
        <button type="submit" name="delete" class="btn btn-outline-danger">Delete Expense</button>
      </form>

      <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

    </div>
  </div>
</main>

</body>
</html>
