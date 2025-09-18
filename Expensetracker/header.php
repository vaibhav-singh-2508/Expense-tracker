<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// Fetch latest 5 unread notifications
$notiStmt = $conn->prepare("SELECT id, message, type, created_at 
                             FROM notifications 
                             WHERE user_id = :u AND is_read = 0 
                             ORDER BY created_at DESC LIMIT 3");
$notiStmt->execute([':u' => $_SESSION['user_id']]);
$notifications = $notiStmt->fetchAll(PDO::FETCH_ASSOC);
$unread_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Expense Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* ===== Global ===== */
    body {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      background: #f4f6f9;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      transition: background 0.3s;
    }
    a { text-decoration: none; color: inherit; }

    /* ===== Header/Navbar ===== */
    .navbar {
      background: linear-gradient(90deg, #0f172a, #1e293b);
      color: #fff;
      padding: 20px 25px;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
      transition: padding 0.3s, background 0.3s;
    }
    .navbar .brand {
      font-size: 22px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: transform 0.3s;
    }
    .navbar .brand:hover {
      transform: scale(1.05);
      color: #38bdf8;
    }

    .nav-right {
      position: absolute;
      right: 25px;
      display: flex;
      align-items: center;
      gap: 20px;
    }

    /* Notification Bell */
    .noti-bell {
      position: relative;
      font-size: 22px;
      cursor: pointer;
      user-select: none;
      transition: transform 0.2s;
    }
    .noti-bell:hover { transform: scale(1.2); }

    .noti-badge {
      position: absolute;
      top: -6px;
      right: -10px;
      background: red;
      color: white;
      font-size: 12px;
      padding: 2px 6px;
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.3); }
    }

    /* Dropdowns */
    .dropdown { position: relative; }
    .dropdown-toggle {
      display: flex;
      align-items: center;
      cursor: pointer;
      gap: 6px;
      color: #fff;
      font-size: 14px;
      transition: color 0.2s;
    }
    .dropdown-toggle:hover { color: #38bdf8; }

    .dropdown-menu {
      position: absolute;
      top: 110%;
      right: 0;
      background: #fff;
      min-width: 220px;
      border-radius: 8px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.2);
      display: none;
      flex-direction: column;
      z-index: 100;
      padding: 8px 0;
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.3s, transform 0.3s;
    }
    .dropdown-menu.show {
      display: flex;
      opacity: 1;
      transform: translateY(0);
    }
    .dropdown-menu a,
    .dropdown-menu span {
      padding: 10px 15px;
      font-size: 14px;
      display: block;
      color: #333;
      white-space: nowrap;
      transition: background 0.2s, color 0.2s;
    }
    .dropdown-menu a:hover { background: #f1f5f9; color: #007bff; }
    .dropdown-divider { height: 1px; background: #ddd; margin: 6px 0; }

    .profile-img {
      border-radius: 50%;
      transition: transform 0.3s;
    }
    .profile-img:hover { transform: scale(1.1); }

    /* ===== Footer ===== */
    .custom-footer {
      background: linear-gradient(90deg, #0f172a, #1e293b);
      color: #f8f9fa;
      text-align: center;
      padding: 18px 0;
      margin-top: auto;
      box-shadow: 0 -4px 10px rgba(0,0,0,0.3);
      transition: background 0.3s;
    }
    .custom-footer small {
      font-size: 14px;
      opacity: 0.85;
    }

    /* ===== Responsive ===== */
    @media (max-width: 700px) {
      .navbar { flex-direction: column; padding: 15px; }
      .nav-right { position: static; margin-top: 10px; gap: 12px; }
    }
  </style>
  <script>
    // Toggle dropdown with smooth animation
    function toggleDropdown(id) {
      const menu = document.getElementById(id);
      const isOpen = menu.classList.contains("show");
      document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.remove("show"));
      if (!isOpen) menu.classList.add("show");
    }

    // Close dropdown when clicking outside
    document.addEventListener("click", function(e) {
      if (!e.target.closest(".dropdown")) {
        document.querySelectorAll(".dropdown-menu").forEach(m => m.classList.remove("show"));
      }
    });
  </script>
</head>
<body>

  <!-- HEADER -->
  <nav class="navbar">
    <span class="brand">Expense Manager</span>

    <div class="nav-right">
      <!-- Notifications -->
      <div class="dropdown">
        <span class="dropdown-toggle noti-bell" onclick="toggleDropdown('notiMenu')">
          🔔
          <?php if ($unread_count > 0): ?>
            <span class="noti-badge"><?= $unread_count ?></span>
          <?php endif; ?>
        </span>
        <div class="dropdown-menu" id="notiMenu">
          <h6 style="margin:8px 15px;font-size:14px;color:#555;">Notifications</h6>
          <?php if ($unread_count > 0): ?>
            <?php foreach ($notifications as $n): ?>
              <span><?= htmlspecialchars($n['message']) ?></span>
            <?php endforeach; ?>
            <div class="dropdown-divider"></div>
            <a href="all_notifications.php" style="color:#007bff;">View All</a>
          <?php else: ?>
            <span style="color:#666;">No new notifications</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Profile -->
      <div class="dropdown">
        <span class="dropdown-toggle" onclick="toggleDropdown('profileMenu')">
          <img src="asset/profile.png" alt="Profile" class="profile-img" width="35" height="35">
          Account
        </span>
        <div class="dropdown-menu" id="profileMenu">
          <a href="profile.php">Profile Update</a>
          <a href="ledger_dashboard.php">Ledger</a>
          <a href="report.php">Report</a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" style="color:red;">Logout</a>
        </div>
      </div>
    </div>
  </nav>
