<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Configurações';
$activeMenu = 'configuracoes';

$settings = db()->query('SELECT * FROM settings WHERE id = 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tolerance = (int)($_POST['size_tolerance_cm'] ?? 3);
    $instagramUrl = trim($_POST['instagram_url'] ?? '');
    $instagramHandle = trim($_POST['instagram_handle'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $siteName = trim($_POST['site_name'] ?? '');
    $deliveryFee = (float)str_replace(',', '.', $_POST['delivery_fee'] ?? 0);
    $deliveryFreeAbove = (float)str_replace(',', '.', $_POST['delivery_free_above'] ?? 0);
    $deliveryInfo = trim($_POST['delivery_info'] ?? '');

    $stmt = db()->prepare('UPDATE settings SET size_tolerance_cm=?, instagram_url=?, instagram_handle=?, whatsapp_number=?, site_name=?, delivery_fee=?, delivery_free_above=?, delivery_info=? WHERE id = 1');
    $stmt->execute([$tolerance, $instagramUrl, $instagramHandle, $whatsapp, $siteName, $deliveryFee, $deliveryFreeAbove, $deliveryInfo]);
    admin_log($admin['id'], 'Atualizou as configurações da plataforma');
    flash_set('success', 'Configurações salvas.');
    redirect('/admin/configuracoes.php');
}

require __DIR__ . '/../includes/admin-header.php';
?>

<form method="post" class="admin-card" style="max-width:560px;">
    <?= csrf_field() ?>
    <h3 style="margin-top:0;">Motor de recomendação de tamanho</h3>
    <div class="form-field">
        <label>Tolerância de medidas (cm)</label>
        <input type="number" name="size_tolerance_cm" value="<?= e((string)$settings['size_tolerance_cm']) ?>" min="0" max="15">
        <p class="hint">Quanto uma medida pode variar além da faixa ideal do tamanho e ainda ser considerada um "ajuste possível".</p>
    </div>

    <h3>Entrega</h3>
    <div class="form-field">
        <label>Taxa de entrega (R$)</label>
        <input type="number" step="0.01" name="delivery_fee" value="<?= e((string)$settings['delivery_fee']) ?>" min="0">
        <p class="hint">Deixe 0 para entrega sempre gratuita.</p>
    </div>
    <div class="form-field">
        <label>Entrega grátis a partir de (R$)</label>
        <input type="number" step="0.01" name="delivery_free_above" value="<?= e((string)$settings['delivery_free_above']) ?>" min="0">
        <p class="hint">Considera apenas o subtotal do aluguel (sem caução). Deixe 0 para desativar.</p>
    </div>
    <div class="form-field">
        <label>Aviso sobre a área de entrega</label>
        <input type="text" name="delivery_info" value="<?= e($settings['delivery_info']) ?>">
        <p class="hint">Exibido ao cliente na hora de informar o endereço.</p>
    </div>

    <h3>Confirmação via Instagram</h3>
    <div class="form-field">
        <label>URL do perfil do Instagram</label>
        <input type="text" name="instagram_url" value="<?= e($settings['instagram_url']) ?>">
    </div>
    <div class="form-field">
        <label>@ do Instagram (exibido ao cliente)</label>
        <input type="text" name="instagram_handle" value="<?= e($settings['instagram_handle']) ?>">
    </div>

    <h3>WhatsApp</h3>
    <div class="form-field">
        <label>Número (com DDI e DDD, somente números)</label>
        <input type="text" name="whatsapp_number" value="<?= e($settings['whatsapp_number']) ?>">
    </div>

    <h3>Loja</h3>
    <div class="form-field">
        <label>Nome do site</label>
        <input type="text" name="site_name" value="<?= e($settings['site_name']) ?>">
    </div>

    <button type="submit" class="btn btn-primary">Salvar configurações</button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
