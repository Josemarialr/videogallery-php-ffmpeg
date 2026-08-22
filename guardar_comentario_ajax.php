<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para comentar.']);
    exit;
}

$video_id   = intval($_POST['video_id'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');

if ($video_id <= 0 || empty($comentario)) {
    echo json_encode(['success' => false, 'message' => 'El comentario no puede estar vacío.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO comentarios (video_id, usuario_id, comentario) VALUES (?, ?, ?)");
$res  = $stmt->execute([$video_id, $_SESSION['usuario_id'], $comentario]);

if ($res) {
    echo json_encode([
        'success'        => true,
        'usuario_nombre' => htmlspecialchars($_SESSION['usuario_nombre']),
        'comentario'     => nl2br(htmlspecialchars($comentario)),
        'creado_en'      => date('d/m/Y H:i')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el comentario en la base de datos.']);
}
