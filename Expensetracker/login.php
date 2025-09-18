<?php
session_start();
include 'config.php';
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $msg = "Invalid password.";
        }
    } else {
        $msg = "User not found, Register yourself first.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Login - Expense Tracker</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #e0f2fe;
            }

            .container {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }

            .custom-card {
                background-color: #fff;
                border: 1px solid #ddd;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                width: 400px;
                padding: 30px;
            }

            h3 {
                text-align: center;
                margin-bottom: 25px;
                color: #333;
            }

            input[type="email"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid #ccc;
                border-radius: 6px;
                box-sizing: border-box;
                font-size: 15px;
            }

            .btn-primary {
                background-color: #007bff;
                color: white;
                padding: 12px;
                border: none;
                border-radius: 6px;
                width: 100%;
                cursor: pointer;
                font-size: 16px;
            }

            .btn-primary:hover {
                background-color: #0056b3;
            }

            .text-danger {
                color: red;
                margin-top: 10px;
                text-align: center;
            }

            .text-center {
                text-align: center;
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

            .logo-section h1 {
                color: white;
                margin-top: 10px;
                font-size: 28px;
                font-weight: bold;
                letter-spacing: 1px;
            }

        </style>
    </head>
    <body>

        <div class="container">

            <div class="custom-card">
                <div class="logo-section">
                    <img src="asset/logo.png" alt="Logo"> <!-- Replace with your logo path -->
                </div>
                <h3>User Login</h3>
                <form method="POST">
                    <input type="email" name="email" placeholder="Email" required />
                    <input type="password" name="password" placeholder="Password" required />
                    <button type="submit" class="btn-primary">Login</button>
                </form>
                <p class="text-danger"><?php echo $msg ?? ''; ?></p>
                <p class="mt-2 text-center">Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </body>
</html>