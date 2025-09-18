<?php
session_start();
require 'config.php';

// redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    if ($name == "") {
        $message = "⚠️ Name is required!";
    } else {
        // check for duplicate person
        $check = $conn->prepare("SELECT id FROM customers 
                         WHERE user_id = :uid 
                         AND (phone = :phone OR email = :email)");
        $check->execute([
            ':uid' => $_SESSION['user_id'],
            ':phone' => $phone,
            ':email' => $email
        ]);

        if ($check->rowCount() > 0) {
            $message = "⚠️ Person with this phone or email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (user_id, name, phone, email, created_at) 
                            VALUES (:uid, :name, :phone, :email, NOW())");
            $stmt->execute([
                ':uid' => $_SESSION['user_id'],
                ':name' => $name,
                ':phone' => $phone,
                ':email' => $email
            ]);
            $message = "✅ Customer added successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Add Person</title>
        <link href="css/ledger_style.css" rel="stylesheet" type="text/css"/>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background:linear-gradient(180deg,#0b1022, #0f172a 40%, #0b1022);
            }
            .container {
                width: 90%;
                max-width: 450px;
                background: #fff;
                padding: 35px 50px;
                border-radius: 16px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                animation: fadeIn 0.6s ease-in-out;
            }
            h1 {
                text-align: center;
                margin-bottom: 25px;
                color: #007bff;
            }
            .form-group {
                position: relative;
                margin-bottom: 20px;
            }
            .form-group input {
                width: 100%;
                padding: 14px 12px;
                border: 2px solid #ccc;
                border-radius: 8px;
                outline: none;
                font-size: 15px;
                transition: 0.3s;
            }
            .form-group label {
                position: absolute;
                left: 12px;
                top: 14px;
                background: #fff;
                padding: 0 5px;
                color: #666;
                font-size: 14px;
                transition: 0.3s;
                pointer-events: none;
            }
            .form-group input:focus {
                border-color: #007bff;
            }
            .form-group input:focus + label,
            .form-group input:not(:placeholder-shown) + label {
                top: -8px;
                left: 10px;
                font-size: 12px;
                color: #007bff;
            }
            button {
                background: #007bff;
                color: white;
                padding: 14px;
                border: none;
                width: 100%;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                transition: transform 0.2s, background 0.3s;
            }
            button:hover {
                background: #0056b3;
                transform: translateY(-2px);
            }
            .msg {
                text-align: center;
                margin-bottom: 15px;
                font-weight: bold;
                padding: 10px;
                border-radius: 8px;
            }
            .msg:empty {
                display: none;
            }
            .msg.success {
                color: green;
                background: #e8f5e9;
            }
            .msg.error {
                color: red;
                background: #ffebee;
            }
            .back {
                display: block;
                text-align: center;
                margin-top: 15px;
                text-decoration: none;
                color: #007bff;
                transition: 0.3s;
            }
            .back:hover {
                color: #0056b3;
            }
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

        </style>
    </head>
    <body>
        <div class="container">
            <h1>➕ Add Person</h1>

            <?php if ($message): ?>
                <div class="msg <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <input type="text" name="name" placeholder=" " required>
                    <label>Name</label>
                </div>
                <div class="form-group">
                    <input type="text" name="phone" placeholder=" ">
                    <label>Phone</label>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder=" ">
                    <label>Email</label>
                </div>
                <button type="submit">Add Person</button>
            </form>

            <a href="ledger_dashboard.php" class="back">⬅ Back to Dashboard</a>
        </div>
    </body>
</html>