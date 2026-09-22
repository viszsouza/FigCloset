<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Roupas';
$activeMenu = 'roupas';

// ---------- Excluir (soft delete se tiver pedidos, senão exclusão definitiva) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_check();
    $id = (int)$_POST['delete_id'];
    $hasOrders = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
    $hasOrders->execute([$id]);
    if ($hasOrders->fetchColumn() > 0) {
        $stmt = db()->prepare('UPDATE products SET deleted_at = NOW(), status = "inativo" WHERE id = ?');
        $stmt->execute([$id]);
        admin_log($admin['id'], "Desativou/soft-delete da roupa #$id (possui histórico de pedidos)");
        flash_set('info', 'Esta peça possui histórico de pedidos, então foi desativada em vez de excluída permanentemente.');
    } else {
        $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        admin_log($admin['id'], "Excluiu permanentemente a roupa #$id");
        flash_set('success', 'Peça excluída com sucesso.');
    }
    redirect('/admin/roupas.php');
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, b.name AS brand_name, c.name AS category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url,
               (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) AS total_alugueis
        FROM products p
        JOIN brands b ON b.id = p.brand_id
        JOIN categories c ON c.id = p.category_id
        WHERE p.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR b.name LIKE ?)';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por nome ou marca" style="padding:9px 12px; border:1px solid var(--line); border-radius:4px; min-width:240px;">
        <button type="submit" class="btn btn-outline-ink btn-sm">Buscar</button>
    </form>
    <a href="<?= BASE_URL ?>/admin/roupa-form.php" class="btn btn-primary btn-sm">+ Nova roupa</a>
</div>

<div class="admin-card" style="padding:0;">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th></th><th>Nome</th><th>Marca</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Aluguéis</th><th>Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($produtos as $p): ?>
                <tr>
                    <td><img class="thumb" src="<?= e($p['image_url'] ?? '') ?>" alt=""></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['brand_name']) ?></td>
                    <td><?= e($p['category_name']) ?></td>
                    <td><?= format_price($p['rental_price']) ?></td>
                    <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><?= (int)$p['total_alugueis'] ?></td>
                    <td class="action-links">
                        <a href="<?= BASE_URL ?>/admin/roupa-form.php?id=<?= $p['id'] ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('Tem certeza que deseja excluir esta roupa?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="danger">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--graphite-soft); padding:30px;">Nenhuma roupa cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
