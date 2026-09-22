<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Roupas para alugar';

$__user = current_user();
$__userMeasurements = $__user ? get_user_measurements($__user['id']) : null;
$__favIds = [];
if ($__user) {
    $stmt = db()->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$__user['id']]);
    $__favIds = array_column($stmt->fetchAll(), 'product_id');
}

// ---------- Ler filtros da query string ----------
$f_categoria   = $_GET['categoria'] ?? '';
$f_marca       = array_filter((array)($_GET['marca'] ?? []), 'is_numeric');
$f_tamanho     = array_filter((array)($_GET['tamanho'] ?? []));
$f_cor         = array_filter((array)($_GET['cor'] ?? []));
$f_estilo      = array_filter((array)($_GET['estilo'] ?? []));
$f_preco_min   = $_GET['preco_min'] ?? '';
$f_preco_max   = $_GET['preco_max'] ?? '';
$f_disponibilidade = $_GET['disponibilidade'] ?? 'todas';
$f_data        = $_GET['data'] ?? '';
$f_busca       = trim($_GET['q'] ?? '');
$f_ordenar     = $_GET['ordenar'] ?? 'relevantes';

// ---------- Montar SQL dinâmico ----------
$where = ['p.status = "ativo"', 'p.deleted_at IS NULL'];
$params = [];

