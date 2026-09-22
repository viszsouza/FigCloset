<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND role = "admin"');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($senha, $admin['password_hash'])) {
        $errors[] = 'E-mail ou senha incorretos.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        admin_log($admin['id'], 'Login no painel administrativo');
        redirect('/admin/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Admin VISZ Closet</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
    <div class="auth-card" style="background:var(--white);">
        <div class="logo" style="margin-bottom:20px;">VISZ <span>Admin</span></div>
        <h1 style="font-size:1.4rem;">Acesso administrativo</h1>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-field">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
    </div>
</div>
</body>
</html>
