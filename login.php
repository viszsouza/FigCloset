<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Entrar';

if (current_user()) {
    redirect('/minha-conta.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $errors[] = 'Preencha e-mail e senha.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = "cliente"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['password_hash'])) {
            $errors[] = 'E-mail ou senha incorretos.';
        } elseif ($user['status'] !== 'ativo') {
            $errors[] = 'Sua conta está bloqueada. Entre em contato com a loja.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $next = $_SESSION['login_redirect'] ?? '/minha-conta.php';
            unset($_SESSION['login_redirect']);
            flash_set('success', 'Bem-vinda(o) de volta, ' . $user['name'] . '!');
            redirect($next);
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container">
        <div class="auth-card">
            <h1>Entrar</h1>
            <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>
            <p style="text-align:center; margin-top:14px;"><a href="<?= BASE_URL ?>/recuperar-senha.php" style="font-size:0.85rem; color:var(--graphite-soft);">Esqueci minha senha</a></p>
            <p class="auth-switch">Ainda não tem conta? <a href="<?= BASE_URL ?>/cadastro.php">Criar conta</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
