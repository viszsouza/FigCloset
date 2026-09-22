<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Categorias';
$activeMenu = 'categorias';
$errors = [];

$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
$editing = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $inUse = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $inUse->execute([$id]);
        if ($inUse->fetchColumn() > 0) {
            flash_set('error', 'Não é possível excluir: existem roupas nessa categoria. Desative-a em vez disso.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('success', 'Categoria excluída.');
        }
        redirect('/admin/categorias.php');
    }

    if (isset($_POST['move'])) {
        $id = (int)$_POST['move_id'];
        $dir = $_POST['move'] === 'up' ? -1 : 1;
        $current = db()->prepare('SELECT * FROM categories WHERE id = ?');
        $current->execute([$id]);
        $cat = $current->fetch();
        if ($cat) {
            $neighborStmt = db()->prepare('SELECT * FROM categories WHERE sort_order ' . ($dir < 0 ? '<' : '>') . ' ? ORDER BY sort_order ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1');
            $neighborStmt->execute([$cat['sort_order']]);
            $neighbor = $neighborStmt->fetch();
            if ($neighbor) {
                db()->prepare('UPDATE categories SET sort_order = ? WHERE id = ?')->execute([$neighbor['sort_order'], $cat['id']]);
                db()->prepare('UPDATE categories SET sort_order = ? WHERE id = ?')->execute([$cat['sort_order'], $neighbor['id']]);
            }
        }
        redirect('/admin/categorias.php');
    }

    $name = trim($_POST['name'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $id = (int)($_POST['id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Informe o nome da categoria.';
    } else {
        $slug = slugify($name);
        if ($id) {
            db()->prepare('UPDATE categories SET name=?, slug=?, active=? WHERE id=?')->execute([$name, $slug, $active, $id]);
            flash_set('success', 'Categoria atualizada.');
        } else {
            $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();
            db()->prepare('INSERT INTO categories (name, slug, sort_order, active) VALUES (?, ?, ?, ?)')->execute([$name, $slug, $maxOrder + 1, $active]);
            flash_set('success', 'Categoria criada.');
        }
        admin_log($admin['id'], "Salvou a categoria \"$name\"");
        redirect('/admin/categorias.php');
    }
}

$categorias = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS total_produtos FROM categories c ORDER BY sort_order')->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="split-narrow-wide">
    <form method="post" class="admin-card">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <h3 style="margin-top:0;"><?= $editing ? 'Editar categoria' : 'Nova categoria' ?></h3>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <div class="form-field"><label>Nome</label><input type="text" name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="form-field" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="active" style="width:auto;" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
            <label style="margin:0;">Ativa</label>
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($editing): ?><a href="<?= BASE_URL ?>/admin/categorias.php" class="btn btn-outline-ink">Cancelar edição</a><?php endif; ?>
    </form>

    <div class="admin-card" style="padding:0;">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Ordem</th><th>Nome</th><th>Status</th><th>Roupas</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td>
                            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="move_id" value="<?= $c['id'] ?>"><button type="submit" name="move" value="up" class="btn-ghost btn-sm">↑</button></form>
                            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="move_id" value="<?= $c['id'] ?>"><button type="submit" name="move" value="down" class="btn-ghost btn-sm">↓</button></form>
                        </td>
                        <td><?= e($c['name']) ?></td>
                        <td><span class="badge badge-<?= $c['active'] ? 'ativo' : 'inativo' ?>"><?= $c['active'] ? 'Ativa' : 'Inativa' ?></span></td>
                        <td><?= (int)$c['total_produtos'] ?></td>
                        <td class="action-links">
                            <a href="<?= BASE_URL ?>/admin/categorias.php?editar=<?= $c['id'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('Excluir esta categoria?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="danger">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
