<?php
session_start();
require 'path_to_database_connection.php';

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($messages);
} else {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Chưa đăng nhập']);
}
?>
