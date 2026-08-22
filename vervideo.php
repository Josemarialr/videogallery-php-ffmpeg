<?php
$titulo_pagina = "Ver Video";
require_once 'header.php';

// Asegurar que la sesión esté iniciada para leer $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = intval($_GET['id'] ?? 0);

// Incrementar contador global de visitas
if ($id > 0) {
    $pdo->prepare("UPDATE videos SET visitas = visitas + 1 WHERE id = ?")->execute([$id]);
}

// Registrar en el historial personal si el usuario está autenticado
if (estaAutenticado() && !empty($_SESSION['usuario_id'])) {
    $stmt_hist = $pdo->prepare("INSERT INTO historial_visitas (usuario_id, video_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE visitado_en = CURRENT_TIMESTAMP");
    $stmt_hist->execute([$_SESSION['usuario_id'], $id]);
}

// Obtener datos del video
$stmt = $pdo->prepare("SELECT v.*, c.nombre as categoria, u.nombre as usuario FROM videos v LEFT JOIN categorias c ON v.categoria_id = c.id LEFT JOIN usuarios u ON v.usuario_id = u.id WHERE v.id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) {
    echo "<div class='container my-5'><h2>Video no encontrado.</h2></div>";
    require_once 'footer.php';
    exit;
}

// Verificar si este video ya está en la playlist del usuario
$en_playlist = false;
if (estaAutenticado() && !empty($_SESSION['usuario_id'])) {
    $stmt_pl = $pdo->prepare("SELECT id FROM playlist WHERE usuario_id = ? AND video_id = ?");
    $stmt_pl->execute([$_SESSION['usuario_id'], $id]);
    $en_playlist = (bool)$stmt_pl->fetch();
}

// Obtener Tags
$stmt_tags = $pdo->prepare("SELECT t.id, t.nombre FROM tags t JOIN video_tags vt ON t.id = vt.tag_id WHERE vt.video_id = ?");
$stmt_tags->execute([$id]);
$tags = $stmt_tags->fetchAll();