if ($f_categoria !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $f_categoria;
}
if (!empty($f_marca)) {
    $in = implode(',', array_fill(0, count($f_marca), '?'));
    $where[] = "p.brand_id IN ($in)";
    foreach ($f_marca as $m) $params[] = $m;
}
if (!empty($f_cor)) {
    $in = implode(',', array_fill(0, count($f_cor), '?'));
    $where[] = "p.color IN ($in)";
    foreach ($f_cor as $c) $params[] = $c;
}
if (!empty($f_estilo)) {
    $in = implode(',', array_fill(0, count($f_estilo), '?'));
    $where[] = "p.style IN ($in)";
    foreach ($f_estilo as $s) $params[] = $s;
}
if ($f_preco_min !== '' && is_numeric($f_preco_min)) {
    $where[] = 'p.rental_price >= ?';
    $params[] = $f_preco_min;
}
if ($f_preco_max !== '' && is_numeric($f_preco_max)) {
    $where[] = 'p.rental_price <= ?';
    $params[] = $f_preco_max;
}
if (!empty($f_tamanho)) {
    $in = implode(',', array_fill(0, count($f_tamanho), '?'));
    $where[] = "p.id IN (SELECT product_id FROM product_sizes WHERE size IN ($in))";
    foreach ($f_tamanho as $t) $params[] = $t;
}
if ($f_busca !== '') {
    $where[] = '(p.name LIKE ? OR b.name LIKE ? OR p.color LIKE ? OR p.style LIKE ?)';
    $like = '%' . $f_busca . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($f_disponibilidade === 'hoje') {
    $where[] = 'p.id NOT IN (SELECT product_id FROM availability WHERE status IN (' . blocking_statuses_sql() . ') AND start_date <= CURDATE() AND end_date >= CURDATE())';
} elseif ($f_disponibilidade === 'data' && $f_data !== '') {
    $where[] = 'p.id NOT IN (SELECT product_id FROM availability WHERE status IN (' . blocking_statuses_sql() . ') AND start_date <= ? AND end_date >= ?)';
    array_push($params, $f_data, $f_data);
}

$orderBy = 'p.created_at DESC';
if ($f_ordenar === 'recentes') $orderBy = 'p.created_at DESC';
elseif ($f_ordenar === 'menor_preco') $orderBy = 'p.rental_price ASC';
elseif ($f_ordenar === 'maior_preco') $orderBy = 'p.rental_price DESC';
elseif ($f_ordenar === 'mais_alugadas') $orderBy = '(SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) DESC';

$whereSql = implode(' AND ', $where);
$sql = "SELECT p.*, b.name AS brand_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
        FROM products p
        JOIN brands b ON b.id = p.brand_id
        JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY $orderBy";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$categorias = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order')->fetchAll();
$marcas = db()->query('SELECT * FROM brands WHERE active = 1 ORDER BY name')->fetchAll();
$cores = db()->query('SELECT DISTINCT color FROM products WHERE color IS NOT NULL ORDER BY color')->fetchAll(PDO::FETCH_COLUMN);
$tamanhosDisponiveis = ['PP', 'P', 'M', 'G', 'GG'];
$estilos = ['casual' => 'Casual', 'social' => 'Social', 'festa' => 'Festa', 'fashion' => 'Fashion', 'esportivo' => 'Esportivo', 'elegante' => 'Elegante', 'streetwear' => 'Streetwear'];

require __DIR__ . '/includes/header.php';

function checked_if_in($value, $arr) { return in_array($value, $arr) ? 'checked' : ''; }
?>

<section class="section-tight">
    <div class="container">
        <div class="section-header" style="margin-bottom:24px;">
            <h2>Catálogo</h2>
            <button type="button" class="btn btn-outline-ink btn-sm mobile-filter-trigger">Filtros</button>
        </div>

        <div class="catalog-layout">
            <form class="filters-panel" method="get" action="<?= BASE_URL ?>/roupas.php">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <strong>Filtrar</strong>
                    <button type="button" class="btn-ghost filters-close">Fechar</button>
                </div>

                <div class="form-field">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f_busca) ?>" placeholder="vestido, blazer, festa...">
                </div>

                <div class="filter-group">
                    <h4>Categoria</h4>
                    <label class="filter-option">
                        <input type="radio" name="categoria" value="" <?= $f_categoria === '' ? 'checked' : '' ?>> Todas
                    </label>
                    <?php foreach ($categorias as $cat): ?>
                    <label class="filter-option">
                        <input type="radio" name="categoria" value="<?= e($cat['slug']) ?>" <?= $f_categoria === $cat['slug'] ? 'checked' : '' ?>>
                        <?= e($cat['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Marca</h4>
                    <?php foreach ($marcas as $m): ?>
                    <label class="filter-option">
                        <input type="checkbox" name="marca[]" value="<?= $m['id'] ?>" <?= checked_if_in((string)$m['id'], $f_marca) ?>>
                        <?= e($m['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Tamanho</h4>
                    <?php foreach ($tamanhosDisponiveis as $t): ?>
                    <label class="filter-option">
                        <input type="checkbox" name="tamanho[]" value="<?= $t ?>" <?= checked_if_in($t, $f_tamanho) ?>>
                        <?= $t ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Cor</h4>
                    <?php foreach ($cores as $c): ?>
                    <label class="filter-option">
                        <input type="checkbox" name="cor[]" value="<?= e($c) ?>" <?= checked_if_in($c, $f_cor) ?>>
                        <?= e($c) ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Estilo</h4>
                    <?php foreach ($estilos as $key => $label): ?>
                    <label class="filter-option">
                        <input type="checkbox" name="estilo[]" value="<?= $key ?>" <?= checked_if_in($key, $f_estilo) ?>>
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Preço (R$)</h4>
                    <div class="filter-row">
                        <input type="number" name="preco_min" value="<?= e($f_preco_min) ?>" placeholder="Mín.">
                        <input type="number" name="preco_max" value="<?= e($f_preco_max) ?>" placeholder="Máx.">
                    </div>
                </div>

                <div class="filter-group">
                    <h4>Disponibilidade</h4>
                    <label class="filter-option">
                        <input type="radio" name="disponibilidade" value="todas" <?= $f_disponibilidade === 'todas' ? 'checked' : '' ?>> Todas
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="disponibilidade" value="hoje" <?= $f_disponibilidade === 'hoje' ? 'checked' : '' ?>> Disponível agora
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="disponibilidade" value="data" <?= $f_disponibilidade === 'data' ? 'checked' : '' ?>> Em uma data específica
                    </label>
                    <input type="date" name="data" value="<?= e($f_data) ?>" style="margin-top:6px; width:100%; padding:8px; border:1px solid var(--line); border-radius:2px;">
                </div>

                <input type="hidden" name="ordenar" value="<?= e($f_ordenar) ?>">
                <noscript>
                    <button type="submit" class="btn btn-primary btn-block">Aplicar filtros</button>
                </noscript>
                <a href="<?= BASE_URL ?>/roupas.php" class="btn btn-outline-ink btn-block" style="margin-top:8px;">Limpar filtros</a>
            </form>

            <div>
                <div class="catalog-toolbar">
                    <span class="result-count"><?= count($produtos) ?> peça<?= count($produtos) === 1 ? '' : 's' ?> encontrada<?= count($produtos) === 1 ? '' : 's' ?></span>
                    <form method="get" id="sort-form">
                        <?php foreach ($_GET as $k => $v) if ($k !== 'ordenar') {
                            foreach ((array)$v as $vv) echo '<input type="hidden" name="' . e($k) . ($k === 'marca' || $k === 'tamanho' || $k === 'cor' || $k === 'estilo' ? '[]' : '') . '" value="' . e($vv) . '">';
                        } ?>
                        <select name="ordenar" onchange="document.getElementById('sort-form').submit()">
                            <option value="relevantes" <?= $f_ordenar === 'relevantes' ? 'selected' : '' ?>>Mais relevantes</option>
                            <option value="recentes" <?= $f_ordenar === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                            <option value="menor_preco" <?= $f_ordenar === 'menor_preco' ? 'selected' : '' ?>>Menor preço</option>
                            <option value="maior_preco" <?= $f_ordenar === 'maior_preco' ? 'selected' : '' ?>>Maior preço</option>
                            <option value="mais_alugadas" <?= $f_ordenar === 'mais_alugadas' ? 'selected' : '' ?>>Mais alugadas</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($produtos)): ?>
                    <div class="empty-state">
                        <p>Nenhuma peça encontrada com esses filtros.</p>
                        <a href="<?= BASE_URL ?>/roupas.php" class="btn btn-outline-ink btn-sm">Limpar filtros</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($produtos as $p) include __DIR__ . '/includes/product-card.php'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>window.BASE_URL = '<?= BASE_URL ?>'; window.CSRF_TOKEN = '<?= e(csrf_token()) ?>';</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
