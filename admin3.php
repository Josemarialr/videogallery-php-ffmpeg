<?php
require_once 'config.php';
require_once 'header.php';

if (!esAdmin()) {
    header("Location: login.php");
    exit;
}

$mensaje = "";
$error = "";

// PROCESAR BORRADO DE VIDEO
if (isset($_GET['eliminar_id'])) {
    $id_eliminar = intval($_GET['eliminar_id']);

    // Opcional: Obtener los nombres de archivos si deseas eliminarlos del disco
    $stmt_files = $pdo->prepare("SELECT archivo_video, archivo_thumb, archivo_preview FROM videos WHERE id = ?");
    $stmt_files->execute([$id_eliminar]);
    $v_files = $stmt_files->fetch();

    if ($v_files) {
        @unlink('uploads/videos/' . $v_files['archivo_video']);
        @unlink('uploads/thumbs/' . $v_files['archivo_thumb']);
        @unlink('uploads/previews/' . $v_files['archivo_preview']);
    }

    // Eliminar relaciones de tags y el registro del video
    $stmt_del_tags = $pdo->prepare("DELETE FROM video_tags WHERE video_id = ?");
    $stmt_del_tags->execute([$id_eliminar]);

    $stmt_del = $pdo->prepare("DELETE FROM videos WHERE id = ?");
    $stmt_del->execute([$id_eliminar]);

    $mensaje = "Video eliminado correctamente.";
}

// PROCESAR ACTUALIZACIÓN DE CAMPOS DEL VIDEO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_video'])) {
    $video_id      = intval($_POST['video_id']);
    $titulo        = trim($_POST['titulo'] ?? '');
    $categoria_id  = intval($_POST['categoria_id'] ?? 0);
    $visitas       = intval($_POST['visitas'] ?? 0);
    $likes         = intval($_POST['likes'] ?? 0);
    $tags_selected = $_POST['tags_select'] ?? [];

    if ($video_id > 0 && !empty($titulo) && $categoria_id > 0) {
        // Actualizar datos del video
        $stmt_upd = $pdo->prepare("UPDATE videos SET titulo = ?, categoria_id = ?, visitas = ?, likes = ? WHERE id = ?");
        $stmt_upd->execute([$titulo, $categoria_id, $visitas, $likes, $video_id]);

        // Actualizar Tags (Eliminar anteriores y vincular nuevos)
        $stmt_del_vtags = $pdo->prepare("DELETE FROM video_tags WHERE video_id = ?");
        $stmt_del_vtags->execute([$video_id]);

        foreach ($tags_selected as $tag_id) {
            $stmt_ins_tag = $pdo->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)");
            $stmt_ins_tag->execute([$video_id, intval($tag_id)]);
        }

        $mensaje = "Video ID #{$video_id} actualizado exitosamente.";
    } else {
        $error = "Por favor, completa todos los campos requeridos.";
    }
}

// OBTENER VIDEO A EDITAR (Si se presiona "Editar")
$video_editar = null;
$tags_video_editar = [];

if (isset($_GET['editar_id'])) {
    $id_editar = intval($_GET['editar_id']);
    $stmt_v = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt_v->execute([$id_editar]);
    $video_editar = $stmt_v->fetch();

    if ($video_editar) {
        $stmt_vt = $pdo->prepare("SELECT tag_id FROM video_tags WHERE video_id = ?");
        $stmt_vt->execute([$id_editar]);
        $tags_video_editar = $stmt_vt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// LISTAR TODOS LOS VIDEOS Y CATEGORÍAS
$sql_videos = "SELECT v.*, c.nombre AS categoria_nombre
               FROM videos v
               LEFT JOIN categorias c ON v.categoria_id = c.id
               ORDER BY v.id DESC";
$lista_videos = $pdo->query($sql_videos)->fetchAll();

$categorias      = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$tags_existentes = $pdo->query("SELECT * FROM tags ORDER BY nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin 2 - Edición de Campos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Panel Admin 2 - Edición de Campos</h2>
        <div>
            <a href="admin.php" class="btn btn-outline-light me-2">&laquo; Cargar Videos (Admin 1)</a>
            <a href="index.php" class="btn btn-outline-light">Ir a la Galería</a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DE EDICIÓN (Aparece al seleccionar "Editar") -->
    <?php if ($video_editar): ?>
        <div class="card bg-secondary text-white shadow mb-5 border-warning">
            <div class="card-header bg-warning text-dark font-weight-bold d-flex justify-content-between">
                <h5 class="mb-0">Editando Video ID #<?= $video_editar['id'] ?></h5>
                <a href="admin2.php" class="btn btn-sm btn-dark">Cancelar Edición</a>
            </div>
            <div class="card-body">
                <form action="admin2.php" method="POST">
                    <input type="hidden" name="actualizar_video" value="1">
                    <input type="hidden" name="video_id" value="<?= $video_editar['id'] ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($video_editar['titulo']) ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $video_editar['categoria_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Visitas</label>
                            <input type="number" name="visitas" class="form-control" value="<?= intval($video_editar['visitas']) ?>">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Likes</label>
                            <input type="number" name="likes" class="form-control" value="<?= intval($video_editar['likes']) ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tags Asignados</label>
                            <select name="tags_select[]" class="form-select" multiple size="4">
                                <?php foreach ($tags_existentes as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= in_array($t['id'], $tags_video_editar) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning font-weight-bold">Guardar Cambios</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- TABLA DE LISTADO DE VIDEOS -->
    <div class="card bg-secondary text-white shadow">
        <div class="card-header">
            <h5 class="mb-0">Listado de Videos Cargas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Miniatura</th>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Visitas</th>
                            <th>Likes</th>
                            <th>Duración</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_videos as $vid): ?>
                            <tr>
                                <td style="width: 100px;">
                                    <?php if (!empty($vid['archivo_thumb'])): ?>
                                        <img src="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>" class="img-fluid rounded" alt="Thumb">
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin Thumb</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $vid['id'] ?></td>
                                <td><?= htmlspecialchars($vid['titulo']) ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($vid['categoria_nombre'] ?? 'Sin categoría') ?>
                                    </span>
                                </td>
                                <td><?= $vid['visitas'] ?></td>
                                <td><?= $vid['likes'] ?></td>
                                <td><?= htmlspecialchars($vid['duracion']) ?></td>
                                <td>
                                    <a href="admin3.php?editar_id=<?= $vid['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="admin3.php?eliminar_id=<?= $vid['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este video?')">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lista_videos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-3">No hay videos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
