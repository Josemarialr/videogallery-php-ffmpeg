<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Función para obtener la IP del usuario
function getVisitorIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Recibir y limpiar datos del POST
$id        = trim($_POST['id'] ?? '');
$title     = trim($_POST['title'] ?? '');
$message   = trim($_POST['message'] ?? '');
$action    = trim($_POST['action'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');

// Incluir la clase Wirepusher
require_once 'wirepusher.php';

$enviado_exito = false;
$error_mensaje = '';

if (!empty($id) && $id !== '0') {
    try {
        // Enviar la notificación a través del método estático Wirepusher::send
        list($http_status, $response) = Wirepusher::send($id, $title, $message, 'video', $action, $image_url, 'video');

        if ($http_status == 200) {
            $enviado_exito = true;
        } else {
            $error_mensaje = "Respuesta del servidor Wirepusher: Código HTTP " . $http_status;
        }
    } catch (Exception $e) {
        $error_mensaje = "Error en el envío: " . $e->getMessage();
    }
} else {
    $error_mensaje = "Debe seleccionar un destinatario válido.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviando Notificación...</title>
    <!-- Bootstrap 5 CSS para mantener consistencia visual -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">

    <div class="card bg-secondary text-white shadow-lg p-4 text-center" style="max-width: 420px; width: 100%;">
        <?php if ($enviado_exito): ?>
            <div class="mb-3">
                <span class="display-3">🚀</span>
            </div>
            <h4 class="text-success fw-bold">¡Video Enviado!</h4>
            <p class="small text-light">La notificación se envió correctamente al dispositivo vía Wirepusher.</p>
        <?php else: ?>
            <div class="mb-3">
                <span class="display-3">⚠️</span>
            </div>
            <h4 class="text-warning fw-bold">Error al enviar</h4>
            <p class="small text-light"><?= htmlspecialchars($error_mensaje) ?></p>
        <?php endif; ?>

        <hr class="border-secondary">

        <div class="d-grid gap-2">
            <!-- Botón para cerrar la pestaña o volver atrás -->
            <button onclick="window.close();" class="btn btn-outline-light btn-sm">Cerrar pestaña</button>
            <a href="javascript:history.back();" class="btn btn-primary btn-sm">← Volver al video</a>
        </div>
    </div>

</body>
</html>
