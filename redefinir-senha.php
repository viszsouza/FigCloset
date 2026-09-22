<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Redefinir senha';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

$stmt = db()->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
$stmt->execute([$token]);
$user = $stmt->fetch();

$errors = [];
$done = false;

if (!$user) {
    $errors[] = 'Este link é inválido ou expirou. Solicite uma nova redefinição de senha.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $senha = $_POST['senha'] ?? '';
    $confirma = $_POST['senha_confirma'] ?? '';
    if (strlen($senha) < 6) $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
    if ($senha !== $confirma) $errors[] = 'As senhas não coincidem.';

    if (empty($errors)) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $upd = db()->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
        $upd->execute([$hash, $user['id']]);
        $done = true;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container">
        <div class="auth-card">
            <h1>Redefinir senha</h1>
            <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
            <?php if ($done): ?>
                <div class="alert alert-success">Senha redefinida com sucesso.</div>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-block">Ir para o login</a>
            <?php elseif ($user): ?>
                <form method="post">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <?= csrf_field() ?>
                    <div class="form-field">
                        <label for="senha">Nova senha</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>
                    <div class="form-field">
                        <label for="senha_confirma">Confirmar nova senha</label>
                        <input type="password" id="senha_confirma" name="senha_confirma" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Salvar nova senha</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
