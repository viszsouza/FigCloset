<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND role = "cliente"');
$stmt->execute([$id]);
$cliente = $stmt->fetch();
if (!$cliente) { flash_set('error', 'Cliente não encontrado.'); redirect('/admin/clientes.php'); }

$pageTitle = 'Cliente: ' . $cliente['name'];
$activeMenu = 'clientes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    if ($nome !== '' && $sobrenome !== '') {
        db()->prepare('UPDATE users SET name=?, last_name=?, phone=?, updated_at=NOW() WHERE id=?')
            ->execute([$nome, $sobrenome, $telefone, $id]);
        admin_log($admin['id'], "Editou dados não sensíveis do cliente #$id");
        flash_set('success', 'Dados do cliente atualizados.');
        redirect('/admin/cliente-detalhe.php?id=' . $id);
    }
}

$measurements = get_user_measurements($id);
$ordersStmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$ordersStmt->execute([$id]);
$orders = $ordersStmt->fetchAll();

$favStmt = db()->prepare('SELECT p.name, p.slug FROM favorites f JOIN products p ON p.id = f.product_id WHERE f.user_id = ?');
$favStmt->execute([$id]);
$favoritos = $favStmt->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="split-narrow-wide">
    <div>
        <div class="admin-card">
            <h3 style="margin-top:0;">Dados</h3>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-field"><label>Nome</label><input type="text" name="nome" value="<?= e($cliente['name']) ?>"></div>
                <div class="form-field"><label>Sobrenome</label><input type="text" name="sobrenome" value="<?= e($cliente['last_name']) ?>"></div>
                <div class="form-field"><label>E-mail</label><input type="email" value="<?= e($cliente['email']) ?>" disabled></div>
                <div class="form-field"><label>Telefone</label><input type="text" name="telefone" value="<?= e($cliente['phone']) ?>"></div>
                <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
            </form>
        </div>

        <div class="admin-card">
            <h3 style="margin-top:0;">Medidas cadastradas</h3>
            <?php if ($measurements): ?>
                <table class="spec-table">
                    <tr><td>Altura</td><td><?= e((string)($measurements['height'] ?? '—')) ?> cm</td></tr>
                    <tr><td>Busto</td><td><?= e((string)($measurements['bust'] ?? '—')) ?> cm</td></tr>
                    <tr><td>Cintura</td><td><?= e((string)($measurements['waist'] ?? '—')) ?> cm</td></tr>
                    <tr><td>Quadril</td><td><?= e((string)($measurements['hip'] ?? '—')) ?> cm</td></tr>
                    <tr><td>Tamanho habitual</td><td><?= e($measurements['usual_size'] ?? '—') ?></td></tr>
                </table>
            <?php else: ?>
                <p style="color:var(--graphite-soft); font-size:0.88rem;">Este cliente ainda não cadastrou medidas.</p>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <h3 style="margin-top:0;">Favoritos</h3>
            <?php if (empty($favoritos)): ?>
                <p style="color:var(--graphite-soft); font-size:0.88rem;">Nenhum favorito.</p>
            <?php else: foreach ($favoritos as $f): ?>
                <p style="margin:4px 0; font-size:0.88rem;"><?= e($f['name']) ?></p>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-top:0;">Histórico de pedidos</h3>
        <table class="admin-table">
            <thead><tr><th>Código</th><th>Período</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/admin/pedido-detalhe.php?id=<?= $o['id'] ?>" style="color:var(--wine);"><?= e($o['order_code']) ?></a></td>
                    <td><?= format_date_br($o['start_date']) ?> – <?= format_date_br($o['end_date']) ?></td>
                    <td><?= format_price($o['total']) ?></td>
                    <td><span class="badge badge-status"><?= e(status_label($o['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--graphite-soft); padding:20px;">Nenhum pedido ainda.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
