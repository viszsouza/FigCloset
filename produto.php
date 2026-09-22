<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT p.*, b.name AS brand_name, b.slug AS brand_slug, c.name AS category_name
                        FROM products p
                        JOIN brands b ON b.id = p.brand_id
                        JOIN categories c ON c.id = p.category_id
                        WHERE p.slug = ? AND p.deleted_at IS NULL');
$stmt->execute([$slug]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    $pageTitle = 'Peça não encontrada';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="empty-state"><p>Essa peça não existe ou foi removida.</p><a href="' . BASE_URL . '/roupas.php" class="btn btn-outline-ink btn-sm">Voltar ao catálogo</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$imgStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
$imgStmt->execute([$produto['id']]);
$imagens = $imgStmt->fetchAll();

$sizeStmt = db()->prepare('SELECT * FROM product_sizes WHERE product_id = ? ORDER BY FIELD(size, "PP","P","M","G","GG","XG")');
$sizeStmt->execute([$produto['id']]);
$tamanhos = $sizeStmt->fetchAll();

$__user = current_user();
$__userMeasurements = $__user ? get_user_measurements($__user['id']) : null;
$bestOverall = get_best_size_for_product($tamanhos, $__userMeasurements);

$isFav = false;
if ($__user) {
    $f = db()->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
    $f->execute([$__user['id'], $produto['id']]);
    $isFav = (bool)$f->fetch();
}

$busyRanges = get_product_busy_ranges($produto['id']);

$pageTitle = $produto['name'];
$pageDescription = $produto['description'];
$ogImage = $imagens[0]['image_url'] ?? null;
$jsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $produto['name'],
    'description' => $produto['description'],
    'brand' => ['@type' => 'Brand', 'name' => $produto['brand_name']],
    'image' => array_column($imagens, 'image_url'),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'BRL',
        'price' => $produto['rental_price'],
        'availability' => 'https://schema.org/InStock',
        'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

require __DIR__ . '/includes/header.php';

$styleLabels = ['casual'=>'Casual','social'=>'Social','festa'=>'Festa','fashion'=>'Fashion','esportivo'=>'Esportivo','elegante'=>'Elegante','streetwear'=>'Streetwear'];
?>

<section class="section-tight">
    <div class="container product-page">
        <div>
            <div class="gallery-main">
                <img src="<?= e($imagens[0]['image_url'] ?? 'https://picsum.photos/seed/fallback/900/1200') ?>" alt="<?= e($produto['name']) ?>">
            </div>
            <div class="gallery-thumbs">
                <?php foreach ($imagens as $i => $img): ?>
                <button data-full="<?= e($img['image_url']) ?>" class="<?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= e($img['image_url']) ?>" alt="">
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <div class="product-detail-brand"><?= e($produto['brand_name']) ?> · <?= e($produto['category_name']) ?></div>
            <h1><?= e($produto['name']) ?></h1>
            <div class="product-detail-price">
                <?= format_price($produto['rental_price']) ?> <small>/ período de aluguel</small>
            </div>
            <?php if ($produto['deposit'] > 0): ?>
                <p style="color:var(--graphite-soft); font-size:0.88rem;">+ <?= format_price($produto['deposit']) ?> de caução (devolvida após a devolução da peça em bom estado)</p>
            <?php endif; ?>

            <p><?= e($produto['description']) ?></p>

            <?php if ($__user && $bestOverall): ?>
                <div class="size-match-panel <?= $bestOverall['percentage'] >= 90 ? 'excellent' : '' ?>">
                    <div class="size-match-head">
                        <div>
                            <strong>Seu tamanho recomendado</strong><br>
                            <span style="font-size:0.85rem; color:var(--graphite-soft);"><?= e($bestOverall['label']) ?></span>
                        </div>
                        <div class="size-match-score"><?= e($bestOverall['size']) ?> — <?= (int)$bestOverall['percentage'] ?>%</div>
                    </div>
                    <div class="size-match-bar"><span style="width:<?= (int)$bestOverall['percentage'] ?>%;"></span></div>
                    <p style="margin:10px 0 0; font-size:0.85rem; color:var(--graphite-soft);">Este produto provavelmente ficará adequado em você, com base nas medidas que você cadastrou.</p>
                </div>
            <?php elseif (!$__user): ?>
                <div class="size-match-panel">
                    <p style="margin:0;"><a href="<?= BASE_URL ?>/minhas-medidas.php" style="color:var(--wine); font-weight:600;">Cadastre suas medidas</a> para ver seu tamanho recomendado e o percentual de compatibilidade com esta peça.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>/sacola-adicionar.php" id="add-to-bag-form">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= $produto['id'] ?>">
                <input type="hidden" name="selected_size" id="selected_size" value="">

                <h4 style="font-size:0.85rem; margin-bottom:8px;">Tamanho</h4>
                <div class="size-options">
                    <?php foreach ($tamanhos as $t):
                        $match = $__userMeasurements ? calculate_size_match($__userMeasurements, $t) : null;
                        $recommended = $bestOverall && $bestOverall['size'] === $t['size'];
                        $noStock = $t['stock'] <= 0;
                    ?>
                    <button type="button" class="size-pill <?= $recommended ? 'recommended' : '' ?>" data-size="<?= e($t['size']) ?>" <?= $noStock ? 'disabled' : '' ?>
                        title="<?= $match ? e($match['label'] . ' — ' . $match['percentage'] . '%') : 'Cadastre suas medidas para ver a compatibilidade' ?>">
                        <?= e($t['size']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <h4 style="font-size:0.85rem; margin-bottom:8px;">Período de aluguel</h4>
                <div class="date-picker-row">
                    <div class="form-field" style="margin-bottom:0;">
                        <label>Início do aluguel</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-field" style="margin-bottom:0;">
                        <label>Devolução</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>

                <?php if (!empty($busyRanges)): ?>
                <p class="availability-note">
                    Períodos já reservados:
                    <?php foreach ($busyRanges as $r): ?>
                        <?= format_date_br($r['start_date']) ?>–<?= format_date_br($r['end_date']) ?><?= end($busyRanges) === $r ? '' : ', ' ?>
                    <?php endforeach; ?>
                </p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top:10px;">Adicionar à sacola</button>
            </form>

            <h4 style="margin-top:28px; font-size:0.85rem;">Ficha técnica</h4>
            <table class="spec-table">
                <tr><td>Marca</td><td><?= e($produto['brand_name']) ?></td></tr>
                <tr><td>Categoria</td><td><?= e($produto['category_name']) ?></td></tr>
                <tr><td>Material</td><td><?= e($produto['material']) ?></td></tr>
                <tr><td>Cor</td><td><?= e($produto['color']) ?></td></tr>
                <tr><td>Estilo</td><td><?= e($styleLabels[$produto['style']] ?? $produto['style']) ?></td></tr>
                <tr><td>Tamanhos disponíveis</td><td><?= e(implode(', ', array_column($tamanhos, 'size'))) ?></td></tr>
            </table>

            <h4 style="margin-top:24px; font-size:0.85rem;">Medidas por tamanho (cm)</h4>
            <table class="spec-table">
                <tr><td>Tamanho</td><td>Busto</td><td>Cintura</td><td>Quadril</td></tr>
                <?php foreach ($tamanhos as $t): ?>
                <tr>
                    <td><strong><?= e($t['size']) ?></strong></td>
                    <td><?= (int)$t['bust_min'] ?>–<?= (int)$t['bust_max'] ?></td>
                    <td><?= (int)$t['waist_min'] ?>–<?= (int)$t['waist_max'] ?></td>
                    <td><?= (int)$t['hip_min'] ?>–<?= (int)$t['hip_max'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</section>

<script>window.BASE_URL = '<?= BASE_URL ?>'; window.CSRF_TOKEN = '<?= e(csrf_token()) ?>';</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
