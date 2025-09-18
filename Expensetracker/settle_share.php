<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $share_id = $_POST['share_id'];
    $stmt = $conn->prepare("UPDATE shared_expenses SET is_settled = 1 WHERE id = ?");
    $stmt->execute([$share_id]);
    header("Location: activity.php"); // redirect back to activity page
}
?>
