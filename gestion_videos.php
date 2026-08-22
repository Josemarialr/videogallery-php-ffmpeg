
<?php
$titulo_pagina = "Gestión de Videos";
require_once 'header.php';

// Verificar permisos de administrador
if (!esAdmin()) {
    header("Location: login.php");
    exit;
}

$mensaje = "";
$error = "";

// --------------------------------------------------
// 1. ELIMINAR VIDEO Y ARCHIVOS FÍSICOS
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_video'])) {
    $video_id = intval($_POST['video_id']);

    // Obtener nombres de archivo para borrarlos del servidor
    $stmt = $pdo->prepare("SELECT archivo_video, archivo_thumb, archivo_preview FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $vid = $stmt->fetch();

    if ($vid) {
        // Eliminar archivos físicos del disco
        @unlink('uploads/videos/' . $vid['archivo_video']);
        @unlink('uploads/thumbs/' . $vid['archivo_thumb']);
        @unlink('uploads/previews/' . $vid['archivo_preview']);

        // Eliminar de la base de datos (relaciones en video_tags y comentarios se borran automáticamente si usas CASCADE)
        $stmt_del = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt_del->execute([$video_id]);

        $mensaje = "El video y sus archivos asociados fueron eliminados correctamente.";
    } else {
        $error = "El video no existe o ya fue eliminado.";
    }
}

// --------------------------------------------------
// 2. ACTUALIZAR DATOS DEL VIDEO
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_video'])) {
    $video_id           = intval($_POST['video_id']);
    $titulo             = trim($_POST['titulo']);

    $categoria_id       = intval($_POST['categoria_id']);
    $tags_seleccionados = $_POST['tags_select'] ?? [];

    if (!empty($titulo) && $categoria_id > 0) {
        // Actualizar datos principales
        $stmt_up = $pdo->prepare("UPDATE videos SET titulo = ?,  categoria_id = ? WHERE id = ?");
        $stmt_up->execute([$titulo,  $categoria_id, $video_id]);

        // Sincronizar etiquetas: borrar relaciones anteriores e insertar las nuevas
        $pdo->prepare("DELETE FROM video_tags WHERE video_id = ?")->execute([$video_id]);
        foreach ($tags_seleccionados as $tag_id) {
            $stmt_vtag = $pdo->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)");
            $stmt_vtag->execute([$video_id, intval($tag_id)]);
        }

        $mensaje = "Información del video actualizada con éxito.";
    } else {
        $error = "Por favor completa el título y selecciona una categoría válida.";
    }
}

// --------------------------------------------------
// 3. OBTENER LISTA DE VIDEOS Y METADATOS
// --------------------------------------------------
$stmt = $pdo->prepare("SELECT v.*, c.nombre as categoria
                       FROM videos v
                       LEFT JOIN categorias c ON v.categoria_id = c.id
                       ORDER BY v.creado_en DESC");
$stmt->execute();
$videos = $stmt->fetchAll();

// Cargar listas para el modal de edición
$categorias      = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$tags_existentes = $pdo->query("SELECT * FROM tags ORDER BY nombre ASC")->fetchAll();

// Mapear etiquetas asignadas a cada video
$video_tags_map = [];
$stmt_vt = $pdo->query("SELECT video_id, tag_id FROM video_tags");
while ($row = $stmt_vt->fetch()) {
    $video_tags_map[$row['video_id']][] = $row['tag_id'];
}
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📹 Administración de Videos</h2>
        <a href="admin.php" class="btn btn-outline-light btn-sm">➕ Cargar Nuevo Video</a>
    </div>

    <!-- ALERTAS -->
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

    <!-- TABLA DE VIDEOS -->
    <div class="card card-custom shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Miniatura</th>
                            <th>Título y Descripción</th>
                            <th>Categoría</th>
                            <th>Duración</th>
                            <th>Estadísticas</th>
                            <th class="text-end" style="width: 180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($videos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-secondary">
                                    No hay videos cargados en la plataforma.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($videos as $v): ?>
                                <?php $vid_tags = $video_tags_map[$v['id']] ?? []; ?>
                                <tr>
                                    <td>
                                        <img src="uploads/thumbs/<?= htmlspecialchars($v['archivo_thumb']) ?>"
                                             class="img-fluid rounded border border-secondary"
                                             style="width: 80px; height: 50px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <a href="vervideo.php?id=<?= $v['id'] ?>" class="text-white text-decoration-none fw-bold d-block">
                                            <?= htmlspecialchars($v['titulo']) ?>
                                        </a>

                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($v['categoria'] ?? 'Sin categoría') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-light"><?= htmlspecialchars($v['duracion'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <small class="text-secondary d-block">👁️ <?= number_format($v['visitas']) ?></small>
                                        <small class="text-secondary d-block">👍 <?= number_format($v['likes']) ?></small>
                                    </td>
                                    <td class="text-end">
                                        <!-- Botón Editar -->
                                        <button class="btn btn-sm btn-primary me-1 btn-editar"
                                                data-id="<?= $v['id'] ?>"
                                                data-titulo="<?= htmlspecialchars($v['titulo'], ENT_QUOTES) ?>"

                                                data-categoria="<?= $v['categoria_id'] ?>"
                                                data-tags='<?= json_encode($vid_tags) ?>'>
                                            ✏️ Editar
                                        </button>

                                        <!-- Formulario Eliminar -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas que deseas eliminar este video? Se borrarán sus archivos del servidor.');">
                                            <input type="hidden" name="eliminar_video" value="1">
                                            <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">🗑️ Borrar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDICIÓN -->
<div class="modal fade" id="modalEditarVideo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">✏️ Editar Video</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="editar_video" value="1">
        <input type="hidden" name="video_id" id="edit_video_id">

        <div class="modal-body">
            <div class="mb-3">
                <label for="edit_titulo" class="form-label">Título</label>
                <input type="text" name="titulo" id="edit_titulo" class="form-control bg-secondary text-white border-0" required>
            </div>



            <div class="mb-3">
                <label for="edit_categoria_id" class="form-label">Categoría</label>
                <select name="categoria_id" id="edit_categoria_id" class="form-select bg-secondary text-white border-0" required>
                    <option value="">-- Seleccionar Categoría --</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="edit_tags" class="form-label">Etiquetas / Tags (Ctrl/Cmd para selección múltiple)</label>
                <select name="tags_select[]" id="edit_tags" class="form-select bg-secondary text-white border-0" multiple size="5">
                    <?php foreach ($tags_existentes as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SCRIPT JS PARA RELLENAR EL MODAL -->
<script>
$(document).ready(function() {
    $('.btn-editar').click(function() {
        const id = $(this).data('id');
        const titulo = $(this).data('titulo');

        const categoria = $(this).data('categoria');
        const tags = $(this).data('tags'); // Array con IDs de tags

        $('#edit_video_id').val(id);
        $('#edit_titulo').val(titulo);

        $('#edit_categoria_id').val(categoria);

        // Desmarcar selecciones anteriores y marcar las correspondientes
        $('#edit_tags option').prop('selected', false);
        if (tags && Array.isArray(tags)) {
            tags.forEach(function(tagId) {
                $('#edit_tags option[value="' + tagId + '"]').prop('selected', true);
            });
        }

        // Mostrar Modal de Bootstrap 5
        const modal = new bootstrap.Modal(document.getElementById('modalEditarVideo'));
        modal.show();
    });
});
</script>

<?php require_once 'footer.php'; ?>
