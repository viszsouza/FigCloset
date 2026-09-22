<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Pedidos';
$activeMenu = 'pedidos';

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, u.name, u.last_name, u.email
        FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND o.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (o.order_code LIKE ? OR u.name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY o.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$statusOptions = [
    'aguardando_confirmacao' => 'Aguardando confirmação', 'confirmado' => 'Confirmado', 'preparando' => 'Preparando',
    'disponivel_retirada' => 'Saiu para entrega', 'em_aluguel' => 'Em aluguel', 'devolucao_pendente' => 'Devolução pendente',
    'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado',
];

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por código ou cliente" style="padding:9px 12px; border:1px solid var(--line); border-radius:4px; min-width:220px;">
        <select name="status" style="padding:9px 12px; border:1px solid var(--line); border-radius:4px;">
            <option value="">Todos os status</option>
            <?php foreach ($statusOptions as $key => $label): ?>
                <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-ink btn-sm">Filtrar</button>
    </form>
</div>

<div class="admin-card" style="padding:0;">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Código</th><th>Cliente</th><th>Período</th><th>Valor</th><th>Status</th><th>Criado em</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $o): ?>
                <tr>
                    <td><?= e($o['order_code']) ?></td>
                    <td><?= e($o['name'] . ' ' . $o['last_name']) ?><br><span style="font-size:0.78rem; color:var(--graphite-soft);"><?= e($o['email']) ?></span></td>
                    <td><?= format_date_br($o['start_date']) ?> – <?= format_date_br($o['end_date']) ?></td>
                    <td><?= format_price($o['total']) ?></td>
                    <td><span class="badge badge-status"><?= e(status_label($o['status'])) ?></span></td>
                    <td><?= format_date_br($o['created_at']) ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/pedido-detalhe.php?id=<?= $o['id'] ?>" style="color:var(--wine); font-size:0.82rem;">Ver / gerenciar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--graphite-soft); padding:30px;">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
