<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Acompanhar pedido';
$codigo = trim($_GET['codigo'] ?? '');
$pedido = null;
$itens = [];

if ($codigo !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_code = ?');
    $stmt->execute([$codigo]);
    $pedido = $stmt->fetch();

    // Só mostra detalhes se o pedido for do usuário logado (ou se ele souber o código exato)
    if ($pedido) {
        $itemStmt = db()->prepare('SELECT oi.*, p.name, p.slug,
                                          (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
                                    FROM order_items oi JOIN products p ON p.id = oi.product_id
                                    WHERE oi.order_id = ?');
        $itemStmt->execute([$pedido['id']]);
        $itens = $itemStmt->fetchAll();
    }
}

$steps = [
    'aguardando_confirmacao' => 'Aguardando confirmação',
    'confirmado' => 'Confirmado',
    'preparando' => 'Preparando',
    'disponivel_retirada' => 'Saiu para entrega',
    'em_aluguel' => 'Em aluguel',
    'devolucao_pendente' => 'Devolução pendente',
    'finalizado' => 'Finalizado',
];
$stepKeys = array_keys($steps);

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container" style="max-width:680px;">
        <h1>Acompanhar pedido</h1>
        <form method="get" style="display:flex; gap:10px; margin-bottom:32px; flex-wrap:wrap;">
            <input type="text" name="codigo" value="<?= e($codigo) ?>" placeholder="Ex: VISZ-8K42P" style="flex:1; padding:12px 14px; border:1px solid var(--line); border-radius:2px;">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <?php if ($codigo !== '' && !$pedido): ?>
            <div class="alert alert-error">Nenhum pedido encontrado com esse código.</div>
        <?php elseif ($pedido): ?>
            <div style="border:1px solid var(--line); border-radius:4px; padding:24px; background:var(--white); margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                    <div>
                        <span style="font-size:0.8rem; color:var(--graphite-soft);">Código</span><br>
                        <strong style="font-family:var(--font-display); font-size:1.2rem;"><?= e($pedido['order_code']) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--graphite-soft);">Período</span><br>
                        <strong><?= format_date_br($pedido['start_date']) ?> a <?= format_date_br($pedido['end_date']) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; color:var(--graphite-soft);">Total</span><br>
                        <strong><?= format_price($pedido['total']) ?></strong>
                    </div>
                </div>
                <?php if (!empty($pedido['delivery_address'])): ?>
                <div style="border-top:1px solid var(--line); margin-top:16px; padding-top:16px;">
                    <span style="font-size:0.8rem; color:var(--graphite-soft);">Entrega para</span><br>
                    <strong><?= e($pedido['delivery_recipient']) ?></strong>
                    <p style="font-size:0.85rem; color:var(--graphite-soft); margin:4px 0 0;">
                        <?= e($pedido['delivery_address']) ?>, <?= e($pedido['delivery_number']) ?>
                        <?= !empty($pedido['delivery_complement']) ? ' - ' . e($pedido['delivery_complement']) : '' ?><br>
                        <?= e($pedido['delivery_district']) ?> — <?= e($pedido['delivery_city']) ?>/<?= e($pedido['delivery_state']) ?> · CEP <?= e($pedido['delivery_zip']) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($pedido['status'] === 'cancelado'): ?>
                <div class="alert alert-error">Este pedido foi cancelado.</div>
            <?php else: ?>
                <div class="status-timeline">
                    <?php
                    $currentIdx = array_search($pedido['status'], $stepKeys);
                    foreach ($steps as $key => $label):
                        $idx = array_search($key, $stepKeys);
                        $class = $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'current' : '');
                    ?>
                    <div class="status-step <?= $class ?>">
                        <div>
                            <strong><?= e($label) ?></strong>
                            <?php if ($idx === $currentIdx): ?><span>Status atual do seu pedido</span><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 style="margin-top:28px;">Peças</h3>
            <?php foreach ($itens as $it): ?>
            <div class="order-row">
                <img src="<?= e($it['image_url']) ?>" alt="<?= e($it['name']) ?>">
                <div class="meta">
                    <strong><?= e($it['name']) ?></strong><br>
                    <span style="font-size:0.85rem; color:var(--graphite-soft);">Tamanho <?= e($it['size']) ?></span>
                </div>
                <strong><?= format_price($it['rental_price']) ?></strong>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--graphite-soft);">Digite o código do seu pedido (enviado após a confirmação) para ver o status em tempo real.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
