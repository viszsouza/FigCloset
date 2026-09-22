<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Marcas';
$activeMenu = 'marcas';
$errors = [];

$editId = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
$editing = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM brands WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $inUse = db()->prepare('SELECT COUNT(*) FROM products WHERE brand_id = ?');
        $inUse->execute([$id]);
        if ($inUse->fetchColumn() > 0) {
            flash_set('error', 'Não é possível excluir: existem roupas cadastradas com essa marca. Desative-a em vez disso.');
        } else {
            db()->prepare('DELETE FROM brands WHERE id = ?')->execute([$id]);
            flash_set('success', 'Marca excluída.');
        }
        redirect('/admin/marcas.php');
    }

    $name = trim($_POST['name'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $id = (int)($_POST['id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Informe o nome da marca.';
    } else {
        $slug = slugify($name);
        if ($id) {
            $stmt = db()->prepare('UPDATE brands SET name=?, slug=?, logo=?, description=?, active=? WHERE id=?');
            $stmt->execute([$name, $slug, $logo ?: null, $description ?: null, $active, $id]);
            flash_set('success', 'Marca atualizada.');
        } else {
            $stmt = db()->prepare('INSERT INTO brands (name, slug, logo, description, active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $slug, $logo ?: null, $description ?: null, $active]);
            flash_set('success', 'Marca criada.');
        }
        admin_log($admin['id'], "Salvou a marca \"$name\"");
        redirect('/admin/marcas.php');
    }
}

$marcas = db()->query('SELECT b.*, (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS total_produtos FROM brands b ORDER BY b.name')->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="split-narrow-wide">
    <form method="post" class="admin-card">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
        <h3 style="margin-top:0;"><?= $editing ? 'Editar marca' : 'Nova marca' ?></h3>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <div class="form-field"><label>Nome</label><input type="text" name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="form-field"><label>Logo (URL, opcional)</label><input type="text" name="logo" value="<?= e($editing['logo'] ?? '') ?>"></div>
        <div class="form-field"><label>Descrição</label><textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea></div>
        <div class="form-field" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="active" style="width:auto;" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
            <label style="margin:0;">Ativa</label>
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($editing): ?><a href="<?= BASE_URL ?>/admin/marcas.php" class="btn btn-outline-ink">Cancelar edição</a><?php endif; ?>
    </form>

    <div class="admin-card" style="padding:0;">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nome</th><th>Status</th><th>Roupas</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($marcas as $m): ?>
                    <tr>
                        <td><?= e($m['name']) ?></td>
                        <td><span class="badge badge-<?= $m['active'] ? 'ativo' : 'inativo' ?>"><?= $m['active'] ? 'Ativa' : 'Inativa' ?></span></td>
                        <td><?= (int)$m['total_produtos'] ?></td>
                        <td class="action-links">
                            <a href="<?= BASE_URL ?>/admin/marcas.php?editar=<?= $m['id'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('Excluir esta marca?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
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
