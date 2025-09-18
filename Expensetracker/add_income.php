<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php'; // Uses $conn (PDO object)

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id     = $_SESSION['user_id'];
    $amount      = $_POST['amount'];
    $source      = $_POST['source'];
    $income_date = $_POST['income_date'];

    try {
        // Insert income
        $stmt = $conn->prepare("INSERT INTO incomes (user_id, amount, source, income_date) 
                                VALUES (:user_id, :amount, :source, :income_date)");
        $stmt->execute([
            ':user_id'     => $user_id,
            ':amount'      => $amount,
            ':source'      => $source,
            ':income_date' => $income_date
        ]);

        // ✅ Insert notification
        $noti = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at)
                                VALUES (:uid, :msg, :type, 0, NOW())");
        $noti->execute([
            ':uid'  => $user_id,
            ':msg'  => "Income of ₹{$amount} added from {$source}",
            ':type' => "income"
        ]);

        $msg = "✅ Income added successfully!";
    } catch (PDOException $e) {
        $msg = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Income</title>
    <style>
        * { box-sizing: border-box; margin:0; padding:0; font-family: Arial, sans-serif; }
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            color: #fff;
        }

        /* ===== HEADER ===== */
        header {
            background: rgba(0,0,0,0.6);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(8px);
        }
        header h1 { margin: 0; font-size: 24px; color: #fff; }
        nav ul { list-style: none; display: flex; gap: 20px; margin: 0; padding: 0; }
        nav ul li a { text-decoration: none; color: #fff; font-weight: bold; transition: 0.3s; }
        nav ul li a:hover { color: #00ffcc; }
        .toggle-btn {
            background: none;
            border: 2px solid #fff;
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }
        .toggle-btn:hover { background: #fff; color: #000; }

        /* ===== FORM ===== */
        h2 {
            text-align: center;
            margin-top: 40px;
            font-weight: 600;
            color: #fff;
        }

        form {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            max-width: 500px;
            margin: 30px auto;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        input[type="number"],
        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            transition: 0.3s ease;
        }

        input:focus {
            outline: none;
            box-shadow: 0 0 5px #00ffcc;
        }

        input[type="submit"] {
            width: 100%;
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 12px;
            transition: 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #218838;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .msg {
            text-align: center;
            font-weight: 500;
            margin-top: 10px;
            color: lightgreen;
        }

        .msg.error { color: red; }

        .back {
            text-align: center;
            margin-top: 20px;
        }
        .back a {
            text-decoration: none;
            color: #fff;
            font-weight: 500;
        }
        .back a:hover { color: #00ffcc; text-decoration: underline; }
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

    <h2>Add Income</h2>
    <form method="POST">
        <input type="number" step="0.01" name="amount" placeholder="Amount" required />
        <input type="text" name="source" placeholder="Source (e.g., Salary, Freelance)" required />
        <input type="date" name="income_date" required />
        <input type="submit" value="Add Income" />

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
        function toggleMode() {
            document.body.classList.toggle('dark-mode');
        }
    </script>
</body>
</html>
