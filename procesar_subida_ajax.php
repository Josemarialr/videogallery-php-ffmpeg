Aquí tienes el archivo **`procesar_subida_ajax.php`** completo con la corrección aplicada en la consulta SQL (sin la columna `descripcion`):

```php
<?php
// Evitar que PHP imprima HTML de error y rompa la estructura JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once 'config.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!function_exists('esAdmin') || !esAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado o sesión no iniciada.']);
        exit;
    }

    // Validar si el archivo superó el post_max_size de php.ini
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        echo json_encode(['success' => false, 'message' => 'El video excede el límite "post_max_size" configurado en php.ini.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {

        // Manejar errores de subida nativos de PHP
        if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $errores_upload = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo excede "upload_max_filesize" en php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el límite permitido por el formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo solo se subió parcialmente.',
                UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor PHP.',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco (revisar permisos).',
                UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida.'
            ];
            $msg = $errores_upload[$_FILES['video']['error']] ?? 'Error desconocido al subir archivo.';
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        $titulo             = trim($_POST['titulo'] ?? '');
        $categoria_id       = intval($_POST['categoria_id'] ?? 0);
        $tags_seleccionados = $_POST['tags_select'] ?? [];
        $tags_nuevos_str    = trim($_POST['tags_nuevos'] ?? '');

        $ffmpeg_bin = '/usr/local/bin/ffmpeg';

        $dir_videos   = 'uploads/videos/';
        $dir_thumbs   = 'uploads/thumbs/';
        $dir_previews = 'uploads/previews/';

        if (!file_exists($dir_videos) && !mkdir($dir_videos, 0777, true)) {
            throw new Exception("No se pudo crear la carpeta $dir_videos");
        }
        if (!file_exists($dir_thumbs) && !mkdir($dir_thumbs, 0777, true)) {
            throw new Exception("No se pudo crear la carpeta $dir_thumbs");
        }
        if (!file_exists($dir_previews) && !mkdir($dir_previews, 0777, true)) {
            throw new Exception("No se pudo crear la carpeta $dir_previews");
        }

        $nombre_base    = uniqid('vid_');
        $ext_video      = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);

        $nombre_video   = $nombre_base . '.' . strtolower($ext_video);
        $nombre_thumb   = $nombre_base . '.jpg';
        $nombre_preview = $nombre_base . '.gif';

        $ruta_video   = $dir_videos . $nombre_video;
        $ruta_thumb   = $dir_thumbs . $nombre_thumb;
        $ruta_preview = $dir_previews . $nombre_preview;

        if (move_uploaded_file($_FILES['video']['tmp_name'], $ruta_video)) {

            $abs_video   = realpath($ruta_video);
            $abs_thumb   = __DIR__ . '/' . $ruta_thumb;
            $abs_preview = __DIR__ . '/' . $ruta_preview;

            // Thumbnail JPG
            $ffmpeg_cmd = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:01 -i " . escapeshellarg($abs_video) . " -vframes 1 -q:v 2 " . escapeshellarg($abs_thumb) . " 2>&1";
            exec($ffmpeg_cmd, $output, $return_var);

            if ($return_var !== 0 || !file_exists($abs_thumb) || filesize($abs_thumb) === 0) {
                $output = [];
                $ffmpeg_cmd_fallback = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:00 -i " . escapeshellarg($abs_video) . " -vframes 1 -q:v 2 " . escapeshellarg($abs_thumb) . " 2>&1";
                exec($ffmpeg_cmd_fallback, $output, $return_var);
            }

            // Extraer Duración
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

            // Preview GIF
            $ffmpeg_gif_cmd = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:01 -t 3 -i " . escapeshellarg($abs_video) . " -vf \"fps=10,scale=320:-1:flags=lanczos\" -y " . escapeshellarg($abs_preview) . " 2>&1";
            exec($ffmpeg_gif_cmd);

            if (!file_exists($abs_preview) || filesize($abs_preview) === 0) {
                $ffmpeg_gif_fallback = escapeshellcmd($ffmpeg_bin) . " -ss 00:00:00 -t 3 -i " . escapeshellarg($abs_video) . " -vf \"fps=10,scale=320:-1:flags=lanczos\" -y " . escapeshellarg($abs_preview) . " 2>&1";
                exec($ffmpeg_gif_fallback);
            }

            if (file_exists($abs_thumb) && filesize($abs_thumb) > 0) {

                // INSERT ajustado sin el campo 'descripcion'
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

                echo json_encode([
                    'success' => true,
                    'message' => 'Video subido con éxito. Duración detectada: ' . $duracion_formateada
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'El video fue subido, pero falló la generación de la miniatura.',
                    'error_detalle' => implode("<br>", $output)
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo subido al directorio de destino.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Petición inválida o el servidor rechazó el envío de datos.']);
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error 500 interno: ' . $e->getMessage(),
        'error_detalle' => 'En archivo ' . basename($e->getFile()) . ' línea ' . $e->getLine()
    ]);
}

```
