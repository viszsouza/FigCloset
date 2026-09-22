<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Recuperar senha';
$sent = false;
$demoLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = "cliente"');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $upd = db()->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
        $upd->execute([$token, $expires, $user['id']]);
        $demoLink = BASE_URL . '/redefinir-senha.php?token=' . $token;
        // Em produção: envie $demoLink por e-mail (ex.: PHPMailer/SMTP) em vez de exibi-lo na tela.
    }
    $sent = true; // Não revelamos se o e-mail existe ou não, por segurança.
}

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container">
        <div class="auth-card">
            <h1>Recuperar senha</h1>
            <?php if ($sent): ?>
                <div class="alert alert-success">Se esse e-mail estiver cadastrado, enviamos um link de redefinição.</div>
                <?php if ($demoLink): ?>
                    <div class="alert alert-info">
                        Modo de demonstração (sem servidor de e-mail configurado) — use este link para testar:<br>
                        <a href="<?= e($demoLink) ?>"><?= e($demoLink) ?></a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:var(--graphite-soft);">Informe seu e-mail para receber um link de redefinição de senha.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="form-field">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Enviar link</button>
                </form>
            <?php endif; ?>
            <p class="auth-switch"><a href="<?= BASE_URL ?>/login.php">Voltar para o login</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
