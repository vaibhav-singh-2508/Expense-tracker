<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger</title>
    <link rel="stylesheet" href="ledger_style.css">
</head>
<body>

<!-- Header Navigation -->
<header class="ledger-header">
    <div class="logo">📘 Ledger</div>
    <nav class="ledger-nav">
        <ul>
            <li><a href="ledger_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="add_customer.php">Add Customer</a></li>
            <li><a href="ledger_report.php">Reports</a></li>
            <li><a href="dashboard.php">Back to App</a></li>
        </ul>
    </nav>
</header>

<main class="ledger-content">
