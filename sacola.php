<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sua sacola';

// Remover item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_index'])) {
    csrf_check();
    $idx = (int)$_POST['remove_index'];
    if (isset($_SESSION['cart'][$idx])) {
        unset($_SESSION['cart'][$idx]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        flash_set('info', 'Peça removida da sacola.');
    }
    redirect('/sacola.php');
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0;
$deposit = 0;

foreach ($cart as $idx => $item) {
    $stmt = db()->prepare('SELECT p.*, b.name AS brand_name,
                                   (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
                            FROM products p JOIN brands b ON b.id = p.brand_id WHERE p.id = ?');
    $stmt->execute([$item['product_id']]);
    $produto = $stmt->fetch();
    if (!$produto) continue;
    $items[] = ['idx' => $idx, 'produto' => $produto, 'item' => $item];
    $subtotal += (float)$produto['rental_price'];
    $deposit += (float)$produto['deposit'];
}
$deliveryFee = calculate_delivery_fee($subtotal);
$total = $subtotal + $deposit + $deliveryFee;

require __DIR__ . '/includes/header.php';
?>

<section class="section-tight">
    <div class="container">
        <h1>Sua sacola</h1>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <p>Sua sacola está vazia.</p>
                <a href="<?= BASE_URL ?>/roupas.php" class="btn btn-primary btn-sm">Explorar roupas</a>
            </div>
        <?php else: ?>
            <div class="split-wide-narrow">
                <div>
                    <?php foreach ($items as $row): $p = $row['produto']; $it = $row['item']; ?>
                    <div class="order-row">
                        <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
                        <div class="meta">
                            <strong><?= e($p['name']) ?></strong><br>
                            <span style="font-size:0.85rem; color:var(--graphite-soft);">
                                <?= e($p['brand_name']) ?> · Tamanho <?= e($it['size']) ?> · <?= format_date_br($it['start_date']) ?> a <?= format_date_br($it['end_date']) ?>
                            </span>
                        </div>
                        <strong><?= format_price($p['rental_price']) ?></strong>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="remove_index" value="<?= $row['idx'] ?>">
                            <button type="submit" class="btn-ghost btn-sm">Remover</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="border:1px solid var(--line); border-radius:4px; padding:24px; background:var(--white);">
                    <h3 style="font-size:1.1rem;">Resumo</h3>
                    <table class="spec-table">
                        <tr><td>Subtotal do aluguel</td><td style="text-align:right;"><?= format_price($subtotal) ?></td></tr>
                        <tr><td>Caução</td><td style="text-align:right;"><?= format_price($deposit) ?></td></tr>
                        <tr><td>Entrega</td><td style="text-align:right;"><?= $deliveryFee > 0 ? format_price($deliveryFee) : '<span style="color:#2E5A2E;">Grátis</span>' ?></td></tr>
                        <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong><?= format_price($total) ?></strong></td></tr>
                    </table>
                    <a href="<?= BASE_URL ?>/pedido-confirmar.php" class="btn btn-primary btn-block" style="margin-top:16px;">Finalizar solicitação</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
