<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$pageTitle = 'Pedido criado';

$codigo = $_GET['codigo'] ?? '';
$stmt = db()->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
$stmt->execute([$codigo, $user['id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    flash_set('error', 'Pedido não encontrado.');
    redirect('/minha-conta.php');
}

$settings = get_settings();

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container" style="max-width:640px; text-align:center;">
        <h1>Seu pedido foi criado!</h1>
        <p>Para confirmar sua reserva, envie este código pelo Instagram da loja.</p>

        <div class="order-code-box">
            <span style="font-size:0.85rem; color:var(--graphite-soft);">Código do pedido</span>
            <div class="code"><?= e($pedido['order_code']) ?></div>
            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <a href="<?= e($settings['instagram_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary">Confirmar pelo Instagram</a>
                <button type="button" class="btn btn-outline-ink copy-code-btn" data-code="<?= e($pedido['order_code']) ?>">Copiar código</button>
            </div>
        </div>

        <p style="font-size:0.9rem;">
            Status atual: <span class="status-tag" style="background:var(--ivory-dim); padding:5px 12px; border-radius:999px;"><?= e(status_label($pedido['status'])) ?></span>
        </p>
        <p style="font-size:0.85rem; color:var(--graphite-soft);">
            Assim que recebermos e confirmarmos seu código no Instagram (<?= e($settings['instagram_handle']) ?>), atualizaremos o status do seu pedido automaticamente por aqui.
        </p>

        <a href="<?= BASE_URL ?>/pedido-status.php?codigo=<?= urlencode($pedido['order_code']) ?>" class="btn btn-outline-ink" style="margin-top:12px;">Acompanhar este pedido</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
