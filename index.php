<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Alugue roupas de marca no seu tamanho';
$pageDescription = 'Vista marcas que combinam com você. Alugue peças exclusivas, encontre o tamanho ideal com nosso sistema de medidas e confirme pelo Instagram.';

$__user = current_user();
$__userMeasurements = $__user ? get_user_measurements($__user['id']) : null;
$__favIds = [];
if ($__user) {
    $stmt = db()->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$__user['id']]);
    $__favIds = array_column($stmt->fetchAll(), 'product_id');
}

function fetch_products_with_cover(string $whereExtra, array $params, int $limit): array {
    $sql = "SELECT p.*, b.name AS brand_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
            FROM products p
            JOIN brands b ON b.id = p.brand_id
            WHERE p.status = 'ativo' AND p.deleted_at IS NULL $whereExtra
            ORDER BY p.created_at DESC
            LIMIT $limit";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$novidades = fetch_products_with_cover('', [], 4);
$destaques = fetch_products_with_cover('AND p.featured = 1', [], 4);
$maisAlugadas = fetch_products_with_cover(
    "AND p.id IN (SELECT product_id FROM (SELECT product_id FROM order_items GROUP BY product_id ORDER BY COUNT(*) DESC LIMIT 8) AS mais_alugados_sub)",
    [], 4
);
if (count($maisAlugadas) < 4) {
    $maisAlugadas = fetch_products_with_cover('', [], 4);
}

$categorias = db()->query("SELECT * FROM categories WHERE active = 1 ORDER BY sort_order LIMIT 8")->fetchAll();
$marcas = db()->query("SELECT * FROM brands WHERE active = 1 ORDER BY name")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-copy">
            <span class="eyebrow-line">Curadoria de moda por assinatura</span>
            <h1>Vista marcas que combinam com você.</h1>
            <p>Alugue peças exclusivas, descubra novos estilos e use o tamanho ideal para o seu corpo — sem comprometer o guarda-roupa nem o orçamento.</p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/roupas.php" class="btn btn-primary">Explorar roupas</a>
                <a href="<?= BASE_URL ?>/minhas-medidas.php" class="btn btn-outline-ivory">Encontrar meu tamanho</a>
            </div>
        </div>
        <div class="hero-visual">
            <img src="https://picsum.photos/seed/viszhero/900/1125" alt="Editorial de moda VISZ Closet">
            <span class="hero-tag">+30 peças de 5 marcas selecionadas</span>
        </div>
    </div>
</section>

<section class="section" id="categorias">
    <div class="container">
        <div class="section-header">
            <h2>Navegue por categoria</h2>
            <p>Da alfaiataria ao streetwear, cada peça com ficha técnica completa.</p>
        </div>
        <div class="category-bento">
            <?php foreach ($categorias as $i => $cat):
                $classes = $i === 0 ? 'c-tall' : ($i === 3 ? 'c-wide' : '');
            ?>
            <a href="<?= BASE_URL ?>/roupas.php?categoria=<?= e($cat['slug']) ?>" class="<?= $classes ?>">
                <img src="https://picsum.photos/seed/cat<?= $cat['id'] ?>/500/500" alt="<?= e($cat['name']) ?>" loading="lazy">
                <span><?= e($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-tight" id="marcas">
    <div class="container">
        <div class="section-header">
            <h2>Marcas disponíveis</h2>
        </div>
        <div class="brand-strip">
            <?php foreach ($marcas as $m): ?>
                <a href="<?= BASE_URL ?>/roupas.php?marca[]=<?= $m['id'] ?>" class="brand-chip"><?= e($m['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Novidades</h2>
            <a href="<?= BASE_URL ?>/roupas.php" class="btn-ghost">Ver catálogo completo →</a>
        </div>
        <div class="product-grid">
            <?php foreach ($novidades as $p) include __DIR__ . '/includes/product-card.php'; ?>
        </div>
    </div>
</section>

<section class="section section-ink">
    <div class="container">
        <div class="section-header">
            <h2>Mais alugadas</h2>
            <p style="color:rgba(244,239,230,0.65);">As escolhas favoritas de quem já usou o VISZ Closet.</p>
        </div>
        <div class="product-grid">
            <?php foreach ($maisAlugadas as $p) include __DIR__ . '/includes/product-card.php'; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Peças em destaque</h2>
            <p>Últimas oportunidades da curadoria desta semana.</p>
        </div>
        <div class="product-grid">
            <?php foreach ($destaques as $p) include __DIR__ . '/includes/product-card.php'; ?>
        </div>
    </div>
</section>

<script>window.BASE_URL = '<?= BASE_URL ?>'; window.CSRF_TOKEN = '<?= e(csrf_token()) ?>';</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
