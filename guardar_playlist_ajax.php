<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para guardar en tu playlist.']);
    exit;
}

$video_id = intval($_POST['video_id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];

if ($video_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Video no válido.']);
    exit;
}

// Verificar si el video ya está guardado
$stmt = $pdo->prepare("SELECT id FROM playlist WHERE usuario_id = ? AND video_id = ?");
$stmt->execute([$usuario_id, $video_id]);
$existe = $stmt->fetch();

if ($existe) {
    // Eliminar de playlist
    $del = $pdo->prepare("DELETE FROM playlist WHERE usuario_id = ? AND video_id = ?");
    $del->execute([$usuario_id, $video_id]);
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Eliminado de tu playlist']);
} else {
    // Insertar en playlist
    $ins = $pdo->prepare("INSERT INTO playlist (usuario_id, video_id) VALUES (?, ?)");
    $ins->execute([$usuario_id, $video_id]);
    echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Guardado en tu playlist']);
}
