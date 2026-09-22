<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$produto = null;
$imagens = [];
$tamanhosExistentes = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();
    if (!$produto) { flash_set('error', 'Roupa não encontrada.'); redirect('/admin/roupas.php'); }

    $imgStmt = db()->prepare('SELECT image_url FROM product_images WHERE product_id = ? ORDER BY sort_order');
    $imgStmt->execute([$id]);
    $imagens = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    $szStmt = db()->prepare('SELECT * FROM product_sizes WHERE product_id = ?');
    $szStmt->execute([$id]);
    foreach ($szStmt->fetchAll() as $row) {
        $tamanhosExistentes[$row['size']] = $row;
    }
}

$pageTitle = $produto ? 'Editar roupa' : 'Nova roupa';
$activeMenu = 'roupas';
$errors = [];

$marcas = db()->query('SELECT * FROM brands WHERE active = 1 ORDER BY name')->fetchAll();
$categorias = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order')->fetchAll();
$tamanhosPossiveis = ['PP', 'P', 'M', 'G', 'GG'];
$estilos = ['casual' => 'Casual', 'social' => 'Social', 'festa' => 'Festa', 'fashion' => 'Fashion', 'esportivo' => 'Esportivo', 'elegante' => 'Elegante', 'streetwear' => 'Streetwear'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $material = trim($_POST['material'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $style = $_POST['style'] ?? 'casual';
    $price = $_POST['rental_price'] ?? '';
    $deposit = $_POST['deposit'] ?? 0;
    $status = $_POST['status'] ?? 'ativo';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $imageLines = array_filter(array_map('trim', explode("\n", $_POST['images'] ?? '')));

    if ($name === '' || !$brandId || !$categoryId || !is_numeric($price)) {
        $errors[] = 'Preencha nome, marca, categoria e preço corretamente.';
    }

    $sizesToSave = [];
    foreach ($tamanhosPossiveis as $sz) {
        if (!empty($_POST['size_enable'][$sz])) {
            $sizesToSave[$sz] = [
                'bust_min' => $_POST['size'][$sz]['bust_min'] ?? null,
                'bust_max' => $_POST['size'][$sz]['bust_max'] ?? null,
                'waist_min' => $_POST['size'][$sz]['waist_min'] ?? null,
                'waist_max' => $_POST['size'][$sz]['waist_max'] ?? null,
                'hip_min' => $_POST['size'][$sz]['hip_min'] ?? null,
                'hip_max' => $_POST['size'][$sz]['hip_max'] ?? null,
                'stock' => $_POST['size'][$sz]['stock'] ?? 1,
            ];
        }
    }
    if (empty($sizesToSave)) {
        $errors[] = 'Selecione pelo menos um tamanho para esta peça.';
    }

    if (empty($errors)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($produto) {
                // manter slug original; permitir editar demais campos
                $stmt = $pdo->prepare('UPDATE products SET name=?, brand_id=?, category_id=?, description=?, material=?, color=?, style=?, rental_price=?, deposit=?, status=?, featured=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$name, $brandId, $categoryId, $description, $material, $color, $style, $price, $deposit, $status, $featured, $produto['id']]);
                $productId = $produto['id'];
                admin_log($admin['id'], "Editou a roupa #$productId ($name)");
            } else {
                $baseSlug = slugify($name);
                $slug = $baseSlug;
                $n = 1;
                while (true) {
                    $chk = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
                    $chk->execute([$slug]);
                    if (!$chk->fetch()) break;
                    $slug = $baseSlug . '-' . (++$n);
                }
                $stmt = $pdo->prepare('INSERT INTO products (name, slug, brand_id, category_id, description, material, color, style, rental_price, deposit, status, featured, created_at, updated_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->execute([$name, $slug, $brandId, $categoryId, $description, $material, $color, $style, $price, $deposit, $status, $featured]);
                $productId = $pdo->lastInsertId();
                admin_log($admin['id'], "Criou a roupa #$productId ($name)");
            }

            // Imagens: substitui todas
            $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
            $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)');
            foreach ($imageLines as $i => $url) {
                $imgStmt->execute([$productId, $url, $i]);
            }
            if (empty($imageLines)) {
                $imgStmt->execute([$productId, 'https://picsum.photos/seed/prod' . $productId . '/900/1200', 0]);
            }

            // Tamanhos: substitui todos
            $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$productId]);
            $szInsert = $pdo->prepare('INSERT INTO product_sizes (product_id, size, bust_min, bust_max, waist_min, waist_max, hip_min, hip_max, stock) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach ($sizesToSave as $sz => $vals) {
                $szInsert->execute([
                    $productId, $sz,
                    $vals['bust_min'] ?: null, $vals['bust_max'] ?: null,
                    $vals['waist_min'] ?: null, $vals['waist_max'] ?: null,
                    $vals['hip_min'] ?: null, $vals['hip_max'] ?: null,
                    $vals['stock'] ?: 1,
                ]);
            }

            $pdo->commit();
            flash_set('success', 'Roupa salva com sucesso.');
            redirect('/admin/roupas.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Não foi possível salvar a roupa. Tente novamente.';
        }
    }
}

require __DIR__ . '/../includes/admin-header.php';

function v($key, $default, $produto) {
    if (isset($_POST[$key])) return e($_POST[$key]);
    return e($produto[$key] ?? $default);
}
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="admin-card">
    <?= csrf_field() ?>
    <div class="form-row">
        <div class="form-field"><label>Nome</label><input type="text" name="name" required value="<?= v('name', '', $produto) ?>"></div>
        <div class="form-field">
            <label>Marca</label>
            <select name="brand_id" required>
                <option value="">Selecione</option>
                <?php foreach ($marcas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($produto['brand_id'] ?? ($_POST['brand_id'] ?? null)) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>Categoria</label>
            <select name="category_id" required>
                <option value="">Selecione</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($produto['category_id'] ?? ($_POST['category_id'] ?? null)) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Estilo</label>
            <select name="style">
                <?php foreach ($estilos as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($produto['style'] ?? ($_POST['style'] ?? '')) === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-field"><label>Descrição</label><textarea name="description" rows="3"><?= v('description', '', $produto) ?></textarea></div>

    <div class="form-row">
        <div class="form-field"><label>Material</label><input type="text" name="material" value="<?= v('material', '', $produto) ?>"></div>
        <div class="form-field"><label>Cor</label><input type="text" name="color" value="<?= v('color', '', $produto) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-field"><label>Preço do aluguel (R$)</label><input type="number" step="0.01" name="rental_price" required value="<?= v('rental_price', '', $produto) ?>"></div>
        <div class="form-field"><label>Caução (R$)</label><input type="number" step="0.01" name="deposit" value="<?= v('deposit', '0', $produto) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>Status</label>
            <select name="status">
                <option value="ativo" <?= ($produto['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                <option value="inativo" <?= ($produto['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div class="form-field" style="display:flex; align-items:center; gap:8px; padding-top:28px;">
            <input type="checkbox" id="featured" name="featured" style="width:auto;" <?= !empty($produto['featured']) || !empty($_POST['featured']) ? 'checked' : '' ?>>
            <label for="featured" style="margin:0;">Peça em destaque</label>
        </div>
    </div>

    <div class="form-field">
        <label>Imagens (uma URL por linha)</label>
        <textarea name="images" rows="4" placeholder="https://..."><?= e(implode("\n", $imagens)) ?></textarea>
        <p class="hint">Dica: você pode usar links do Google Drive (compartilhados publicamente) ou de qualquer serviço de imagens.</p>
    </div>

    <h4>Tamanhos e medidas (cm)</h4>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th></th><th>Tamanho</th><th>Busto mín.</th><th>Busto máx.</th><th>Cintura mín.</th><th>Cintura máx.</th><th>Quadril mín.</th><th>Quadril máx.</th><th>Estoque</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tamanhosPossiveis as $sz):
                $existing = $tamanhosExistentes[$sz] ?? null;
                $enabled = $existing !== null || (!empty($_POST['size_enable'][$sz]));
                $get = fn($f, $d='') => e((string)($_POST['size'][$sz][$f] ?? $existing[$f] ?? $d));
            ?>
                <tr>
                    <td><input type="checkbox" name="size_enable[<?= $sz ?>]" value="1" <?= $enabled ? 'checked' : '' ?>></td>
                    <td><strong><?= $sz ?></strong></td>
                    <td><input type="number" name="size[<?= $sz ?>][bust_min]" value="<?= $get('bust_min') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][bust_max]" value="<?= $get('bust_max') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][waist_min]" value="<?= $get('waist_min') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][waist_max]" value="<?= $get('waist_max') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][hip_min]" value="<?= $get('hip_min') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][hip_max]" value="<?= $get('hip_max') ?>" style="width:70px;"></td>
                    <td><input type="number" name="size[<?= $sz ?>][stock]" value="<?= $get('stock', '1') ?>" style="width:60px;"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:24px; display:flex; gap:12px;">
        <button type="submit" class="btn btn-primary">Salvar roupa</button>
        <a href="<?= BASE_URL ?>/admin/roupas.php" class="btn btn-outline-ink">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
