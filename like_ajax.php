<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para dar Me Gusta.']);
    exit;
}

$video_id = intval($_POST['video_id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];

try {
    // Intentar registrar el like
    $stmt = $pdo->prepare("INSERT INTO video_likes (video_id, usuario_id) VALUES (?, ?)");
    $stmt->execute([$video_id, $usuario_id]);

    // Incrementar en la tabla videos
    $pdo->prepare("UPDATE videos SET likes = likes + 1 WHERE id = ?")->execute([$video_id]);

    // Obtener nuevo total
    $stmt_likes = $pdo->prepare("SELECT likes FROM videos WHERE id = ?");
    $stmt_likes->execute([$video_id]);
    $total_likes = $stmt_likes->fetchColumn();

    echo json_encode(['success' => true, 'likes' => $total_likes]);
} catch (PDOException $e) {
    // Si ya dio like (clave duplicada)
    echo json_encode(['success' => false, 'message' => 'Ya has dado Me Gusta a este video.']);
}
?>
