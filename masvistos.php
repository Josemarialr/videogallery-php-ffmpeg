<?php
$titulo_pagina = "Galería de Videos";
require_once 'header.php';

$where = [];
$params = [];
$joins = [];

$active_tag = $_GET['tag'] ?? null;
$active_cat = $_GET['cat'] ?? null;
$search_query = $_GET['q'] ?? null;

// Configuración de la paginación
$videos_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $videos_por_pagina;

// 1. CONSTRUCCIÓN DINÁMICA DE FILTROS

// Filtro por Tag
if (!empty($active_tag)) {
    $joins[] = "JOIN video_tags vt ON v.id = vt.video_id JOIN tags t ON vt.tag_id = t.id";
    $where[] = "t.nombre = ?";
    $params[] = trim($active_tag);
}

// Filtro por Categoría
if (!empty($active_cat)) {
    $where[] = "v.categoria_id = ?";
    $params[] = intval($active_cat);
}

// Filtro por Búsqueda EXCLUSIVA por Nombre/Título del Video
if (!empty($search_query)) {
    $where[] = "v.titulo LIKE ?";
    $params[] = '%' . trim($search_query) . '%';
}

$joins_sql = $joins ? " " . implode(" ", $joins) : "";
$where_sql = $where ? " WHERE " . implode(" AND ", $where) : "";

// 2. CONTEO TOTAL DE VIDEOS
$sql_count = "SELECT COUNT(DISTINCT v.id) as total
              FROM videos v"
              . $joins_sql
              . $where_sql;

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_videos = $stmt_count->fetch()['total'] ?? 0;

// 3. CONSULTA PRINCIPAL CON PAGINACIÓN
$sql = "SELECT DISTINCT v.*, c.nombre as categoria
        FROM videos v
        LEFT JOIN categorias c ON v.categoria_id = c.id"
        . $joins_sql
        . $where_sql . "
        ORDER BY v.visitas DESC, v.id DESC
        LIMIT ? OFFSET ?";

// Calcular el total de páginas
$total_paginas = ceil($total_videos / $videos_por_pagina);

// Ejecutar consulta principal
$stmt = $pdo->prepare($sql);

$param_index = 1;
foreach ($params as $val) {
    $stmt->bindValue($param_index++, $val);
}
$stmt->bindValue($param_index++, intval($videos_por_pagina), PDO::PARAM_INT);
$stmt->bindValue($param_index++, intval($offset), PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll();

// Cargar categorías para la barra superior de botones
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();

// Función auxiliar para mantener los parámetros GET actuales en la paginación
function obtenerUrlPagina($num_pagina) {
    $params = $_GET;
    $params['pagina'] = $num_pagina;
    return 'index.php?' . http_build_query($params);
}
?>

<div class="container my-4">

    <!-- BARRA DE FILTROS Y BÚSQUEDA -->
    <div class="row align-items-center mb-4 g-3">
        <!-- Botones de Categorías y Tags Activos -->
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <a href="index.php" class="btn btn-sm <?= (!$active_cat && !$active_tag && !$search_query) ? 'btn-primary' : 'btn-outline-secondary text-white' ?>">
                    Todas
                </a>
                <?php foreach ($categorias as $cat): ?>
                    <?php
                        $activa = ($active_cat == $cat['id']);
                        // Mantiene la búsqueda actual al hacer clic en una categoría
                        $url_cat = "index.php?cat=" . $cat['id'] . ($search_query ? "&q=" . urlencode($search_query) : "");
                    ?>
                    <a href="<?= $url_cat ?>"
                       class="btn btn-sm <?= $activa ? 'btn-primary' : 'btn-outline-secondary text-white' ?>">
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </a>
                <?php endforeach; ?>

                <!-- Badge para Tag Activo -->
                <?php if (!empty($active_tag)): ?>
                    <span class="badge bg-info text-dark p-2 ms-2">
                        Tag: #<?= htmlspecialchars($active_tag) ?>
                        <a href="index.php" class="text-dark text-decoration-none ms-1 fw-bold">✕</a>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formularios de Búsqueda -->
        <div class="col-lg-4">
            <form action="index.php" method="GET" class="d-flex">
                <?php if ($active_cat): ?>
                    <input type="hidden" name="cat" value="<?= htmlspecialchars($active_cat) ?>">
                <?php endif; ?>
                <?php if ($active_tag): ?>
                    <input type="hidden" name="tag" value="<?= htmlspecialchars($active_tag) ?>">
                <?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm bg-dark text-white border-secondary me-2"
                       placeholder="Buscar por nombre..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
            </form>
        </div>
    </div>

    <!-- GRILLA DE VIDEOS -->
    <?php if (empty($videos)): ?>
        <div class="alert alert-secondary text-center my-5 bg-dark text-light border-secondary p-5 shadow">
            <h4>No se encontraron videos disponibles.</h4>
            <p class="mb-0 text-secondary">Prueba ajustando el nombre de búsqueda o seleccionando otra categoría.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($videos as $vid): ?>
                <div class="col">
                    <div class="card card-custom h-100 shadow-sm">
                        <!-- Caja de la Miniatura con Preview GIF -->
                        <a href="vervideo.php?id=<?= $vid['id'] ?>">
                            <div class="thumb-container">
                                <img src="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                     data-static="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb']) ?>"
                                     data-gif="uploads/previews/<?= htmlspecialchars($vid['archivo_preview']) ?>"
                                     class="js-thumb-preview"
                                     alt="<?= htmlspecialchars($vid['titulo']) ?>"
                                     loading="lazy">

                                <!-- Badge de Duración Extraída por FFmpeg -->
                                <?php if (!empty($vid['duracion'])): ?>
                                    <span class="duration-badge">
                                        <?= htmlspecialchars($vid['duracion']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <!-- Cuerpo de la Tarjeta -->
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <h6 class="card-title mb-1 text-truncate">
                                    <a href="vervideo.php?id=<?= $vid['id'] ?>" class="text-white text-decoration-none">
                                        <?= htmlspecialchars($vid['titulo']) ?>
                                    </a>
                                </h6>
                                <p class="text-secondary small mb-0">
                                    <?= htmlspecialchars($vid['categoria'] ?? 'Sin categoría') ?>
                                </p>
                            </div>

                            <!-- Métricas (Visitas y Likes) -->
                            <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-secondary mt-3">
                                <span>👁️ <?= number_format($vid['visitas']) ?></span>
                                <span>👍 <?= number_format($vid['likes']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN CON BOOTSTRAP -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de videos" class="mt-5">
                <ul class="pagination justify-content-center">
                    <!-- Botón Anterior -->
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="<?= obtenerUrlPagina($pagina_actual - 1) ?>">Anterior</a>
                    </li>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?= ($pagina_actual == $i) ? 'active' : '' ?>">
                            <a class="page-link bg-dark text-white border-secondary <?= ($pagina_actual == $i) ? 'bg-primary border-primary' : '' ?>" href="<?= obtenerUrlPagina($i) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Botón Siguiente -->
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="<?= obtenerUrlPagina($pagina_actual + 1) ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- Script para la animación en GIF al pasar el puntero -->
<script>
$(document).ready(function() {
    $(document).on('mouseenter', '.js-thumb-preview', function() {
        const gifUrl = $(this).data('gif');
        if (gifUrl && gifUrl.trim() !== '') {
            $(this).attr('src', gifUrl);
        }
    }).on('mouseleave', '.js-thumb-preview', function() {
        const staticUrl = $(this).data('static');
        if (staticUrl && staticUrl.trim() !== '') {
            $(this).attr('src', staticUrl);
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>
