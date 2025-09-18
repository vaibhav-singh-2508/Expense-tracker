<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id      = $_SESSION['user_id'];
    $amount       = $_POST['amount'];
    $category     = $_POST['category'];
    $expense_date = $_POST['expense_date'];
    $note         = $_POST['note'];

    // If user selected custom category
    if ($category === 'custom' && !empty($_POST['custom_category'])) {
        $category = $_POST['custom_category'];
    }

    try {
        // Insert main expense
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, expense_date, note) 
                                VALUES (:user_id, :amount, :category, :expense_date, :note)");
        $stmt->execute([
            ':user_id'      => $user_id,
            ':amount'       => $amount,
            ':category'     => $category,
            ':expense_date' => $expense_date,
            ':note'         => $note
        ]);

        $expense_id = $conn->lastInsertId();

        // Handle shared expense
        if(isset($_POST['is_shared']) && !empty($_POST['shared_users'])){
            $shared_users = $_POST['shared_users']; // array of user IDs
            $total_amount = $amount;
            $share_amount = $total_amount / (count($shared_users) + 1); // include yourself

            // Insert shares for each selected user
            foreach($shared_users as $uid){
                $stmt = $conn->prepare("INSERT INTO shared_expenses (expense_id, user_id, share_amount) VALUES (?, ?, ?)");
                $stmt->execute([$expense_id, $uid, $share_amount]);
            }

            // Insert your own share
            $stmt = $conn->prepare("INSERT INTO shared_expenses (expense_id, user_id, share_amount) VALUES (?, ?, ?)");
            $stmt->execute([$expense_id, $user_id, $share_amount]);
        }

        $msg = "✅ Expense added successfully!";
    } catch (PDOException $e) {
        $msg = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Expense</title>
    <style>
        /* Your existing CSS here */
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); margin:0; padding:0; color:#fff; }
        header { background: rgba(0,0,0,0.85); color: #fff; padding:15px 40px; display:flex; justify-content: space-between; align-items:center; }
        header h1 { margin:0; font-size:26px; }
        nav ul { list-style:none; display:flex; gap:20px; margin:0; padding:0; }
        nav ul li a { text-decoration:none; color:#fff; font-weight:500; transition:0.3s; }
        nav ul li a:hover { color:#f39c12; }
        .toggle-btn { background:#f39c12; border:none; color:#fff; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:14px; transition:0.3s; }
        .toggle-btn:hover { background:#d35400; }
        h2 { text-align:center; margin-top:30px; font-weight:600; }
        form { background-color:#fff; color:#333; max-width:500px; margin:30px auto; padding:30px; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        input[type="number"], input[type="date"], select, textarea, input[type="text"] { width:100%; padding:14px 16px; margin-bottom:20px; border:1px solid #ccc; border-radius:12px; font-size:16px; }
        input[type="submit"] { width:100%; background-color:#f39c12; color:#fff; border:none; padding:14px; font-size:16px; font-weight:bold; cursor:pointer; border-radius:12px; transition:0.3s; }
        input[type="submit"]:hover { background-color:#d35400; }
        .msg { text-align:center; font-weight:500; margin-top:10px; color:green; }
        .msg.error { color:red; }
        .back { text-align:center; margin-top:20px; }
        .back a { text-decoration:none; color:#f39c12; font-weight:500; }
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
    <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
</header>

<h2>Add Expense</h2>

<form method="POST">
    <input type="number" step="0.01" name="amount" placeholder="Amount" required>
    <select name="category" id="category" required>
        <option value="">Select Category</option>
        <option value="Food">Food</option>
        <option value="Travel">Travel</option>
        <option value="Shopping">Shopping</option>
        <option value="Bills">Bills</option>
        <option value="custom">Other</option>
    </select>
    <input type="text" name="custom_category" id="custom_category" placeholder="Enter your category" style="display:none;">
    <input type="date" name="expense_date" required>
    <textarea name="note" rows="3" placeholder="Note (optional)"></textarea>

    <!-- Shared Expense -->
    <div class="form-group">
        <label><input type="checkbox" name="is_shared" id="is_shared"> Shared Expense</label>
    </div>

    <div class="form-group" id="shared_users_div" style="display:none;">
        <label>Share With:</label>
        <select name="shared_users[]" multiple class="form-control">
            <?php
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE id != ?");
            $stmt->execute([$_SESSION['user_id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($users as $user){
                echo "<option value='{$user['id']}'>{$user['name']}</option>";
            }
            ?>
        </select>
    </div>

    <input type="submit" value="Add Expense">

    <?php if (!empty($msg)): ?>
        <p class="msg <?= strpos($msg, 'Error') !== false ? 'error' : '' ?>">
            <?= htmlspecialchars($msg) ?>
        </p>
    <?php endif; ?>
</form>

<div class="back">
    <a href="dashboard.php">⬅ Back to Dashboard</a>
</div>

<script>
    // Show custom category input
    document.getElementById("category").addEventListener("change", function() {
        const customInput = document.getElementById("custom_category");
        if (this.value === "custom") {
            customInput.style.display = "block";
            customInput.required = true;
        } else {
            customInput.style.display = "none";
            customInput.required = false;
        }
    });

    // Show shared users only if checkbox checked
    document.getElementById('is_shared').addEventListener('change', function(){
        document.getElementById('shared_users_div').style.display = this.checked ? 'block' : 'none';
    });

    // Dark mode toggle
    function toggleMode() {
        document.body.classList.toggle("dark-mode");
    }
</script>
</body>
</html>
