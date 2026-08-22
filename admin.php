<?php
require_once 'config.php';
require_once 'header.php';

if (!esAdmin()) {
    header("Location: login.php");
    exit;
}

$mensaje = "";
$error = "";

// 1. PROCESAR CREACIÓN DE CATEGORÍA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_categoria'])) {
    $nombre_cat = trim($_POST['nombre_categoria']);
    if (!empty($nombre_cat)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre_cat)));
        $stmt_cat = $pdo->prepare("INSERT IGNORE INTO categorias (nombre, slug) VALUES (?, ?)");
        $stmt_cat->execute([$nombre_cat, $slug]);
        $mensaje = "Categoría '" . htmlspecialchars($nombre_cat) . "' creada con éxito.";
    }
}

// 2. PASO 1: SUBIR VIDEO TEMPORAL SI SE ELIGIÓ CORTAR
$video_para_cortar = null;
$datos_post_temporales = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_video']) && isset($_FILES['video'])) {
    $cortar_activado = isset($_POST['cortar_video']) && $_POST['cortar_video'] == '1';

    if ($_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $dir_videos = 'uploads/videos/';
        if (!file_exists($dir_videos)) mkdir($dir_videos, 0777, true);

        if ($cortar_activado) {
            // Guardamos archivo temporalmente para la pantalla de recortes
            $ext_video = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $temp_name = uniqid('temp_') . '.' . $ext_video;
            $ruta_temp = $dir_videos . $temp_name;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $ruta_temp)) {
                $video_para_cortar = $temp_name;
                $datos_post_temporales = [
                    'titulo' => trim($_POST['titulo'] ?? ''),
                    'categoria_id' => intval($_POST['categoria_id'] ?? 0),
                    'tags_select' => $_POST['tags_select'] ?? [],
                    'tags_nuevos' => trim($_POST['tags_nuevos'] ?? '')
                ];
            } else {
                $error = "Error al mover el archivo temporal para recortar.";
            }
        }
    } else if (!isset($_POST['procesar_corte'])) {
        $error = "Error al subir el archivo (Código PHP: " . $_FILES['video']['error'] . "). Revisa el límite de tamaño de subida.";
    }
}

