<?php
/**
 * Partial: cartão de produto.
 * Espera no escopo: $p (linha da tabela products + image_url + brand_name),
 * $__favIds (array de product_id favoritados pelo usuário atual),
 * $__userMeasurements (medidas do usuário atual ou null).
 */
$__isFav = in_array($p['id'], $__favIds ?? []);
$__sizes = db()->prepare('SELECT * FROM product_sizes WHERE product_id = ?');
$__sizes->execute([$p['id']]);
$__sizesRows = $__sizes->fetchAll();
$__best = get_best_size_for_product($__sizesRows, $__userMeasurements ?? null);
?>
<div class="product-card">
    <a href="<?= BASE_URL ?>/produto.php?slug=<?= e($p['slug']) ?>" class="product-media">
        <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    </a>
    <button class="fav-toggle <?= $__isFav ? 'active' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" aria-label="Favoritar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>
    <?php if ($__best): ?>
        <span class="match-badge <?= $__best['percentage'] >= 90 ? 'excellent' : '' ?>">
            <?= (int)$__best['percentage'] ?>% no tam. <?= e($__best['size']) ?>
        </span>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/produto.php?slug=<?= e($p['slug']) ?>" class="product-info">
        <span class="product-brand"><?= e($p['brand_name']) ?></span>
        <span class="product-name"><?= e($p['name']) ?></span>
        <span class="product-price"><?= format_price($p['rental_price']) ?> <small>/ aluguel</small></span>
    </a>
</div>
