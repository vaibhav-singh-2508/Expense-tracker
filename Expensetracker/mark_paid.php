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
    // verify share exists and belongs to this user and is not settled
    $stmt = $conn->prepare("SELECT expense_id, user_id, paid_request, is_settled FROM shared_expenses WHERE id = ?");
    $stmt->execute([$share_id]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$share) {
        header("Location: activity.php");
        exit;
    }

    if ($share['user_id'] != $me || $share['is_settled']) {
        header("Location: activity.php");
        exit;
    }

    if ($share['paid_request']) {
        // already requested — no-op
        header("Location: activity.php");
        exit;
    }

    // set paid_request = 1
    $u = $conn->prepare("UPDATE shared_expenses SET paid_request = 1 WHERE id = ?");
    $u->execute([$share_id]);

    // notify the expense owner (who is owed)
    $stmt2 = $conn->prepare("SELECT user_id FROM expenses WHERE id = ?");
    $stmt2->execute([$share['expense_id']]);
    $owner = $stmt2->fetchColumn();

    if ($owner) {
        $msg = "User " . htmlspecialchars($_SESSION['user_name']) . " marked a payment for you to confirm.";
        $ins = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, share_id, message) VALUES (?, ?, ?, ?)");
        $ins->execute([$owner, $me, $share_id, $msg]);
    }

    header("Location: activity.php");
    exit;
} catch (PDOException $e) {
    // optionally log error
    header("Location: activity.php");
    exit;
}
