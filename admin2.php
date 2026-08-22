<?php
require_once 'config.php';
require_once 'header.php';

if (!esAdmin()) {
    header("Location: login.php");
    exit;
}

$mensaje = "";
$error = "";

// Configuración de la Paginación
$videos_por_pagina = 15;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $videos_por_pagina;

// PROCESAR BORRADO DE VIDEO
if (isset($_GET['eliminar_id'])) {
    $id_eliminar = intval($_GET['eliminar_id']);

    // Obtener los nombres de archivos si deseas eliminarlos del disco
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
    $creado_en     = trim($_POST['creado_en'] ?? '');
    $tags_selected = $_POST['tags_select'] ?? [];

    if ($video_id > 0 && !empty($titulo) && $categoria_id > 0 && !empty($creado_en)) {
        // Formatear la fecha ingresada para asegurar formato de MySQL (YYYY-MM-DD HH:MM:SS)
        $fecha_mysql = date('Y-m-d H:i:s', strtotime($creado_en));

        // Actualizar datos del video (incluyendo la fecha)
        $stmt_upd = $pdo->prepare("UPDATE videos SET titulo = ?, categoria_id = ?, visitas = ?, likes = ?, creado_en = ? WHERE id = ?");
        $stmt_upd->execute([$titulo, $categoria_id, $visitas, $likes, $fecha_mysql, $video_id]);

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

// CONTEO TOTAL PARA LA PAGINACIÓN
$total_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn() ?? 0;
$total_paginas = ceil($total_videos / $videos_por_pagina);

// LISTAR VIDEOS CON LIMIT Y OFFSET
$sql_videos = "SELECT v.*, c.nombre AS categoria_nombre
               FROM videos v
               LEFT JOIN categorias c ON v.categoria_id = c.id
               ORDER BY v.id DESC
               LIMIT ? OFFSET ?";

$stmt_list = $pdo->prepare($sql_videos);
$stmt_list->bindValue(1, intval($videos_por_pagina), PDO::PARAM_INT);
$stmt_list->bindValue(2, intval($offset), PDO::PARAM_INT);
$stmt_list->execute();
$lista_videos = $stmt_list->fetchAll();

$categorias      = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$tags_existentes = $pdo->query("SELECT * FROM tags ORDER BY nombre ASC")->fetchAll();

// Función auxiliar para construir URLs conservando la página actual
function obtenerUrlPagina($num_pagina) {
    $params = $_GET;
    $params['pagina'] = $num_pagina;
    return 'admin2.php?' . http_build_query($params);
}
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
        <?php
            // Formatear la fecha para que la interprete correctamente el input tipo datetime-local (YYYY-MM-DDTHH:MM)
            $fecha_formato_input = !empty($video_editar['creado_en']) ? date('Y-m-d\TH:i', strtotime($video_editar['creado_en'])) : '';
        ?>
        <div class="card bg-secondary text-white shadow mb-5 border-warning">
            <div class="card-header bg-warning text-dark font-weight-bold d-flex justify-content-between">
                <h5 class="mb-0">Editando Video ID #<?= $video_editar['id'] ?></h5>
                <a href="<?= obtenerUrlPagina($pagina_actual) ?>" class="btn btn-sm btn-dark">Cancelar Edición</a>
            </div>
            <div class="card-body">
                <form action="admin2.php?pagina=<?= $pagina_actual ?>&editar_id=<?= $video_editar['id'] ?>" method="POST">
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
                            <label class="form-label">Fecha de Creación</label>
                            <input type="datetime-local" name="creado_en" class="form-control" value="<?= $fecha_formato_input ?>" required>
                        </div>

                        <div class="col-md-12 mb-3">
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Listado de Videos Cargados</h5>
            <small class="text-light">Total: <?= $total_videos ?> videos</small>
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
                            <th>Fecha</th>
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
                                <td><small><?= !empty($vid['creado_en']) ? date('d/m/Y H:i', strtotime($vid['creado_en'])) : '-' ?></small></td>
                                <td><?= htmlspecialchars($vid['duracion'] ?? '') ?></td>
                                <td>
                                    <a href="admin2.php?pagina=<?= $pagina_actual ?>&editar_id=<?= $vid['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="admin2.php?eliminar_id=<?= $vid['id'] ?>&pagina=<?= $pagina_actual ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este video?')">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lista_videos)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-3">No hay videos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- NAVEGACIÓN DE PAGINACIÓN -->
    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de admin" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="<?= obtenerUrlPagina($pagina_actual - 1) ?>">Anterior</a>
                </li>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= ($pagina_actual == $i) ? 'active' : '' ?>">
                        <a class="page-link <?= ($pagina_actual == $i) ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="<?= obtenerUrlPagina($i) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="<?= obtenerUrlPagina($pagina_actual + 1) ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></script>
</body>
</html>
