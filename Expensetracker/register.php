<?php
include 'config.php';
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $msg = "❌ Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);

            if ($check->rowCount() > 0) {
                $msg = "⚠️ Email already registered.";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashedPassword]);

                $msg = "✅ Registration successful. <a href='login.php'>Login here</a>";
            }

            $check = null;
            $stmt = null;
        } catch (PDOException $e) {
            $msg = "❌ Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Expense Tracker</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: linear-gradient(135deg, #0f0f0f, #1a1a1a, #2a2a2a);
      color: #fff;
    }

    header {
      background: rgba(0, 0, 0, 0.8);
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 6px rgba(0,0,0,0.5);
    }

    header h1 {
      margin: 0;
      font-size: 22px;
      color: #fff;
      font-weight: bold;
      letter-spacing: 1px;
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
      transition: color 0.3s;
    }

    nav ul li a:hover {
      color: #00c6ff;
    }

    .toggle-btn {
      background: #00c6ff;
      color: #000;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.3s;
    }

    .toggle-btn:hover {
      background: #009bd1;
      color: #fff;
    }

    .container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
    }

    .custom-card {
      background-color: #fff;
      color: #000;
      border-radius: 12px;
      box-shadow: 0 6px 12px rgba(0,0,0,0.3);
      width: 400px;
      padding: 30px;
    }

    h3 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 15px;
      box-sizing: border-box;
    }

    .btn-success {
      background-color: #28a745;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 6px;
      width: 100%;
      cursor: pointer;
      font-size: 16px;
    }

    .btn-success:hover {
      background-color: #218838;
    }

    .text-danger {
      color: red;
      margin-top: 10px;
      text-align: center;
    }

    .text-center {
      text-align: center;
    }

    .error-text {
      color: red;
      font-size: 0.9rem;
    }

    a {
      color: #007bff;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    .logo-section {
      text-align: center;
      margin-bottom: 20px;
    }

    .logo-section img {
      width: 90px;
      height: 90px;
      border-radius: 50%;
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

<div class="container">
  <div class="custom-card">
    <div class="logo-section">
      <img src="asset/logo.png" alt="Logo">
    </div>
    <h3>Create Account</h3>

    <form method="POST" onsubmit="return validatePasswords()" novalidate>
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" id="password" placeholder="Password" required>
      <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
      <div id="password-error" class="error-text"></div>

      <button type="submit" class="btn-success">Register</button>
    </form>

    <p class="text-danger"><?= $msg ?></p>
    <p class="mt-2 text-center">Already registered? <a href="login.php">Login</a></p>
  </div>
</div>

<script>
  function validatePasswords() {
    const password = document.getElementById('password').value.trim();
    const confirm = document.getElementById('confirm_password').value.trim();
    const errorDiv = document.getElementById('password-error');

    if (password !== confirm) {
      errorDiv.textContent = "❌ Passwords do not match.";
      return false;
    }

    errorDiv.textContent = "";
    return true;
  }

  function toggleMode() {
    document.body.classList.toggle('dark-mode');
  }
</script>

</body>
</html>
