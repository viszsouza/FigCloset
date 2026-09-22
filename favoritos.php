<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$pageTitle = 'Favoritos';
$__userMeasurements = get_user_measurements($user['id']);

$stmt = db()->prepare('SELECT p.*, b.name AS brand_name,
                               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
                        FROM favorites f
                        JOIN products p ON p.id = f.product_id
                        JOIN brands b ON b.id = p.brand_id
                        WHERE f.user_id = ? AND p.deleted_at IS NULL
                        ORDER BY f.created_at DESC');
$stmt->execute([$user['id']]);
$produtos = $stmt->fetchAll();
$__favIds = array_column($produtos, 'id');

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container">
        <h1>Meus favoritos</h1>
        <?php if (empty($produtos)): ?>
            <div class="empty-state">
                <p>Você ainda não favoritou nenhuma peça.</p>
                <a href="<?= BASE_URL ?>/roupas.php" class="btn btn-primary btn-sm">Explorar catálogo</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($produtos as $p) include __DIR__ . '/includes/product-card.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<script>window.BASE_URL = '<?= BASE_URL ?>'; window.CSRF_TOKEN = '<?= e(csrf_token()) ?>'; window.REMOVE_ON_UNFAVORITE = true;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
