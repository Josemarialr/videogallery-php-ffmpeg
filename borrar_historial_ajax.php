<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php'; // Asegúrate de incluir tu archivo de conexión PDO

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';

if ($accion === 'borrar_todo') {
    // Eliminar todo el historial del usuario activo
    $stmt = $pdo->prepare("DELETE FROM historial_visitas WHERE usuario_id = ?");
    $result = $stmt->execute([$usuario_id]);

    echo json_encode(['success' => $result]);
    exit;
}

if ($accion === 'borrar_item') {
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    if (!$video_id) {
        echo json_encode(['success' => false, 'message' => 'ID de video inválido']);
        exit;
    }

    // Eliminar solo ese ítem de la tabla historial_visitas
    $stmt = $pdo->prepare("DELETE FROM historial_visitas WHERE usuario_id = ? AND video_id = ?");
    $result = $stmt->execute([$usuario_id, $video_id]);

    echo json_encode(['success' => $result]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
