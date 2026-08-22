<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre           = trim($_POST['nombre']);
    $email            = trim($_POST['email']);
    $password         = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $pais             = trim($_POST['pais'] ?? '');
    $telefono         = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;

    // Manejo de la foto de perfil (Opcional)
    $foto_nombre = 'default.png';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $dir_fotos = 'uploads/profiles/';
        if (!file_exists($dir_fotos)) {
            mkdir($dir_fotos, 0777, true);
        }
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nombre = uniqid('perfil_') . '.' . strtolower($ext);
        move_uploaded_file($_FILES['foto']['tmp_name'], $dir_fotos . $foto_nombre);
    }

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, pais, telefono, fecha_nacimiento, foto) VALUES (?, ?, ?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$nombre, $email, $password, $pais, $telefono, $fecha_nacimiento, $foto_nombre]);
        header("Location: login.php");
        exit;
    } catch (PDOException $e) {
        $error = "El correo ya está registrado o hubo un error en los datos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white py-5">
<div class="container col-md-6">
    <form method="POST" enctype="multipart/form-data" class="card p-4 bg-secondary text-white shadow">
        <h3 class="mb-3">Registro de Usuario</h3>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">País</label>
                <input type="text" name="pais" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Foto de Perfil</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Registrarse</button>
    </form>
</div>
</body>
</html>
