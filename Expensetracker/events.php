<?php
session_start();
require 'config.php';
require 'functions.php'; // make sure addNotification() is here

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notifications = [];

// ----- 1. Monthly Expense Trend -----
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// Current month total OUT
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions 
                        WHERE user_id = :u AND type = 'OUT' 
                        AND DATE_FORMAT(date, '%Y-%m') = :m");
$stmt->execute([':u' => $user_id, ':m' => $thisMonth]);
$currentTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Last month total OUT
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions 
                        WHERE user_id = :u AND type = 'OUT' 
                        AND DATE_FORMAT(date, '%Y-%m') = :m");
$stmt->execute([':u' => $user_id, ':m' => $lastMonth]);
$lastTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Compare
if ($currentTotal > $lastTotal) {
    $msg = "Your expenses this month have increased compared to last month.";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "warning");
} elseif ($currentTotal < $lastTotal) {
    $msg = "Your expenses this month are lower than last month.";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "success");
} else {
    $msg = "Your expenses this month are the same as last month.";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "info");
}

// ----- 2. Budget Alerts -----
$budgetLimit = 5000;
$percentLimit = 0.8; // 80%

if ($currentTotal >= $budgetLimit) {
    $msg = "You have crossed your monthly limit of ₹$budgetLimit!";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "danger");
} elseif ($currentTotal >= $budgetLimit * $percentLimit) {
    $msg = "You’ve reached 80% of your monthly budget of ₹$budgetLimit.";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "warning");
}

// ----- 3. Cash in Hand -----
$stmt = $conn->prepare("SELECT 
                            (SELECT SUM(amount) FROM transactions WHERE user_id = :u AND type = 'IN') -
                            (SELECT SUM(amount) FROM transactions WHERE user_id = :u AND type = 'OUT')
                        AS cash_in_hand");
$stmt->execute([':u' => $user_id]);
$cashInHand = $stmt->fetch(PDO::FETCH_ASSOC)['cash_in_hand'] ?? 0;

if ($cashInHand < 0) {
    $msg = "Your cash in hand is negative. Please review your expenses.";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "danger");
} else {
    $msg = "You’re on track this month!";
    $notifications[] = $msg;
    addNotification($conn, $user_id, $msg, "success");
}

