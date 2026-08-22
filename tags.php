<?php
require_once 'config.php'; // Incluye tu archivo de conexión PDO ($pdo)
require_once 'header.php'; // Tu encabezado opcional

// Consulta para obtener tags con el conteo total de sus videos asociados, ordenados alfabéticamente
$sql = "SELECT t.id, t.nombre, COUNT(vt.video_id) AS total_videos
        FROM tags t
        LEFT JOIN video_tags vt ON t.id = vt.tag_id
        GROUP BY t.id, t.nombre
        ORDER BY t.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Tags (Alfabético)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container my-5">
    <h1 class="mb-4 text-center">Explorar por Tags</h1>

    <div class="row">
        <?php foreach ($tags as $tag): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                <a href="videos_por_tag.php?tag_id=<?= $tag['id'] ?>" class="text-decoration-none">
                    <div class="card bg-secondary text-white border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <h6 class="card-title text-capitalize mb-1">
                                #<?= htmlspecialchars($tag['nombre']) ?>
                            </h6>
                            <span class="badge bg-primary rounded-pill">
                                <?= $tag['total_videos'] ?> <?= ($tag['total_videos'] == 1) ? 'video' : 'videos' ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>

        <?php if (empty($tags)): ?>
            <div class="col-12 text-center">
                <p class="text-muted">No se encontraron tags registrados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
