<?php
session_start();
require 'config.php'; // Your PDO connection

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT name, email, password FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';
$pass_success = '';
$pass_error = '';

// Update name & email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name && $email) {
        // Check if email already exists (excluding current user)
        $check = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
        $check->execute([
            ':email' => $email,
            ':user_id' => $user_id
        ]);

        if ($check->rowCount() > 0) {
            $error = "This email is already registered with another account!";
        } else {
            $update = $conn->prepare("UPDATE users SET name = :name, email = :email WHERE id = :user_id");
            $update->execute([
                ':name' => $name,
                ':email' => $email,
                ':user_id' => $user_id
            ]);
            $success = "Profile updated successfully!";
            $user['name'] = $name;
            $user['email'] = $email;
        }
    } else {
        $error = "All fields are required!";
    }
}


// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($old_password && $new_password && $confirm_password) {
        if (!password_verify($old_password, $user['password'])) {
            $pass_error = "Old password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $pass_error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $pass_error = "Password must be at least 6 characters!";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $update->execute([
                ':password' => $hashed,
                ':user_id' => $user_id
            ]);
            $pass_success = "Password changed successfully!";
        }
    } else {
        $pass_error = "All password fields are required!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile | Expense Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- No Bootstrap -->
    <style>
        /* ---------- Reset ---------- */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #fff;
            overflow-x: hidden;
            transition: background 0.4s, color 0.4s;
        }

        a { color: #fff; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ---------- Header ---------- */
        header {
            position: fixed; top: 0; left: 0; right: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 28px;
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(8px);
            z-index: 10;
        }
        header h1 { font-size: 1.25rem; margin: 0; font-weight: 700; letter-spacing: .3px; }
        nav ul { list-style: none; display: flex; gap: 18px; padding: 0; margin: 0; }
        nav a { position: relative; font-weight: 600; }
        nav a::after {
            content: ""; position: absolute; left: 0; bottom: -4px; height: 2px; width: 0;
            background: #fff; transition: width .25s ease;
        }
        nav a:hover::after { width: 100%; }

        .toggle-btn {
            cursor: pointer; padding: 8px 14px; border-radius: 999px;
            border: 2px solid #fff; background: transparent; color: #fff;
            font-size: .9rem; font-weight: 700; transition: .25s ease;
        }
        .toggle-btn:hover { background: #fff; color: #5a00f0; }

        /* ---------- Main / Layout ---------- */
        main {
            padding: 120px 20px 40px; /* space for fixed header */
        }
        .container {
            max-width: 1100px; margin: 0 auto;
        }

        .page-title {
            font-size: 2rem; font-weight: 800; margin: 0 0 20px;
            text-shadow: 0 2px 6px rgba(0,0,0,.25);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        @media (min-width: 900px) {
            .grid { grid-template-columns: 1fr 1fr; }
        }

        /* ---------- Cards ---------- */
        .card {
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.2);
            color: #fff;
        }
        .card h2, .card h3, .card h4 { margin: 0 0 14px; }
        .muted { opacity: .9; }

        /* ---------- Forms (no Bootstrap) ---------- */
        .form {
            display: grid;
            gap: 14px;
        }
        .form-row { display: grid; gap: 8px; }
        label { font-size: .95rem; font-weight: 600; opacity: .95; }
        .input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.35);
            background: rgba(255,255,255,0.18);
            color: #fff;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .input::placeholder { color: rgba(255,255,255,.8); }
        .input:focus {
            border-color: #ffdb5c;
            box-shadow: 0 0 0 3px rgba(255,219,92,.25);
            background: rgba(255,255,255,0.24);
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .08s ease, filter .2s ease, background .2s ease, color .2s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn.primary { background: #ffdb5c; color: #000; }
        .btn.primary:hover { filter: brightness(1.05); }
        .btn.warning { background: #ffd36e; color: #000; }
        .btn.warning:hover { filter: brightness(1.05); }

        /* ---------- Alerts ---------- */
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-weight: 700;
            margin: 6px 0 10px;
            border: 1px solid rgba(255,255,255,.35);
            background: rgba(255,255,255,0.16);
        }
        .alert.success { border-color: #00d084; box-shadow: inset 0 0 0 1px rgba(0,208,132,.35); }
        .alert.error { border-color: #ff6b6b; box-shadow: inset 0 0 0 1px rgba(255,107,107,.35); }

        /* ---------- Helpers ---------- */
        .spacer-8 { height: 8px; }
        .spacer-16 { height: 16px; }

        /* ---------- Footer ---------- */
        footer {
            margin-top: 28px;
            text-align: center;
            color: #eaeaea;
            opacity: .9;
        }

        /* ---------- Dark Mode ---------- */
        body.dark {
            background: linear-gradient(135deg,#0d0d0d,#1a1a1a);
            color: #eee;
        }
        body.dark header { background: rgba(255,255,255,0.05); }
        body.dark .card {
            background: rgba(255,255,255,0.06);
            color: #f0f0f0;
        }
        body.dark .input {
            background: rgba(255,255,255,0.10);
            border-color: rgba(255,255,255,0.25);
        }
        body.dark .alert { background: rgba(255,255,255,0.08); }
    </style>
</head>
<body>
<header>
    <h1>Expense Manager</h1>
    <nav>
        <ul>
            <li><a href="#profile">Profile</a></li>
            <li><a href="feedback.php">Feedback</a></li>
            <li><a href="#goals">Future Goals</a></li>
        </ul>
    </nav>
    <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
</header>

<main>
    <div class="container">
        <h2 class="page-title">My Profile</h2>

        <div class="grid" id="profile">
            <!-- Profile Update -->
            <section class="card">
                <h3>Update Profile</h3>

                <?php if ($success): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php elseif ($error): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateProfileForm();" class="form" novalidate>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-row">
                        <label for="name">Full Name</label>
                        <input type="text" class="input" name="name" id="name" placeholder="Your full name"
                               value="<?= htmlspecialchars($user['name'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label for="email">Email ID</label>
                        <input type="email" class="input" name="email" id="email" placeholder="name@example.com"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn primary">Update Profile</button>
                </form>
            </section>

            <!-- Change Password -->
            <section class="card">
                <h3>Change Password</h3>

                <?php if ($pass_success): ?>
                    <div class="alert success"><?= htmlspecialchars($pass_success) ?></div>
                <?php elseif ($pass_error): ?>
                    <div class="alert error"><?= htmlspecialchars($pass_error) ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validatePasswordForm();" class="form" novalidate>
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-row">
                        <label for="old_password">Old Password</label>
                        <input type="password" class="input" name="old_password" id="old_password" placeholder="••••••••">
                    </div>
                    <div class="form-row">
                        <label for="new_password">New Password</label>
                        <input type="password" class="input" name="new_password" id="new_password" placeholder="At least 6 characters">
                    </div>
                    <div class="form-row">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="input" name="confirm_password" id="confirm_password" placeholder="Repeat new password">
                    </div>
                    <button type="submit" class="btn warning">Change Password</button>
                    <div class="spacer-16"></div>
                    <div class="muted"><a href="dashboard.php">← Go To Dashboard</a></div>
                </form>
            </section>
        </div>
    </div>
</main>

<script>
    // Dark mode toggle (persists in localStorage)
    function toggleMode() {
        const isDark = document.body.classList.toggle('dark');
        try { localStorage.setItem('em_theme_dark', isDark ? '1' : '0'); } catch(e) {}
    }
    (function initTheme(){
        try {
            if (localStorage.getItem('em_theme_dark') === '1') {
                document.body.classList.add('dark');
            }
        } catch(e) {}
    })();

    // Client-side validation
    function validateProfileForm() {
        const name = document.getElementById("name").value.trim();
        const email = document.getElementById("email").value.trim();
        if (!name || !email) { alert("Please fill out all profile fields."); return false; }
        const emailPattern = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;
        if (!emailPattern.test(email)) { alert("Please enter a valid email address."); return false; }
        return true;
    }

    function validatePasswordForm() {
        const oldPassword = document.getElementById("old_password").value.trim();
        const newPassword = document.getElementById("new_password").value.trim();
        const confirmPassword = document.getElementById("confirm_password").value.trim();
        if (!oldPassword || !newPassword || !confirmPassword) {
            alert("Please fill out all password fields."); return false;
        }
        if (newPassword.length < 6) {
            alert("New password must be at least 6 characters."); return false;
        }
        if (newPassword !== confirmPassword) {
            alert("New passwords do not match."); return false;
        }
        return true;
    }
</script>
</body>
</html>
