<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Clientes';
$activeMenu = 'clientes';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    csrf_check();
    $id = (int)$_POST['toggle_id'];
    $stmt = db()->prepare('SELECT status FROM users WHERE id = ? AND role = "cliente"');
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    if ($current) {
        $new = $current === 'ativo' ? 'bloqueado' : 'ativo';
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        admin_log($admin['id'], "Alterou status do cliente #$id para $new");
        flash_set('success', 'Status do cliente atualizado.');
    }
    redirect('/admin/clientes.php');
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS total_pedidos,
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) AS total_favoritos
        FROM users u WHERE u.role = 'cliente'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome ou e-mail" style="padding:9px 12px; border:1px solid var(--line); border-radius:4px; min-width:240px;">
        <button type="submit" class="btn btn-outline-ink btn-sm">Buscar</button>
    </form>
</div>

<div class="admin-card" style="padding:0;">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Pedidos</th><th>Favoritos</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= e($c['name'] . ' ' . $c['last_name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone']) ?></td>
                    <td><?= (int)$c['total_pedidos'] ?></td>
                    <td><?= (int)$c['total_favoritos'] ?></td>
                    <td><span class="badge badge-<?= $c['status'] === 'ativo' ? 'ativo' : 'inativo' ?>"><?= $c['status'] === 'ativo' ? 'Ativo' : 'Bloqueado' ?></span></td>
                    <td class="action-links">
                        <a href="<?= BASE_URL ?>/admin/cliente-detalhe.php?id=<?= $c['id'] ?>">Ver</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Confirma a alteração de status deste cliente?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="toggle_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="<?= $c['status'] === 'ativo' ? 'danger' : '' ?>"><?= $c['status'] === 'ativo' ? 'Bloquear' : 'Desbloquear' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clientes)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--graphite-soft); padding:30px;">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
