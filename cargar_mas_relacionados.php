<?php
require_once 'config.php';

$video_id = intval($_GET['video_id'] ?? 0);
$cat_id   = intval($_GET['cat_id'] ?? 0);
$offset   = intval($_GET['offset'] ?? 0);

$stmt = $pdo->prepare("SELECT v.*, c.nombre as categoria
                       FROM videos v
                       LEFT JOIN categorias c ON v.categoria_id = c.id
                       WHERE v.id != ? AND v.categoria_id = ?
                       ORDER BY v.creado_en DESC
                       LIMIT 6 OFFSET ?");

$stmt->bindValue(1, $video_id, PDO::PARAM_INT);
$stmt->bindValue(2, $cat_id, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();

$videos = $stmt->fetchAll();

foreach ($videos as $rel): ?>
    <div class="col">
        <div class="card card-custom h-100 shadow-sm">
            <a href="vervideo.php?id=<?= $rel['id'] ?>">
                <div class="thumb-container">
                    <img src="uploads/thumbs/<?= htmlspecialchars($rel['archivo_thumb']) ?>"
                         data-static="uploads/thumbs/<?= htmlspecialchars($rel['archivo_thumb']) ?>"
                         data-gif="uploads/previews/<?= htmlspecialchars($rel['archivo_preview']) ?>"
                         class="js-thumb-preview" alt="<?= htmlspecialchars($rel['titulo']) ?>">
                    <?php if(!empty($rel['duracion'])): ?>
                        <span class="duration-badge"><?= htmlspecialchars($rel['duracion']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <div class="card-body p-2">
                <h6 class="card-title mb-1 small">
                    <a href="vervideo.php?id=<?= $rel['id'] ?>" class="text-white text-decoration-none">
                        <?= htmlspecialchars($rel['titulo']) ?>
                    </a>
                </h6>
                <div class="d-flex justify-content-between text-secondary extra-small" style="font-size: 0.75rem;">
                    <span>👁️ <?= $rel['visitas'] ?></span>
                    <span>👍 <?= $rel['likes'] ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
