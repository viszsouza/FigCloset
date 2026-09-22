<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT o.*, u.name, u.last_name, u.email, u.phone, u.address, u.city, u.state, u.zip
                        FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$stmt->execute([$id]);
$pedido = $stmt->fetch();

if (!$pedido) { flash_set('error', 'Pedido não encontrado.'); redirect('/admin/pedidos.php'); }

$pageTitle = 'Pedido ' . $pedido['order_code'];
$activeMenu = 'pedidos';

$statusOptions = [
    'aguardando_confirmacao' => 'Aguardando confirmação', 'confirmado' => 'Confirmado', 'preparando' => 'Preparando',
    'disponivel_retirada' => 'Saiu para entrega', 'em_aluguel' => 'Em aluguel', 'devolucao_pendente' => 'Devolução pendente',
    'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // ---------- Excluir pedido ----------
    if (isset($_POST['delete_order'])) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Libera a disponibilidade antes de remover o pedido
            $pdo->prepare('DELETE FROM availability WHERE order_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
            $pdo->commit();
            admin_log($admin['id'], "Excluiu o pedido {$pedido['order_code']} do cliente {$pedido['email']}");
            flash_set('success', 'Pedido ' . $pedido['order_code'] . ' excluído e as peças liberadas.');
            redirect('/admin/pedidos.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Não foi possível excluir o pedido.');
            redirect('/admin/pedido-detalhe.php?id=' . $id);
        }
    }

    $novoStatus = $_POST['status'] ?? '';
    if (isset($statusOptions[$novoStatus])) {
        db()->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$novoStatus, $id]);

        // A peça só passa a bloquear a agenda depois da confirmação do admin.
        $availStatus = match ($novoStatus) {
            'aguardando_confirmacao' => 'reservado',
            'em_aluguel' => 'em_aluguel',
            'finalizado', 'cancelado' => 'devolvido',
            default => 'confirmado',
        };
        db()->prepare('UPDATE availability SET status = ? WHERE order_id = ?')->execute([$availStatus, $id]);

        admin_log($admin['id'], "Alterou o status do pedido {$pedido['order_code']} para $novoStatus");
        flash_set('success', 'Status do pedido atualizado.');
        redirect('/admin/pedido-detalhe.php?id=' . $id);
    }
}

$itemStmt = db()->prepare('SELECT oi.*, p.name, p.slug,
                                  (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
                            FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$itemStmt->execute([$id]);
$itens = $itemStmt->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="split-wide-narrow" style="gap:20px;">
    <div class="admin-card">
        <h3 style="margin-top:0;">Peças do pedido</h3>
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

        <table class="spec-table" style="margin-top:16px;">
            <tr><td>Período</td><td style="text-align:right;"><?= format_date_br($pedido['start_date']) ?> a <?= format_date_br($pedido['end_date']) ?></td></tr>
            <tr><td>Subtotal</td><td style="text-align:right;"><?= format_price($pedido['subtotal']) ?></td></tr>
            <tr><td>Caução</td><td style="text-align:right;"><?= format_price($pedido['deposit']) ?></td></tr>
            <tr><td>Entrega</td><td style="text-align:right;"><?= $pedido['delivery_fee'] > 0 ? format_price($pedido['delivery_fee']) : 'Grátis' ?></td></tr>
            <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong><?= format_price($pedido['total']) ?></strong></td></tr>
        </table>
    </div>

    <div>
        <div class="admin-card">
            <h3 style="margin-top:0;">Entrega</h3>
            <?php if (!empty($pedido['delivery_address'])): ?>
                <p style="margin:0 0 6px;"><strong><?= e($pedido['delivery_recipient']) ?></strong></p>
                <p style="font-size:0.88rem; color:var(--graphite-soft); margin:0;">
                    <?= e($pedido['delivery_phone']) ?><br>
                    <?= e($pedido['delivery_address']) ?>, <?= e($pedido['delivery_number']) ?>
                    <?= !empty($pedido['delivery_complement']) ? ' - ' . e($pedido['delivery_complement']) : '' ?><br>
                    <?= e($pedido['delivery_district']) ?> — <?= e($pedido['delivery_city']) ?>/<?= e($pedido['delivery_state']) ?><br>
                    CEP <?= e($pedido['delivery_zip']) ?>
                </p>
                <?php if (!empty($pedido['delivery_notes'])): ?>
                    <p style="font-size:0.85rem; margin-top:10px; padding:10px; background:var(--ivory-dim); border-radius:4px;">
                        <strong>Observações:</strong> <?= e($pedido['delivery_notes']) ?>
                    </p>
                <?php endif; ?>
                <?php
                $enderecoMaps = urlencode(trim($pedido['delivery_address'] . ', ' . $pedido['delivery_number'] . ' - ' . $pedido['delivery_district'] . ', ' . $pedido['delivery_city'] . ' - ' . $pedido['delivery_state']));
                ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= $enderecoMaps ?>" target="_blank" rel="noopener" class="btn btn-outline-ink btn-sm" style="margin-top:12px;">Abrir no Google Maps</a>
            <?php else: ?>
                <p style="font-size:0.88rem; color:var(--graphite-soft);">Este pedido foi criado antes da funcionalidade de entrega e não possui endereço registrado.</p>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <h3 style="margin-top:0;">Cliente</h3>
            <p style="margin:0;"><strong><?= e($pedido['name'] . ' ' . $pedido['last_name']) ?></strong></p>
            <p style="font-size:0.88rem; color:var(--graphite-soft);">
                <?= e($pedido['email']) ?><br>
                <?= e($pedido['phone']) ?>
            </p>
        </div>

        <div class="admin-card">
            <h3 style="margin-top:0;">Status do pedido</h3>
            <p style="font-size:0.85rem; color:var(--graphite-soft);">Código: <strong><?= e($pedido['order_code']) ?></strong></p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-field">
                    <label>Status atual</label>
                    <select name="status">
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $pedido['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Atualizar status</button>
            </form>
            <p style="font-size:0.8rem; color:var(--graphite-soft); margin-top:10px;">
                Ao confirmar, verifique se o cliente enviou o código <strong><?= e($pedido['order_code']) ?></strong> pelo Instagram da loja antes de mudar o status para "Confirmado".
            </p>
            <?php if ($pedido['status'] === 'aguardando_confirmacao'): ?>
            <div class="alert alert-info" style="margin-top:12px; font-size:0.82rem;">
                Enquanto o pedido estiver "Aguardando confirmação", as peças <strong>continuam disponíveis</strong> para outros clientes solicitarem. Elas só ficam bloqueadas na agenda depois que você confirmar este pedido.
            </div>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <h3 style="margin-top:0;">Excluir pedido</h3>
            <p style="font-size:0.84rem; color:var(--graphite-soft);">
                Remove o pedido definitivamente e libera as peças reservadas. Esta ação não pode ser desfeita — para apenas encerrar o pedido, prefira o status "Cancelado".
            </p>
            <form method="post" onsubmit="return confirm('Excluir permanentemente o pedido <?= e($pedido['order_code']) ?>? Esta ação não pode ser desfeita.');">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_order" value="1">
                <button type="submit" class="btn btn-block" style="background:#b33; color:#fff;">Excluir pedido</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
