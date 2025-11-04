<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: activity.php");
    exit;
}

$share_id = intval($_POST['share_id']);
$me = $_SESSION['user_id'];

try {
    // load shared_expense and related expense owner
    $stmt = $conn->prepare("SELECT se.expense_id, se.user_id AS owes_user, se.paid_request, se.is_settled, e.user_id AS expense_owner
                            FROM shared_expenses se
                            JOIN expenses e ON se.expense_id = e.id
                            WHERE se.id = ?");
    $stmt->execute([$share_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header("Location: activity.php");
        exit;
    }

    // only expense owner can confirm
    if ($row['expense_owner'] != $me) {
        header("Location: activity.php");
        exit;
    }

    if ($row['is_settled']) {
        header("Location: activity.php");
        exit;
    }

    // Ensure there was a paid_request (optional: allow direct confirm if you want)
    if (!$row['paid_request']) {
        // optional: you could directly settle without paid_request
        // For now we'll still allow direct confirm (uncomment if you want stricter flow)
        // header("Location: activity.php");
        // exit;
    }

    // mark settled
    $u = $conn->prepare("UPDATE shared_expenses SET is_settled = 1, paid_request = 0, settled_date = NOW() WHERE id = ?");
    $u->execute([$share_id]);

    // notify the payer that payment was confirmed
    $msg = "Your payment has been confirmed by " . htmlspecialchars($_SESSION['user_name']) . ".";
    $ins = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, share_id, message) VALUES (?, ?, ?, ?)");
    $ins->execute([$row['owes_user'], $me, $share_id, $msg]);

    header("Location: activity.php");
    exit;
} catch (PDOException $e) {
    header("Location: activity.php");
    exit;
}