// Obtener Comentarios
$stmt_comments = $pdo->prepare("SELECT c.*, COALESCE(u.nombre, 'Usuario') as usuario_nombre
                                FROM comentarios c
                                LEFT JOIN usuarios u ON c.usuario_id = u.idu
                                WHERE c.video_id = ?
                                ORDER BY c.creado_en DESC");
$stmt_comments->execute([$id]);
$comentarios = $stmt_comments->fetchAll();

// Obtener Primeros 6 Videos Relacionados
$stmt_rel = $pdo->prepare("SELECT v.*, c.nombre as categoria FROM videos v LEFT JOIN categorias c ON v.categoria_id = c.id WHERE v.id != ? AND v.categoria_id = ? ORDER BY v.creado_en DESC LIMIT 6");
$stmt_rel->execute([$id, $video['categoria_id'] ?? 0]);
$relacionados = $stmt_rel->fetchAll();
?>

<div class="container my-4">
    <div class="row">
        <!-- COLUMNA PRINCIPAL -->
        <div class="col-lg-8 mb-4">
            <!-- REPRODUCTOR CON OVERLAY DE CARGA -->
            <div class="ratio ratio-16x9 bg-black rounded shadow position-relative overflow-hidden">
                <!-- PRELOADER OVERLAY -->
                <div id="video-preloader" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center bg-dark bg-opacity-75 text-white" style="z-index: 10; display: none !important;">
                    <div class="spinner-border text-info mb-2" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <span id="preloader-text" class="fw-bold fs-5">0%</span>
                </div>

                <video id="reproductor-video" controls autoplay muted poster="uploads/thumbs/<?= htmlspecialchars($video['archivo_thumb'] ?? '') ?>">
                    <source src="uploads/videos/<?= htmlspecialchars($video['archivo_video'] ?? '') ?>" type="video/mp4">
                    Tu navegador no soporta el reproductor.
                </video>
            </div>

            <!-- TÍTULO Y BOTONES DE ACCIÓN -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <h3 class="mb-0"><?= htmlspecialchars($video['titulo'] ?? '') ?></h3>
                <div class="d-flex gap-2">
                    <!-- BOTÓN PLAYLIST -->
                    <?php if (estaAutenticado()): ?>
                        <button id="btn-playlist" data-id="<?= $video['id'] ?>" class="btn <?= $en_playlist ? 'btn-warning' : 'btn-outline-warning' ?> btn-sm">
                            <span id="playlist-text"><?= $en_playlist ? '★ En Playlist' : '☆ Guardar en Playlist' ?></span>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalLogin" onclick="alert('Inicia sesión para guardar en tu playlist.');">
                            ☆ Guardar en Playlist
                        </button>
                    <?php endif; ?>

                    <button id="btn-like" data-id="<?= $video['id'] ?>" class="btn btn-outline-primary btn-sm">
                        👍 <span id="likes-count"><?= $video['likes'] ?? 0 ?></span>
                    </button>

                    <!-- BOTÓN QUE ABRE LA VENTANA EMERGENTE -->
                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalEnviar">
                        📤 Enviar
                    </button>

                    <!-- BOTÓN DESCARGA CONDICIONAL -->
                    <?php if (estaAutenticado()): ?>
                        <a href="uploads/videos/<?= htmlspecialchars($video['archivo_video'] ?? '') ?>"
                           download="<?= htmlspecialchars($video['titulo'] ?? 'video') ?>.mp4"
                           class="btn btn-success btn-sm">
                            ⬇️ Descargar
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalLogin" onclick="alert('Inicia sesión para descargar este video.');">
                            ⬇️ Descargar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DATOS META -->
            <div class="d-flex align-items-center gap-3 my-2 text-secondary small">
                <span>👁️ <?= number_format($video['visitas'] ?? 0) ?> visitas</span>
                <span>📂 Categoría: <strong><?= htmlspecialchars($video['categoria'] ?? 'General') ?></strong></span>
                <span>⏱️ Duración: <strong><?= htmlspecialchars($video['duracion'] ?? 'N/A') ?></strong></span>
            </div>

            <!-- TAGS (Muestra max 5 y botón + si hay más) -->
            <div class="mb-3">
                <?php
                $limite_tags = 5;
                $total_tags = count($tags);
                $primeros_tags = array_slice($tags, 0, $limite_tags);
                $resto_tags = array_slice($tags, $limite_tags);
                ?>

                <!-- Primeros 5 Tags -->
                <?php foreach ($primeros_tags as $t): ?>
                    <a href="index.php?tag=<?= urlencode($t['nombre']) ?>" class="badge bg-secondary text-white text-decoration-none me-1">
                        #<?= htmlspecialchars($t['nombre']) ?>
                    </a>
                <?php endforeach; ?>

                <!-- Tags Ocultos y Botón + -->
                <?php if ($total_tags > $limite_tags): ?>
                    <span id="tags-extra" style="display: none;">
                        <?php foreach ($resto_tags as $t): ?>
                            <a href="index.php?tag=<?= urlencode($t['nombre']) ?>" class="badge bg-secondary text-white text-decoration-none me-1">
                                #<?= htmlspecialchars($t['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </span>

                    <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 align-baseline" onclick="$(this).hide(); $('#tags-extra').fadeIn();">
                        +<?= $total_tags - $limite_tags ?>
                    </button>
                <?php endif; ?>
            </div>

            <p class="text-light bg-dark p-3 rounded border border-secondary"><?= nl2br(htmlspecialchars($video['descripcion'] ?? '')) ?></p>

            <hr class="border-secondary my-4">

            <!-- COMENTARIOS -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">💬 Comentarios (<span id="comentarios-count"><?= count($comentarios) ?></span>)</h5>
                <?php if (estaAutenticado()): ?>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalComentario">✍️ Dejar un comentario</button>
                <?php else: ?>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalLogin">Inicia sesión para comentar</button>
                <?php endif; ?>
            </div>

            <div id="lista-comentarios" class="d-flex flex-column gap-3">
                <?php if (empty($comentarios)): ?>
                    <div id="sin-comentarios" class="text-secondary small italic">Aún no hay comentarios. ¡Sé el primero en comentar!</div>
                <?php else: ?>
                    <?php foreach ($comentarios as $com): ?>
                        <div class="card card-custom p-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong class="text-info"><?= htmlspecialchars($com['usuario_nombre']) ?></strong>
                                <small class="text-secondary"><?= date('d/m/Y H:i', strtotime($com['creado_en'])) ?></small>
                            </div>
                            <p class="mb-0 small text-light"><?= nl2br(htmlspecialchars($com['comentario'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RELACIONADOS LATERAL -->
        <div class="col-lg-4">
            <h5 class="mb-3">Videos Relacionados</h5>
            <div id="contenedor-relacionados" class="row row-cols-1 g-3">
                <?php foreach ($relacionados as $rel): ?>
                    <div class="col">
                        <div class="card card-custom h-100 shadow-sm">
                            <a href="vervideo.php?id=<?= $rel['id'] ?>">
                                <div class="thumb-container">
                                    <img src="uploads/thumbs/<?= htmlspecialchars($rel['archivo_thumb'] ?? '') ?>" class="js-thumb-preview" data-static="uploads/thumbs/<?= htmlspecialchars($rel['archivo_thumb'] ?? '') ?>" data-gif="uploads/previews/<?= htmlspecialchars($rel['archivo_preview'] ?? '') ?>" alt="<?= htmlspecialchars($rel['titulo'] ?? '') ?>">
                                    <?php if(!empty($rel['duracion'])): ?>
                                        <span class="duration-badge"><?= htmlspecialchars($rel['duracion']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="card-body p-2">
                                <h6 class="card-title mb-1 small">
                                    <a href="vervideo.php?id=<?= $rel['id'] ?>" class="text-white text-decoration-none"><?= htmlspecialchars($rel['titulo'] ?? '') ?></a>
                                </h6>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL COMENTARIO -->
<?php if (estaAutenticado()): ?>
<div class="modal fade" id="modalComentario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">💬 Publicar Comentario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="form-comentario">
        <input type="hidden" name="video_id" value="<?= $id ?>">
        <div class="modal-body">
            <div class="mb-3">
                <label for="comentario_texto" class="form-label">Escribe tu opinión</label>
                <textarea name="comentario" id="comentario_texto" class="form-control bg-secondary text-white border-0" rows="4" placeholder="¿Qué opinas sobre este video?" required></textarea>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-enviar-comentario" class="btn btn-primary">Publicar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- MODAL ENVIAR VÍA WIREPUSHER -->
<div class="modal fade" id="modalEnviar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">📤 Enviar Video vía Wirepusher</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="send.php" method="POST">
        <div class="modal-body">
            <?php
            $stmt_wp_users = $pdo->query("SELECT id, nombre FROM usuarios WHERE id IS NOT NULL AND id != '' ORDER BY nombre ASC");
            $usuarios_wp = $stmt_wp_users->fetchAll();

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $uri_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $base_url = $protocol . "://" . $host . $uri_path;

            $video_url = $base_url . "/vervideo.php?id=" . $video['id'];
            $thumb_url = $base_url . "/uploads/thumbs/" . ($video['archivo_thumb'] ?? '');
            ?>

            <div class="mb-3">
                <label for="select-usuario-wp" class="form-label">Destinatario:</label>
                <select class="form-control bg-secondary text-white border-0" name="id" id="select-usuario-wp" required>
                    <option value="">Enviar vía Wirepusher a?</option>
                    <?php foreach ($usuarios_wp as $valores): ?>
                        <option value="<?= htmlspecialchars($valores['id']) ?>">
                            <?= htmlspecialchars($valores['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="title" value="Te enviaron un link">
            <input type="hidden" name="message" value="Te enviaron un link para que veas un video haciendo click en este mensaje">
            <input type="hidden" name="type" value="video">
            <input type="hidden" name="action" value="<?= htmlspecialchars($video_url) ?>">
            <input type="hidden" name="image_url" value="<?= htmlspecialchars($thumb_url) ?>">
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-danger">ENVIAR VIDEO</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    // --- CONTROL DE PRELOADER Y BUFFER DEL VIDEO ---
    const videoElement = $('#reproductor-video')[0];
    const $preloader = $('#video-preloader');
    const $preloaderText = $('#preloader-text');

    function mostrarPreloader() {
        $preloader.removeClass('d-none').css('display', 'flex');
    }

    function ocultarPreloader() {
        $preloader.addClass('d-none').css('display', 'none');
    }

    function actualizarPorcentajeCarga() {
        if (!videoElement || !videoElement.duration) return;

        const currentTime = videoElement.currentTime;
        const buffered = videoElement.buffered;
        let percent = 0;

        for (let i = 0; i < buffered.length; i++) {
            if (buffered.start(i) <= currentTime && currentTime <= buffered.end(i)) {
                const bufferedEnd = buffered.end(i);
                const duration = videoElement.duration;
                percent = Math.round((bufferedEnd / duration) * 100);
                break;
            }
        }

        $preloaderText.text(percent + '%');
    }

    if (videoElement) {
        $(videoElement).on('waiting seeking loadstart', function() {
            mostrarPreloader();
            actualizarPorcentajeCarga();
        });

        $(videoElement).on('progress timeupdate', function() {
            actualizarPorcentajeCarga();
        });

        $(videoElement).on('canplay playing seeked', function() {
            ocultarPreloader();
        });
    }

    // 1. GUARDAR COMENTARIO VÍA AJAX
    $('#form-comentario').submit(function(e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $('#btn-enviar-comentario');

        $btn.prop('disabled', true).text('Publicando...');

        $.post('guardar_comentario_ajax.php', $form.serialize(), function(response) {
            $btn.prop('disabled', false).text('Publicar');

            if (response && response.success) {
                $('#comentario_texto').val('');

                const modalElement = document.getElementById('modalComentario');
                if (modalElement && typeof bootstrap !== 'undefined') {
                    const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                    modalInstance.hide();
                }

                $('#sin-comentarios').remove();

                const nuevoComentarioHtml = `
                    <div class="card card-custom p-3">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="text-info">${response.usuario_nombre}</strong>
                            <small class="text-secondary">${response.creado_en}</small>
                        </div>
                        <p class="mb-0 small text-light">${response.comentario}</p>
                    </div>
                `;
                $('#lista-comentarios').prepend(nuevoComentarioHtml);

                const $cnt = $('#comentarios-count');
                $cnt.text((parseInt($cnt.text()) || 0) + 1);

            } else {
                alert((response && response.message) ? response.message : 'Ocurrió un error al publicar el comentario.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).text('Publicar');
            alert('Error de conexión con el servidor.');
        });
    });

    // 2. ACCIÓN GUARDAR/QUITAR PLAYLIST AJAX
    $('#btn-playlist').click(function() {
        const videoId = $(this).data('id');
        const $btn = $(this);

        $.post('guardar_playlist_ajax.php', { video_id: videoId }, function(response) {
            if (response && response.success) {
                if (response.action === 'added') {
                    $btn.removeClass('btn-outline-warning').addClass('btn-warning');
                    $('#playlist-text').text('★ En Playlist');
                } else {
                    $btn.removeClass('btn-warning').addClass('btn-outline-warning');
                    $('#playlist-text').text('☆ Guardar en Playlist');
                }
            } else {
                alert((response && response.message) ? response.message : 'Error al procesar la playlist.');
            }
        }, 'json').fail(function() {
            alert('Error de conexión con el servidor.');
        });
    });

    // 3. LIKE AJAX
    $('#btn-like').click(function() {
        const videoId = $(this).data('id');
        $.post('like_ajax.php', { video_id: videoId }, function(response) {
            if (response && response.success) {
                $('#likes-count').text(response.likes);
            } else {
                alert((response && response.message) ? response.message : 'Error al registrar el me gusta.');
            }
        }, 'json').fail(function() {
            alert('Error de conexión con el servidor.');
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>
