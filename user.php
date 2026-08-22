<?php
$titulo_pagina = "Mi Cuenta";
require_once 'header.php';

if (!estaAutenticado()) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// 1. Datos del Usuario
$stmt_u = $pdo->prepare("SELECT * FROM usuarios WHERE idu = ?");
$stmt_u->execute([$usuario_id]);
$usuario = $stmt_u->fetch();

// 2. Playlist del Usuario
$stmt_pl = $pdo->prepare("SELECT v.*, c.nombre as categoria, p.creado_en as guardado_en
                          FROM playlist p
                          JOIN videos v ON p.video_id = v.id
                          LEFT JOIN categorias c ON v.categoria_id = c.id
                          WHERE p.usuario_id = ?
                          ORDER BY p.creado_en DESC");
$stmt_pl->execute([$usuario_id]);
$playlist = $stmt_pl->fetchAll();

// 3. Historial de Visitas
$stmt_h = $pdo->prepare("SELECT v.*, c.nombre as categoria, h.visitado_en
                         FROM historial_visitas h
                         JOIN videos v ON h.video_id = v.id
                         LEFT JOIN categorias c ON v.categoria_id = c.id
                         WHERE h.usuario_id = ?
                         ORDER BY h.visitado_en DESC");
$stmt_h->execute([$usuario_id]);
$historial = $stmt_h->fetchAll();
?>

<div class="container my-4">
    <!-- FICHA DEL USUARIO -->
    <div class="card card-custom mb-4 shadow">
        <div class="card-body p-4 d-flex align-items-center gap-4 flex-wrap">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2" style="width: 70px; height: 70px;">
                <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
            </div>
            <div>
                <h3 class="mb-1">
                    <a href="user2.php" class="text-white text-decoration-none">
                        <?= htmlspecialchars($usuario['nombre']) ?>
                    </a>
                </h3>
                <p class="text-secondary mb-0">📧 <?= htmlspecialchars($usuario['email']) ?> | 👤 Rol: <strong><?= strtoupper($usuario['rol'] ?? 'Usuario') ?></strong></p>
            </div>
        </div>
    </div>

    <!-- PESTAÑAS PLAYLIST / HISTORIAL -->
    <ul class="nav nav-tabs border-secondary mb-4" id="userTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active bg-dark text-white border-secondary" id="playlist-tab" data-bs-toggle="tab" data-bs-target="#playlist-content" type="button" role="tab">
                ⭐ Mi Playlist (<?= count($playlist) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link bg-dark text-white border-secondary ms-2" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial-content" type="button" role="tab">
                🕒 Historial de Visitas (<?= count($historial) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="userTabsContent">
        <!-- SECCIÓN 1: PLAYLIST -->
        <div class="tab-pane fade show active" id="playlist-content" role="tabpanel">
            <?php if (empty($playlist)): ?>
                <div class="alert alert-secondary bg-dark text-light border-secondary text-center py-4">
                    No tienes ningún video guardado en tu playlist.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($playlist as $vid): ?>
                        <div class="col" id="item-pl-<?= $vid['id'] ?>">
                            <div class="card card-custom h-100 shadow-sm">
                                <a href="vervideo.php?id=<?= $vid['id'] ?>">
                                    <div class="thumb-container">
                                        <img src="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                             data-static="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                             data-gif="uploads/previews/<?= htmlspecialchars($vid['archivo_preview']) ?>"
                                             class="js-thumb-preview" alt="<?= htmlspecialchars($vid['titulo']) ?>">
                                        <?php if (!empty($vid['duracion'])): ?>
                                            <span class="duration-badge"><?= htmlspecialchars($vid['duracion']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <h6 class="card-title mb-1 text-truncate">
                                            <a href="vervideo.php?id=<?= $vid['id'] ?>" class="text-white text-decoration-none">
                                                <?= htmlspecialchars($vid['titulo']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-secondary d-block"><?= htmlspecialchars($vid['categoria'] ?? 'Sin categoría') ?></small>
                                    </div>
                                    <div class="pt-2 border-top border-secondary mt-2 d-flex justify-content-between align-items-center">
                                        <small class="text-secondary" style="font-size: 0.75rem;">Guardado: <?= date('d/m/Y', strtotime($vid['guardado_en'])) ?></small>
                                        <button class="btn btn-sm btn-outline-danger btn-quitar-pl" data-id="<?= $vid['id'] ?>" title="Quitar de playlist">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECCIÓN 2: HISTORIAL -->
        <div class="tab-pane fade" id="historial-content" role="tabpanel">
            <?php if (empty($historial)): ?>
                <div class="alert alert-secondary bg-dark text-light border-secondary text-center py-4" id="alert-historial-vacio">
                    Aún no has visto ningún video.
                </div>
            <?php else: ?>
                <!-- Botón Borrar Todo -->
                <div class="d-flex justify-content-end mb-3" id="contenedor-borrar-todo">
                    <button class="btn btn-outline-danger btn-sm" id="btn-vaciar-historial">
                        🗑️ Vaciar todo el historial
                    </button>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="grid-historial">
                    <?php foreach ($historial as $vid): ?>
                        <div class="col" id="item-historial-<?= $vid['id'] ?>">
                            <div class="card card-custom h-100 shadow-sm">
                                <a href="vervideo.php?id=<?= $vid['id'] ?>">
                                    <div class="thumb-container">
                                        <img src="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                             data-static="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                             data-gif="uploads/previews/<?= htmlspecialchars($vid['archivo_preview']) ?>"
                                             class="js-thumb-preview" alt="<?= htmlspecialchars($vid['titulo']) ?>">
                                        <?php if (!empty($vid['duracion'])): ?>
                                            <span class="duration-badge"><?= htmlspecialchars($vid['duracion']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <h6 class="card-title mb-1 text-truncate">
                                            <a href="vervideo.php?id=<?= $vid['id'] ?>" class="text-white text-decoration-none">
                                                <?= htmlspecialchars($vid['titulo']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-secondary d-block"><?= htmlspecialchars($vid['categoria'] ?? 'Sin categoría') ?></small>
                                    </div>
                                    <div class="pt-2 border-top border-secondary mt-2 d-flex justify-content-between align-items-center">
                                        <small class="text-secondary" style="font-size: 0.75rem;">Visto el: <?= date('d/m/Y H:i', strtotime($vid['visitado_en'])) ?></small>
                                        <button class="btn btn-sm btn-outline-danger btn-quitar-historial" data-id="<?= $vid['id'] ?>" title="Eliminar de historial">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Previsualización en GIF
    $(document).on('mouseenter', '.js-thumb-preview', function() {
        const gifUrl = $(this).data('gif');
        if (gifUrl) $(this).attr('src', gifUrl);
    }).on('mouseleave', '.js-thumb-preview', function() {
        const staticUrl = $(this).data('static');
        if (staticUrl) $(this).attr('src', staticUrl);
    });

    // Quitar de la playlist dinámicamente
    $('.btn-quitar-pl').click(function() {
        const videoId = $(this).data('id');
        $.post('guardar_playlist_ajax.php', { video_id: videoId }, function(response) {
            if (response.success) {
                $('#item-pl-' + videoId).fadeOut(300, function() { $(this).remove(); });
            }
        }, 'json');
    });

    // 1. Borrar un elemento individual del historial
    $(document).on('click', '.btn-quitar-historial', function() {
        const videoId = $(this).data('id');

        $.post('borrar_historial_ajax.php', { accion: 'borrar_item', video_id: videoId }, function(response) {
            if (response.success) {
                $('#item-historial-' + videoId).fadeOut(300, function() {
                    $(this).remove();

                    // Si ya no quedan elementos en el historial, mostramos la alerta de historial vacío
                    if ($('#grid-historial .col').length === 0) {
                        $('#contenedor-borrar-todo').remove();
                        $('#historial-content').html(`
                            <div class="alert alert-secondary bg-dark text-light border-secondary text-center py-4">
                                Aún no has visto ningún video.
                            </div>
                        `);
                    }
                });
            } else {
                alert('Ocurrió un error al intentar eliminar el registro.');
            }
        }, 'json');
    });

    // 2. Vaciar todo el historial
    $('#btn-vaciar-historial').click(function() {
        if (confirm('¿Estás seguro de que deseas vaciar todo tu historial de vistas?')) {
            $.post('borrar_historial_ajax.php', { accion: 'borrar_todo' }, function(response) {
                if (response.success) {
                    $('#contenedor-borrar-todo').fadeOut(200);
                    $('#grid-historial').fadeOut(300, function() {
                        $('#historial-content').html(`
                            <div class="alert alert-secondary bg-dark text-light border-secondary text-center py-4">
                                Aún no has visto ningún video.
                            </div>
                        `);
                    });
                } else {
                    alert('Ocurrió un error al vaciar el historial.');
                }
            }, 'json');
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>
