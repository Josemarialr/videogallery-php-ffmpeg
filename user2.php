<?php
require_once 'config.php';
require_once 'header.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$idu = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// 1. PROCESAR ACTUALIZACIÓN DEL PERFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre           = trim($_POST['nombre'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $pais             = trim($_POST['pais'] ?? '');
    $telefono         = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : NULL;
    $wirepusher_id    = trim($_POST['wirepusher_id'] ?? ''); // Se guarda en la columna 'id'

    // Validar si el nuevo email ya está en uso por otro usuario
    $stmt_email = $pdo->prepare("SELECT idu FROM usuarios WHERE email = ? AND idu != ?");
    $stmt_email->execute([$email, $idu]);
    if ($stmt_email->fetch()) {
        $error = "El correo electrónico ya está registrado por otro usuario.";
    }

    // Obtener foto actual por si no se sube una nueva
    $stmt_actual = $pdo->prepare("SELECT foto FROM usuarios WHERE idu = ?");
    $stmt_actual->execute([$idu]);
    $foto_nombre = $stmt_actual->fetchColumn();

    // Manejo de la subida de foto de perfil (si no hay errores previos)
    if (empty($error) && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $ext_permitidas)) {
            $dir_perfiles = 'uploads/perfiles/';
            if (!file_exists($dir_perfiles)) {
                mkdir($dir_perfiles, 0777, true);
            }

            // Nombre único para evitar sobreescritura
            $nuevo_nombre_foto = 'perfil_' . $idu . '_' . time() . '.' . $ext;
            $ruta_destino = $dir_perfiles . $nuevo_nombre_foto;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
                // Borrar foto anterior si existe, no está vacía y no es la predeterminada
                if ($foto_nombre && $foto_nombre !== 'default.png' && file_exists($dir_perfiles . $foto_nombre)) {
                    unlink($dir_perfiles . $foto_nombre);
                }
                $foto_nombre = $nuevo_nombre_foto;
            } else {
                $error = "Error al mover la imagen cargada al servidor.";
            }
        } else {
            $error = "Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.";
        }
    }

    if (empty($error)) {
        // UPDATE en la tabla 'usuarios' usando 'idu' como PK y 'id' para WirePusher
        $sql = "UPDATE usuarios SET
                    nombre = ?,
                    email = ?,
                    pais = ?,
                    telefono = ?,
                    fecha_nacimiento = ?,
                    id = ?,
                    foto = ?
                WHERE idu = ?";

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nombre, $email, $pais, $telefono, $fecha_nacimiento, $wirepusher_id, $foto_nombre, $idu])) {
            // Actualizar también la variable de sesión del nombre para reflejarlo instantáneamente en el header
            $_SESSION['usuario_nombre'] = $nombre;
            $mensaje = "Perfil actualizado con éxito.";
        } else {
            $error = "Error al guardar los cambios en la base de datos.";
        }
    }
}

// 2. OBTENER DATOS ACTUALES DEL USUARIO (Filtrado por 'idu')
$stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE idu = ?");
$stmt_user->execute([$idu]);
$usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "Usuario no encontrado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-secondary text-white shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Mi Perfil (ID User: <?= htmlspecialchars($usuario['idu']) ?>)</h4>
                    <a href="index.php" class="btn btn-outline-light btn-sm">&laquo; Volver</a>
                </div>
                <div class="card-body">

                    <!-- MENSAJES DE ALERTA -->
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($mensaje) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="user2.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="actualizar_perfil" value="1">

                        <!-- MOSTRAR Y CAMBIAR FOTO -->
                        <div class="row mb-4 align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if (!empty($usuario['foto']) && file_exists('uploads/perfiles/' . $usuario['foto'])): ?>
                                    <img src="uploads/perfiles/<?= htmlspecialchars($usuario['foto']) ?>" alt="Foto Perfil" class="img-thumbnail rounded-circle mb-2" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/120?text=Sin+Foto" alt="Sin Foto" class="img-thumbnail rounded-circle mb-2" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <label for="foto" class="form-label">Foto de Perfil</label>
                                <input type="file" name="foto" id="foto" class="form-control" accept="image/*">
                                <small class="text-light">Formatos admitidos: JPG, PNG, WEBP, GIF.</small>
                            </div>
                        </div>

                        <hr class="border-secondary">

                        <!-- NOMBRE Y EMAIL -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- PAÍS Y TELÉFONO -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pais" class="form-label">País</label>
                                <input type="text" name="pais" id="pais" class="form-control" placeholder="Ej: Argentina" value="<?= htmlspecialchars($usuario['pais'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" name="telefono" id="telefono" class="form-control" placeholder="+54 9 11 ..." value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- FECHA DE NACIMIENTO Y WIREPUSHER -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($usuario['fecha_nacimiento'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="wirepusher_id" class="form-label">ID / Token WirePusher</label>
                                <input type="text" name="wirepusher_id" id="wirepusher_id" class="form-control" placeholder="ID de WirePusher" value="<?= htmlspecialchars($usuario['id'] ?? '') ?>">
                                <small class="text-light">ID de WirePusher (columna 'id' en la base de datos).</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3">Guardar Cambios</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