// 3. PASO 2: PROCESAR SUBIDA FINAL (DIRECTA O TRAS EL CORTE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['procesar_corte']) || (isset($_POST['subir_video']) && !$video_para_cortar && empty($error)))) {

    $ffmpeg_bin = defined('FFMPEG_PATH') ? FFMPEG_PATH : '/usr/local/bin/ffmpeg';

    $dir_videos   = 'uploads/videos/';
    $dir_thumbs   = 'uploads/thumbs/';
    $dir_previews = 'uploads/previews/';

    if (!file_exists($dir_videos)) mkdir($dir_videos, 0777, true);
    if (!file_exists($dir_thumbs)) mkdir($dir_thumbs, 0777, true);
    if (!file_exists($dir_previews)) mkdir($dir_previews, 0777, true);

    $titulo             = trim($_POST['titulo'] ?? '');
    $categoria_id       = intval($_POST['categoria_id'] ?? 0);
    $tags_seleccionados = $_POST['tags_select'] ?? [];
    $tags_nuevos_str    = trim($_POST['tags_nuevos'] ?? '');

    $nombre_base    = uniqid('vid_');
    $nombre_thumb   = $nombre_base . '.jpg';
    $nombre_preview = $nombre_base . '.gif';

    $exito_archivo = false;
    $ruta_video = "";

    // CASO A: Viene de ser recortado
    if (isset($_POST['procesar_corte'])) {
        $file_temp = $_POST['temp_filename'] ?? '';
        $start     = floatval($_POST['start_time'] ?? 0);
        $end       = floatval($_POST['end_time'] ?? 0);
        $duration  = $end - $start;

        $ruta_input_temp = $dir_videos . $file_temp;
        $ext_video       = strtolower(pathinfo($file_temp, PATHINFO_EXTENSION));
        $nombre_video    = $nombre_base . '.' . $ext_video;
        $ruta_video      = $dir_videos . $nombre_video;

        if ($duration > 0 && file_exists($ruta_input_temp)) {
            // Comando FFmpeg para recortar
            $cmd_trim = sprintf(
                '%s -ss %s -i %s -t %s -c:v libx264 -c:a aac -strict experimental -y %s 2>&1',
                escapeshellarg($ffmpeg_bin),
                escapeshellarg($start),
                escapeshellarg(realpath($ruta_input_temp)),
                escapeshellarg($duration),
                escapeshellarg(__DIR__ . '/' . $ruta_video)
            );
            exec($cmd_trim, $out_trim, $res_trim);

            // Eliminar el archivo subido temporal
            if (file_exists($ruta_input_temp)) unlink($ruta_input_temp);

            if ($res_trim === 0 && file_exists($ruta_video) && filesize($ruta_video) > 0) {
                $exito_archivo = true;
            } else {
                $error = "Error al recortar el video con FFmpeg.";
            }
        } else {
            $error = "Tiempos de corte inválidos o el archivo temporal no existe.";
        }
    }
    // CASO B: Subida normal directa
    else if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $ext_video    = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $nombre_video = $nombre_base . '.' . $ext_video;
        $ruta_video   = $dir_videos . $nombre_video;

        if (move_uploaded_file($_FILES['video']['tmp_name'], $ruta_video)) {
            $exito_archivo = true;
        } else {
            $error = "Error al mover el archivo de video al directorio final.";
        }
    }

    // SI EL VIDEO SE GUARDÓ CORRECTAMENTE (RECORTADO O DIRECTO), CONTINUAMOS
    if ($exito_archivo) {
        $abs_video   = realpath($ruta_video);
        $abs_thumb   = __DIR__ . '/' . $dir_thumbs . $nombre_thumb;
        $abs_preview = __DIR__ . '/' . $dir_previews . $nombre_preview;

        // 1. Thumbnail JPG
        $output = [];
        $ffmpeg_cmd = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:01 -i " . escapeshellarg($abs_video) . " -vframes 1 -q:v 2 " . escapeshellarg($abs_thumb) . " 2>&1";
        exec($ffmpeg_cmd, $output, $return_var);

        if ($return_var !== 0 || !file_exists($abs_thumb) || filesize($abs_thumb) === 0) {
            $output = [];
            $ffmpeg_cmd_fallback = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:00 -i " . escapeshellarg($abs_video) . " -vframes 1 -q:v 2 " . escapeshellarg($abs_thumb) . " 2>&1";
            exec($ffmpeg_cmd_fallback, $output, $return_var);
        }

        // 2. Extraer Duración
        $duracion_formateada = "00:00";
        $output_str = implode(" ", $output);
        if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})/', $output_str, $matches)) {
            $horas   = $matches[1];
            $minutos = $matches[2];
            $segundos= $matches[3];

            $duracion_formateada = ($horas === '00')
                ? $minutos . ':' . $segundos
                : $horas . ':' . $minutos . ':' . $segundos;
        }

        // 3. Preview GIF
        $ffmpeg_gif_cmd = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:01 -t 3 -i " . escapeshellarg($abs_video) . " -vf \"fps=10,scale=320:-1:flags=lanczos\" -y " . escapeshellarg($abs_preview) . " 2>&1";
        exec($ffmpeg_gif_cmd);

        // 4. Guardar en Base de Datos
        $stmt = $pdo->prepare("INSERT INTO videos (usuario_id, categoria_id, titulo, archivo_video, archivo_thumb, archivo_preview, duracion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $categoria_id, $titulo, $nombre_video, $nombre_thumb, $nombre_preview, $duracion_formateada]);
        $video_id = $pdo->lastInsertId();

        // Tags Seleccionados
        foreach ($tags_seleccionados as $tag_id) {
            $stmt_vtag = $pdo->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)");
            $stmt_vtag->execute([$video_id, intval($tag_id)]);
        }

        // Tags Nuevos
        if (!empty($tags_nuevos_str)) {
            $array_nuevos = explode(',', $tags_nuevos_str);
            foreach ($array_nuevos as $tag_nom) {
                $tag_nom = trim(strtolower($tag_nom));
                if (!empty($tag_nom)) {
                    $stmt_tag = $pdo->prepare("INSERT INTO tags (nombre) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
                    $stmt_tag->execute([$tag_nom]);
                    $nuevo_tag_id = $pdo->lastInsertId();

                    $stmt_vtag = $pdo->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)");
                    $stmt_vtag->execute([$video_id, $nuevo_tag_id]);
                }
            }
        }

        $mensaje = "Video subido y procesado con éxito. Duración: " . $duracion_formateada;
    }
}

