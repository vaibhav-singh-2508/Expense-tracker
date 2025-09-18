<?php
function addNotification(PDO $pdo, int $user_id, string $message, string $type='info'): void {
    $ins = $pdo->prepare("
        INSERT INTO notifications (user_id, message, type) 
        VALUES (:uid, :msg, :type)
    ");
    $ins->execute([
        ':uid'  => $user_id,
        ':msg'  => $message,
        ':type' => $type
    ]);
}
