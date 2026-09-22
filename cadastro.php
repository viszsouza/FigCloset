<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Criar conta';

if (current_user()) {
    redirect('/minha-conta.php');
}

$errors = [];
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senhaConfirma = $_POST['senha_confirma'] ?? '';
    $nascimento = trim($_POST['nascimento'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if ($nome === '' || $sobrenome === '' || $telefone === '') $errors[] = 'Preencha nome, sobrenome e telefone.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (strlen($senha) < 6) $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
    if ($senha !== $senhaConfirma) $errors[] = 'As senhas não coincidem.';

    if (empty($errors)) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Já existe uma conta com esse e-mail.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (name, last_name, email, phone, password_hash, role, birth_date, address, status, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, "cliente", ?, ?, "ativo", NOW(), NOW())');
        $stmt->execute([$nome, $sobrenome, $email, $telefone, $hash, $nascimento ?: null, $endereco ?: null]);
        $userId = db()->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash_set('success', 'Conta criada com sucesso! Que tal cadastrar suas medidas agora?');
        redirect('/minhas-medidas.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container">
        <div class="auth-card" style="max-width:520px;">
            <h1>Criar conta</h1>
            <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-field">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required value="<?= e($old['nome'] ?? '') ?>">
                    </div>
                    <div class="form-field">
                        <label for="sobrenome">Sobrenome</label>
                        <input type="text" id="sobrenome" name="sobrenome" required value="<?= e($old['sobrenome'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required value="<?= e($old['email'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" required value="<?= e($old['telefone'] ?? '') ?>" placeholder="(81) 99999-9999">
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>
                    <div class="form-field">
                        <label for="senha_confirma">Confirmar senha</label>
                        <input type="password" id="senha_confirma" name="senha_confirma" required>
                    </div>
                </div>
                <div class="form-field">
                    <label for="nascimento">Data de nascimento (opcional)</label>
                    <input type="date" id="nascimento" name="nascimento" value="<?= e($old['nascimento'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="endereco">Endereço (opcional)</label>
                    <input type="text" id="endereco" name="endereco" value="<?= e($old['endereco'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Criar conta</button>
            </form>
            <p class="auth-switch">Já tem conta? <a href="<?= BASE_URL ?>/login.php">Entrar</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