// Cargar Categorías y Tags
$categorias      = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$tags_existentes = $pdo->query("SELECT * FROM tags ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración - Videos & Categorías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Panel de Administración</h2>
        <div>
            <a href="index.php" class="btn btn-outline-light">&laquo; Volver a la Galería</a>
            <a href="admin2.php" class="btn btn-outline-light">&laquo; Gestión</a>
        </div>
    </div>

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

    <!-- SECCIÓN INTERMEDIA: REPRODUCTOR PARA MARCAR EL CORTE DEL VIDEO -->
    <?php if ($video_para_cortar): ?>
        <div class="card bg-secondary text-white shadow mb-4 border-warning">
            <div class="card-header bg-warning text-dark"><h5 class="mb-0">Paso 2: Seleccionar Tiempo de Corte</h5></div>
            <div class="card-body">
                <div class="text-center bg-black rounded p-2 mb-3">
                    <video id="player" class="w-100" style="max-height: 400px;" controls src="uploads/videos/<?= htmlspecialchars($video_para_cortar) ?>"></video>
                </div>

                <form action="admin.php" method="POST">
                    <input type="hidden" name="procesar_corte" value="1">
                    <input type="hidden" name="temp_filename" value="<?= htmlspecialchars($video_para_cortar) ?>">
                    <input type="hidden" name="titulo" value="<?= htmlspecialchars($datos_post_temporales['titulo']) ?>">
                    <input type="hidden" name="categoria_id" value="<?= $datos_post_temporales['categoria_id'] ?>">
                    <input type="hidden" name="tags_nuevos" value="<?= htmlspecialchars($datos_post_temporales['tags_nuevos']) ?>">
                    <?php foreach ($datos_post_temporales['tags_select'] as $t_id): ?>
                        <input type="hidden" name="tags_select[]" value="<?= htmlspecialchars($t_id) ?>">
                    <?php endforeach; ?>

                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Desde (segundos):</label>
                            <div class="input-group">
                                <input type="number" step="0.1" id="start_time" name="start_time" class="form-control" value="0.0" required readonly>
                                <button class="btn btn-outline-light" type="button" onclick="setStartTime()">Marcar Inicio</button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Hasta (segundos):</label>
                            <div class="input-group">
                                <input type="number" step="0.1" id="end_time" name="end_time" class="form-control" value="5.0" required readonly>
                                <button class="btn btn-outline-light" type="button" onclick="setEndTime()">Marcar Fin</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 btn-lg fw-bold">Confirmar Corte y Guardar Video</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="row" <?= $video_para_cortar ? 'style="display:none;"' : '' ?>>
        <!-- FORMULARIO 1: CREAR CATEGORÍA -->
        <div class="col-md-4 mb-4">
            <div class="card bg-secondary text-white shadow h-100">
                <div class="card-header"><h5 class="mb-0">Nueva Categoría</h5></div>
                <div class="card-body">
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="crear_categoria" value="1">
                        <div class="mb-3">
                            <label for="nombre_categoria" class="form-label">Nombre</label>
                            <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" placeholder="Ej: Deportes, Películas" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">+ Agregar Categoría</button>
                    </form>

                    <hr class="my-4">
                    <h6>Categorías existentes:</h6>
                    <ul class="list-group list-group-flush bg-dark rounded">
                        <?php foreach ($categorias as $c): ?>
                            <li class="list-group-item bg-transparent text-white border-secondary">
                                <?= htmlspecialchars($c['nombre']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FORMULARIO 2: CARGAR VIDEO -->
        <div class="col-md-8 mb-4">
            <div class="card bg-secondary text-white shadow">
                <div class="card-header"><h5 class="mb-0">Cargar Nuevo Video</h5></div>
                <div class="card-body">
                    <form action="admin.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="subir_video" value="1">

                        <div class="mb-3">
                            <label for="video" class="form-label">Archivo de Video</label>
                            <input type="file" name="video" id="video" class="form-control" accept="video/*" required>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="cortar_video" id="cortar_video" value="1">
                            <label class="form-check-label text-warning fw-bold" for="cortar_video">
                                Cortar video antes de subir (seleccionar tiempo de inicio y fin)
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título del Video</label>
                            <input type="text" name="titulo" id="titulo" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="categoria_id" class="form-label">Categoría</label>
                            <select name="categoria_id" id="categoria_id" class="form-select" required>
                                <option value="">-- Seleccionar Categoría --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tags_select" class="form-label">Seleccionar Tags existentes</label>
                            <select name="tags_select[]" id="tags_select" class="form-select" multiple size="4">
                                <?php foreach ($tags_existentes as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tags_nuevos" class="form-label">Agregar Tags Nuevos (separados por coma)</label>
                            <input type="text" name="tags_nuevos" id="tags_nuevos" class="form-control" placeholder="ej: 4k, estreno, trailer">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Subir Video y Procesar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Limpiar título automáticamente al seleccionar archivo
const inputVideo = document.getElementById('video');
if (inputVideo) {
    inputVideo.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            let nombreArchivo = this.files[0].name;
            let ultimoPunto = nombreArchivo.lastIndexOf('.');
            if (ultimoPunto !== -1) {
                nombreArchivo = nombreArchivo.substring(0, ultimoPunto);
            }
            let tituloLimpio = nombreArchivo.replace(/[_\-.]+/g, ' ');
            document.getElementById('titulo').value = tituloLimpio;
        }
    });
}

// Botones de marcar tiempo de corte
const player = document.getElementById('player');

function setStartTime() {
    if (player) {
        document.getElementById('start_time').value = player.currentTime.toFixed(1);
    }
}

function setEndTime() {
    if (player) {
        document.getElementById('end_time').value = player.currentTime.toFixed(1);
    }
}
</script>
</body>
</html>
