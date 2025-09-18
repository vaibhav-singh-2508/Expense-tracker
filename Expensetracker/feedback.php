<?php
session_start();
require 'config.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $message = trim($_POST['message']);

    if ($name && $message) {
        $stmt = $conn->prepare("INSERT INTO feedback (name, message) VALUES (?, ?)");
        $stmt->execute([$name, $message]);
        $msg = "Thank you for your feedback!";
    } else {
        $msg = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Give Feedback | Expense Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Reset */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding-top: 80px; /* space for header */
        }

        /* Header */
        header {
            position: fixed; top: 0; left: 0; right: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 32px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 4px 18px rgba(0,0,0,0.3);
            z-index: 10;
        }
        header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffdb5c, #ff9a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 0.6px;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 24px;
            margin: 0;
            padding: 0;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            position: relative;
            transition: color .2s ease;
        }
        nav a:hover { color: #ffdb5c; }
        nav a::after {
            content: "";
            position: absolute;
            bottom: -5px; left: 0;
            height: 2px; width: 0;
            background: #ffdb5c;
            transition: width .25s ease;
        }
        nav a:hover::after { width: 100%; }

        /* Card */
        .form-container {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            padding: 28px;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            animation: fadeIn 1s ease;
        }

        h2 {
            text-align: center;
            margin: 0 0 20px;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #ffdb5c;
        }

        /* Inputs */
        .input, textarea {
            width: 100%;
            padding: 12px 14px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            background: rgba(255,255,255,0.15);
            color: #fff;
            resize: none;
            outline: none;
            transition: all 0.25s ease;
            font-size: 1rem;
        }
        .input::placeholder,
        textarea::placeholder { color: rgba(255,255,255,0.75); }
        .input:focus, textarea:focus {
            border-color: #ffdb5c;
            background: rgba(255,255,255,0.22);
            box-shadow: 0 0 0 3px rgba(255,219,92,0.25);
        }

        /* Button */
        button {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            background: #ffdb5c;
            color: #000;
            transition: filter 0.2s ease, transform 0.08s ease;
            font-size: 1rem;
        }
        button:hover { filter: brightness(1.05); }
        button:active { transform: translateY(1px); }

        /* Message */
        .msg {
            text-align: center;
            margin-top: 12px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .msg.success { color: #00d084; }
        .msg.error { color: #ff6b6b; }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Link below form */
        .home-link {
            display: block;
            margin-top: 14px;
            text-align: center;
            color: #ffdb5c;
            font-weight: 600;
            text-decoration: none;
        }
        .home-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header>
        <h1>Expense Manager</h1>
        <nav>
            <ul>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="goals.php">Future Goals</a></li>
            </ul>
        </nav>
    </header>

    <div class="form-container">
        <h2>Give Your Feedback</h2>
        <form method="POST">
            <input type="text" class="input" name="name" placeholder="Your Name" required>
            <textarea name="message" rows="4" placeholder="Your Feedback..." required></textarea>
            <button type="submit">Submit</button>
        </form>
        <?php if ($msg): ?>
            <p class="msg <?= ($msg === "Thank you for your feedback!") ? 'success' : 'error' ?>">
                <?= htmlspecialchars($msg) ?>
            </p>
        <?php endif; ?>
        <a href="index.php" class="home-link">← Back to Home</a>
    </div>
</body>
</html>