<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

$totalRoupas = db()->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn();
$roupasDisponiveis = db()->query("SELECT COUNT(*) FROM products WHERE status='ativo' AND deleted_at IS NULL
    AND id NOT IN (SELECT product_id FROM availability WHERE status IN (" . blocking_statuses_sql() . ") AND start_date <= CURDATE() AND end_date >= CURDATE())")->fetchColumn();
$roupasAlugadas = db()->query("SELECT COUNT(DISTINCT product_id) FROM availability WHERE status = 'em_aluguel'")->fetchColumn();
$pedidosPendentes = db()->query("SELECT COUNT(*) FROM orders WHERE status = 'aguardando_confirmacao'")->fetchColumn();
$pedidosConfirmados = db()->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmado'")->fetchColumn();
$clientesCadastrados = db()->query("SELECT COUNT(*) FROM users WHERE role = 'cliente'")->fetchColumn();
$faturamento = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelado')")->fetchColumn();
$reservasProximas = db()->query("SELECT COUNT(*) FROM orders WHERE start_date >= CURDATE() AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('cancelado','finalizado')")->fetchColumn();

$maisAlugadas = db()->query("SELECT p.name, b.name AS brand_name, COUNT(oi.id) AS total
    FROM order_items oi JOIN products p ON p.id = oi.product_id JOIN brands b ON b.id = p.brand_id
    GROUP BY p.id ORDER BY total DESC LIMIT 5")->fetchAll();

$pedidosRecentes = db()->query("SELECT o.*, u.name, u.last_name FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 6")->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-stat-grid">
    <div class="admin-stat"><div class="num"><?= (int)$totalRoupas ?></div><div class="label">Total de roupas</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$roupasDisponiveis ?></div><div class="label">Roupas disponíveis hoje</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$roupasAlugadas ?></div><div class="label">Roupas em aluguel</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$clientesCadastrados ?></div><div class="label">Clientes cadastrados</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$pedidosPendentes ?></div><div class="label">Pedidos pendentes</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$pedidosConfirmados ?></div><div class="label">Pedidos confirmados</div></div>
    <div class="admin-stat"><div class="num"><?= format_price($faturamento) ?></div><div class="label">Faturamento acumulado</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$reservasProximas ?></div><div class="label">Reservas nos próx. 7 dias</div></div>
</div>

<div class="split-balanced">
    <div class="admin-card">
        <h3 style="margin-top:0;">Pedidos recentes</h3>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Código</th><th>Cliente</th><th>Período</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pedidosRecentes as $o): ?>
                    <tr>
                        <td><?= e($o['order_code']) ?></td>
                        <td><?= e($o['name'] . ' ' . $o['last_name']) ?></td>
                        <td><?= format_date_br($o['start_date']) ?> – <?= format_date_br($o['end_date']) ?></td>
                        <td><span class="badge badge-status"><?= e(status_label($o['status'])) ?></span></td>
                        <td><a href="<?= BASE_URL ?>/admin/pedido-detalhe.php?id=<?= $o['id'] ?>" style="color:var(--wine); font-size:0.82rem;">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-top:0;">Peças mais alugadas</h3>
        <table class="admin-table">
            <thead><tr><th>Peça</th><th>Aluguéis</th></tr></thead>
            <tbody>
            <?php foreach ($maisAlugadas as $m): ?>
                <tr><td><?= e($m['name']) ?><br><span style="color:var(--graphite-soft); font-size:0.78rem;"><?= e($m['brand_name']) ?></span></td><td><?= (int)$m['total'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
