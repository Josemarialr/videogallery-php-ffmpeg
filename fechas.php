<?php
$titulo_pagina = "Galería de Videos";
require_once 'header.php';

$where = [];
$params = [];
$joins = [];

$active_tag = $_GET['tag'] ?? null;
$active_cat = $_GET['cat'] ?? null;
$search_query = $_GET['q'] ?? null;
$active_fecha = $_GET['fecha'] ?? null; // Formato esperado: YYYY-MM (ej: 2026-01)

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

// Filtro por Búsqueda por Título
if (!empty($search_query)) {
    $where[] = "v.titulo LIKE ?";
    $params[] = '%' . trim($search_query) . '%';
}

// Filtro por Fecha (Año-Mes) optimizado por rango de fechas
if (!empty($active_fecha) && preg_match('/^\d{4}-\d{2}$/', $active_fecha)) {
    $inicio_mes = $active_fecha . '-01 00:00:00';
    $fin_mes = date('Y-m-d H:i:s', strtotime("$inicio_mes +1 month"));

    $where[] = "v.creado_en >= ? AND v.creado_en < ?";
    $params[] = $inicio_mes;
    $params[] = $fin_mes;
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
$total_videos = $stmt_count->fetchColumn() ?? 0;

// 3. CONSULTA PRINCIPAL CON PAGINACIÓN
$sql = "SELECT DISTINCT v.*, c.nombre as categoria
        FROM videos v
        LEFT JOIN categorias c ON v.categoria_id = c.id"
        . $joins_sql
        . $where_sql . "
        ORDER BY v.creado_en DESC
        LIMIT ? OFFSET ?";

$total_paginas = ceil($total_videos / $videos_por_pagina);

$stmt = $pdo->prepare($sql);
$param_index = 1;
foreach ($params as $val) {
    $stmt->bindValue($param_index++, $val);
}
$stmt->bindValue($param_index++, intval($videos_por_pagina), PDO::PARAM_INT);
$stmt->bindValue($param_index++, intval($offset), PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar categorías
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Cargar meses disponibles agrupados (Compatible con ONLY_FULL_GROUP_BY y Microsegundos)
$sql_fechas = "SELECT
                    DATE_FORMAT(creado_en, '%Y-%m') as mes_anio,
                    DATE_FORMAT(creado_en, '%Y') as anio,
                    DATE_FORMAT(creado_en, '%c') as mes
               FROM videos
               WHERE creado_en IS NOT NULL
                 AND creado_en > '1970-01-01 00:00:00'
               GROUP BY mes_anio, anio, mes
               ORDER BY mes_anio DESC";
$fechas_disponibles = $pdo->query($sql_fechas)->fetchAll(PDO::FETCH_ASSOC);

// Nombres de meses
$meses_es = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Funciones auxiliares para mantener variables GET en la URL
function obtenerUrlPagina($num_pagina) {
    $params = $_GET;
    $params['pagina'] = $num_pagina;
    return 'fechas.php?' . http_build_query($params);
}

function obtenerUrlFiltro($clave, $valor) {
    $params = $_GET;
    unset($params['pagina']);
    if ($valor === null || $valor === '') {
        unset($params[$clave]);
    } else {
        $params[$clave] = $valor;
    }
    return 'fechas.php?' . http_build_query($params);
}
?>

<div class="container my-4">

    <!-- BARRA DE CATEGORÍAS Y BÚSQUEDA -->
    <div class="row align-items-center mb-3 g-3">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <a href="<?= obtenerUrlFiltro('cat', null) ?>" class="btn btn-sm <?= (!$active_cat) ? 'btn-primary' : 'btn-outline-secondary text-white' ?>">
                    Todas
                </a>
                <?php foreach ($categorias as $cat): ?>
                    <?php $activa = ($active_cat == $cat['id']); ?>
                    <a href="<?= obtenerUrlFiltro('cat', $cat['id']) ?>"
                       class="btn btn-sm <?= $activa ? 'btn-primary' : 'btn-outline-secondary text-white' ?>">
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!empty($active_tag)): ?>
                    <span class="badge bg-info text-dark p-2 ms-2">
                        Tag: #<?= htmlspecialchars($active_tag) ?>
                        <a href="<?= obtenerUrlFiltro('tag', null) ?>" class="text-dark text-decoration-none ms-1 fw-bold">✕</a>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <form action="index.php" method="GET" class="d-flex">
                <?php if ($active_cat): ?>
                    <input type="hidden" name="cat" value="<?= htmlspecialchars($active_cat) ?>">
                <?php endif; ?>
                <?php if ($active_tag): ?>
                    <input type="hidden" name="tag" value="<?= htmlspecialchars($active_tag) ?>">
                <?php endif; ?>
                <?php if ($active_fecha): ?>
                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($active_fecha) ?>">
                <?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm bg-dark text-white border-secondary me-2"
                       placeholder="Buscar por nombre..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
            </form>
        </div>
    </div>

    <!-- BOTONES DE FILTRO POR MES Y AÑO -->
    <?php if (!empty($fechas_disponibles)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-2 bg-dark rounded border border-secondary d-flex flex-wrap align-items-center gap-1">
                    <small class="text-secondary me-2 fw-semibold">📅 Fecha:</small>
                    <a href="<?= obtenerUrlFiltro('fecha', null) ?>" class="btn btn-sm <?= (!$active_fecha) ? 'btn-warning' : 'btn-outline-warning' ?>">
                        Todas
                    </a>
                    <?php foreach ($fechas_disponibles as $f): ?>
                        <?php
                            $es_activa = ($active_fecha === $f['mes_anio']);
                            $nombre_mes = $meses_es[intval($f['mes'])] ?? '';
                            $etiqueta = $nombre_mes . ' ' . $f['anio'];
                        ?>
                        <a href="<?= obtenerUrlFiltro('fecha', $f['mes_anio']) ?>"
                           class="btn btn-sm <?= $es_activa ? 'btn-warning fw-bold' : 'btn-outline-secondary text-white' ?>">
                            <?= $etiqueta ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- GRILLA DE VIDEOS -->
    <?php if (empty($videos)): ?>
        <div class="alert alert-secondary text-center my-5 bg-dark text-light border-secondary p-5 shadow">
            <h4>No se encontraron videos disponibles.</h4>
            <p class="mb-0 text-secondary">Prueba ajustando el nombre, la fecha o la categoría seleccionada.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($videos as $vid): ?>
                <div class="col">
                    <div class="card card-custom h-100 shadow-sm">
                        <a href="vervideo.php?id=<?= $vid['id'] ?>">
                            <div class="thumb-container">
                                <img src="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb'] ?? '') ?>"
                                     data-static="uploads/thumbs/<?= htmlspecialchars($vid['archivo_thumb'] ?? '') ?>"
                                     data-gif="uploads/previews/<?= htmlspecialchars($vid['archivo_preview'] ?? '') ?>"
                                     class="js-thumb-preview"
                                     alt="<?= htmlspecialchars($vid['titulo'] ?? '') ?>"
                                     loading="lazy">

                                <?php if (!empty($vid['duracion'])): ?>
                                    <span class="duration-badge">
                                        <?= htmlspecialchars($vid['duracion']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <h6 class="card-title mb-1 text-truncate">
                                    <a href="vervideo.php?id=<?= $vid['id'] ?>" class="text-white text-decoration-none">
                                        <?= htmlspecialchars($vid['titulo'] ?? '') ?>
                                    </a>
                                </h6>
                                <p class="text-secondary small mb-0">
                                    <?= htmlspecialchars($vid['categoria'] ?? 'Sin categoría') ?>
                                </p>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-secondary mt-3">
                                <span>👁️ <?= number_format($vid['visitas'] ?? 0) ?></span>
                                <span>👍 <?= number_format($vid['likes'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de videos" class="mt-5">
                <ul class="pagination justify-content-center">
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

                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="<?= obtenerUrlPagina($pagina_actual + 1) ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

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
