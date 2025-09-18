<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Expense Manager - Home</title>
        <style>
            /* Reset */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', sans-serif;
            }

            body {
                background: linear-gradient(135deg, #74ebd5, #ACB6E5);
                color: #fff;
                overflow-x: hidden;
                transition: background 0.4s, color 0.4s;
            }

            /* Header */
            header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 60px;
                background: rgba(0,0,0,0.2);
                position: fixed;
                top: 0;
                width: 100%;
                z-index: 10;
                backdrop-filter: blur(8px);
            }
            header h1 {
                font-size: 1.8rem;
                font-weight: bold;
            }
            nav ul {
                display: flex;
                gap: 25px;
                list-style: none;
            }
            nav ul li a {
                color: #fff;
                text-decoration: none;
                font-weight: 500;
                position: relative;
            }
            nav ul li a::after {
                content: "";
                position: absolute;
                width: 0;
                height: 2px;
                bottom: -4px;
                left: 0;
                background: #fff;
                transition: 0.3s;
            }
            nav ul li a:hover::after {
                width: 100%;
            }

            /* Dark Mode Button */
            .toggle-btn {
                cursor: pointer;
                padding: 8px 16px;
                border-radius: 20px;
                border: 2px solid #fff;
                background: transparent;
                color: #fff;
                font-size: 0.9rem;
                font-weight: 600;
                transition: all 0.3s;
            }
            .toggle-btn:hover {
                background: #fff;
                color: #5a00f0;
            }

            /* Hero */
            .hero {
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: space-evenly;
                padding: 120px 60px 60px;
                text-align: left;
                position: relative;
            }
            .hero-text {
                max-width: 50%;
            }
            .hero h2 {
                font-size: 3rem;
                font-weight: 800;
                margin-bottom: 20px;
                animation: fadeInDown 1.2s;
            }
            .hero p {
                font-size: 1.2rem;
                margin-bottom: 30px;
                animation: fadeInUp 1.5s;
            }
            .hero button {
                padding: 12px 30px;
                border: none;
                border-radius: 25px;
                background: #fff;
                color: #5a00f0;
                font-weight: bold;
                cursor: pointer;
                transition: 0.3s;
                animation: fadeInUp 1.5s;
            }
            .hero button:hover {
                background: #ffdb5c;
                color: #000;
                transform: scale(1.05);
            }

            /* Login Card */
            .login-card {
                background: rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);
                padding: 30px;
                border-radius: 15px;
                width: 500px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.2);
                text-align: center;
                animation: fadeInUp 1.5s;
            }
            .login-card h3 {
                margin-bottom: 20px;
                font-size: 1.5rem;
            }
            .login-card input {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: none;
                border-radius: 8px;
                background: rgba(255,255,255,0.2);
                outline: none;
            }
            .login-card input::placeholder {
                color: #ddd;
            }
            .btn-primary {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background: #ffdb5c;
                color: #000;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
            }
            .btn-primary:hover {
                background: #ffc107;
                transform: scale(1.05);
            }
            .text-danger {
                color: #ff6b6b;
                margin-top: 10px;
                font-size: 0.9rem;
            }

            /* Floating shapes */
            .circle {
                position: absolute;
                border-radius: 50%;
                background: rgba(255,255,255,0.15);
                animation: float 8s infinite ease-in-out;
            }
            .circle.small {
                width: 50px;
                height: 50px;
                top: 20%;
                left: 15%;
            }
            .circle.medium {
                width: 100px;
                height: 100px;
                top: 50%;
                right: 10%;
            }
            .circle.large {
                width: 150px;
                height: 150px;
                bottom: 10%;
                left: 5%;
            }
            @keyframes float {
                0%,100% {
                    transform: translateY(0);
                }
                50% {
                    transform: translateY(-30px);
                }
            }
            @keyframes fadeInDown {
                from {
                    opacity: 0;
                    transform: translateY(-30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Sections */
            section {
                padding: 80px 60px;
                text-align: center;
                background: #fff;
                color: #333;
            }
            section h3 {
                font-size: 2rem;
                margin-bottom: 20px;
                color: #5a00f0;
            }
            section p {
                max-width: 800px;
                margin: 0 auto 30px;
                line-height: 1.6;
            }

            /* Team */
            .team-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
                gap: 30px;
                margin-top: 40px;
            }
            .team-card {
                background: #fff;
                border-radius: 15px;
                padding: 20px;
                text-align: center;
                box-shadow: 0 6px 15px rgba(0,0,0,0.1);
                transition: 0.4s;
            }
            .team-card img {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                border: 4px solid #5a00f0;
                margin-bottom: 15px;
            }
            .team-card:hover {
                transform: translateY(-10px) scale(1.05);
                box-shadow: 0 12px 25px rgba(0,0,0,0.2);
            }

            /* Footer */
            footer {
                background: #2a004f;
                text-align: center;
                padding: 20px;
                color: #aaa;
            }

            /* Dark Mode */
            body.dark {
                background: linear-gradient(135deg,#0d0d0d,#1a1a1a);
                color: #eee;
            }
            body.dark header {
                background: rgba(255,255,255,0.05);
            }
            body.dark section {
                background: #1a1a1a;
                color: #ddd;
            }
            body.dark section h3 {
                color: #ffdb5c;
            }
            body.dark .team-card {
                background: #222;
                color: #eee;
            }
            body.dark .team-card h4 {
                color: #ffdb5c;
            }
            body.dark footer {
                background: #000;
                color: #777;
            }
            .feedback-slider {
                overflow: hidden;
                width: 100%;
                margin-top: 30px;
            }
            .feedback-track {
                display: flex;
                gap: 20px;
                animation: scroll 10s linear infinite;
            }
            .feedback-card {
                flex: 0 0 300px;
                background: #fff;
                color: #333;
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 6px 15px rgba(0,0,0,0.1);
                text-align: center;
            }
            .feedback-card h4 {
                color: #5a00f0;
                margin-bottom:10px;
            }
            .feedback-card p {
                font-style: italic;
                color:#555;
            }

            @keyframes scroll {
                0%   {
                    transform: translateX(0);
                }
                100% {
                    transform: translateX(-50%);
                }
            }
        </style>
    </head>
    <body>
        <!-- Header -->
        <header>
            <h1>Expense Manager</h1>
            <nav>
                <ul>
                    <li><a href="#login">Login</a></li>
                    <li><a href="#Contact Us">Contact Us</a></li>
                    <li><a href="#team">Team</a></li>
                    <li><a href="feedback.php">Feedback</a></li>
                    <li><a href="#goals">Future Goals</a></li>
                </ul>
            </nav>
            <button class="toggle-btn" onclick="toggleMode()">🌙 Dark Mode</button>
        </header>

        <!-- Hero -->
        <section class="hero" id="login">
            <div class="hero-text">
                <h2>Manage Your Expenses Smartly</h2>
                <p>Track income, expenses, and plan your financial future with ease. Expense Manager helps you stay in control and make smarter money decisions.</p>
                <button onclick="document.querySelector('.login-card').scrollIntoView({behavior: 'smooth'})">Get Started</button>
            </div>
            <div class="login-card">
                <h3>User Login</h3>
                <form method="POST">
                    <input type="email" name="email" placeholder="Email" required />
                    <input type="password" name="password" placeholder="Password" required />
                    <button type="submit" class="btn-primary">Login</button>
                </form>
                <p class="text-danger"><?php echo $msg ?? ''; ?></p>
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
            <!-- Floating shapes -->
            <div class="circle small"></div>
            <div class="circle medium"></div>
            <div class="circle large"></div>
        </section>

        <section id="feedback">
            <h3>User Feedback</h3>
            <p>We value your input! Here’s what users are saying about Expense Manager.</p>

            <div class="feedback-slider">
                <div class="feedback-track">
                    <?php
                    $stmt = $conn->query("SELECT name, message FROM feedback ORDER BY created_at DESC LIMIT 5");
                    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($feedbacks as $fb) {
                        echo "
                <div class='feedback-card'>
                    <h4>" . htmlspecialchars($fb['name']) . "</h4>
                    <p>“" . htmlspecialchars($fb['message']) . "”</p>
                </div>";
                    }

                    // duplicate again for smooth infinite loop
                    foreach ($feedbacks as $fb) {
                        echo "
                <div class='feedback-card'>
                    <h4>" . htmlspecialchars($fb['name']) . "</h4>
                    <p>“" . htmlspecialchars($fb['message']) . "”</p>
                </div>";
                    }
                    ?>
                </div>
            </div>
        </section>
        <!-- Team -->
        <section id="team">
            <h3>Our Team</h3>
            <p>A passionate group of developers and designers building solutions for better financial management.</p>
            <div class="team-container">
                <div class="team-card"><img src="asset/profile.png"><h4>Vaibhav Singh</h4><p>Head of Project</p></div>
                <div class="team-card"><img src="https://via.placeholder.com/150"><h4>Ananya Sharma</h4><p>UI/UX Designer</p></div>
                <div class="team-card"><img src="https://via.placeholder.com/150"><h4>Rohit Mehta</h4><p>Backend Developer</p></div>
                <div class="team-card"><img src="https://via.placeholder.com/150"><h4>Priya Nair</h4><p>Frontend Developer</p></div>
            </div>
        </section>

        <!-- Goals -->
        <section id="goals">
            <h3>Future Goals</h3>
            <p>We aim to bring AI-powered expense predictions, smart budgeting tools, and collaborative financial planning features in upcoming versions.</p>
        </section>

        <!-- Footer -->
        <footer>
            <p>&copy; 2025 Expense Manager. All rights reserved.</p>
        </footer>

        <script>
            function toggleMode() {
                document.body.classList.toggle("dark");
                const btn = document.querySelector(".toggle-btn");
                btn.textContent = document.body.classList.contains("dark") ? "☀️ Light Mode" : "🌙 Dark Mode";
            }
        </script>
    </body>
</html>