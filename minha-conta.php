<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$tab = $_GET['tab'] ?? 'visao-geral';
$pageTitle = 'Minha conta';
$errors = [];

// ---------- Processar formulários ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'dados_pessoais') {
        $nome = trim($_POST['nome'] ?? '');
        $sobrenome = trim($_POST['sobrenome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $nascimento = trim($_POST['nascimento'] ?? '');
        if ($nome === '' || $sobrenome === '' || $telefone === '') {
            $errors[] = 'Nome, sobrenome e telefone são obrigatórios.';
        } else {
            $stmt = db()->prepare('UPDATE users SET name=?, last_name=?, phone=?, birth_date=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$nome, $sobrenome, $telefone, $nascimento ?: null, $user['id']]);
            flash_set('success', 'Dados pessoais atualizados.');
            redirect('/minha-conta.php?tab=dados-pessoais');
        }
    } elseif ($action === 'endereco') {
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        $stmt = db()->prepare('UPDATE users SET address=?, city=?, state=?, zip=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$endereco ?: null, $cidade ?: null, $estado ?: null, $cep ?: null, $user['id']]);
        flash_set('success', 'Endereço atualizado.');
        redirect('/minha-conta.php?tab=endereco');
    } elseif ($action === 'senha') {
        $atual = $_POST['senha_atual'] ?? '';
        $nova = $_POST['senha_nova'] ?? '';
        $confirma = $_POST['senha_confirma'] ?? '';
        if (!password_verify($atual, $user['password_hash'])) {
            $errors[] = 'Senha atual incorreta.';
        } elseif (strlen($nova) < 6) {
            $errors[] = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($nova !== $confirma) {
            $errors[] = 'A confirmação de senha não confere.';
        } else {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$hash, $user['id']]);
            flash_set('success', 'Senha alterada com sucesso.');
            redirect('/minha-conta.php?tab=seguranca');
        }
    }
}

// Recarrega usuário após possível update
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$user = $stmt->fetch();

$measurements = get_user_measurements($user['id']);

$ordersStmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$ordersStmt->execute([$user['id']]);
$orders = $ordersStmt->fetchAll();

$activeOrders = array_filter($orders, fn($o) => !in_array($o['status'], ['finalizado', 'cancelado']));
$nextRental = null;
foreach ($orders as $o) {
    if (in_array($o['status'], ['confirmado', 'preparando', 'disponivel_retirada']) && (!$nextRental || $o['start_date'] < $nextRental['start_date'])) {
        $nextRental = $o;
    }
}

$favCountStmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
$favCountStmt->execute([$user['id']]);
$favCount = $favCountStmt->fetchColumn();

require __DIR__ . '/includes/header.php';

function nav_link($tab, $current, $label, $href) {
    $active = $tab === $current ? 'active' : '';
    echo '<a class="' . $active . '" href="' . $href . '">' . $label . '</a>';
}
?>
<section class="section-tight">
    <div class="container">
        <h1>Olá, <?= e($user['name']) ?></h1>

        <div class="account-layout" style="margin-top:20px;">
            <nav class="account-nav">
                <?php nav_link($tab, 'visao-geral', 'Visão geral', BASE_URL . '/minha-conta.php?tab=visao-geral'); ?>
                <?php nav_link($tab, 'pedidos', 'Meus pedidos', BASE_URL . '/minha-conta.php?tab=pedidos'); ?>
                <a href="<?= BASE_URL ?>/minhas-medidas.php">Minhas medidas</a>
                <a href="<?= BASE_URL ?>/favoritos.php">Favoritos</a>
                <?php nav_link($tab, 'dados-pessoais', 'Dados pessoais', BASE_URL . '/minha-conta.php?tab=dados-pessoais'); ?>
                <?php nav_link($tab, 'endereco', 'Endereço', BASE_URL . '/minha-conta.php?tab=endereco'); ?>
                <?php nav_link($tab, 'seguranca', 'Segurança', BASE_URL . '/minha-conta.php?tab=seguranca'); ?>
                <a href="<?= BASE_URL ?>/logout.php">Sair</a>
            </nav>

            <div>
                <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

                <?php if ($tab === 'visao-geral'): ?>
                    <div class="stat-grid">
                        <div class="stat-card"><div class="num"><?= count($activeOrders) ?></div><div class="label">Pedidos ativos</div></div>
                        <div class="stat-card"><div class="num"><?= $nextRental ? format_date_br($nextRental['start_date']) : '—' ?></div><div class="label">Próximo aluguel</div></div>
                        <div class="stat-card"><div class="num"><?= (int)$favCount ?></div><div class="label">Peças favoritas</div></div>
                        <div class="stat-card"><div class="num"><?= $measurements['usual_size'] ?? '—' ?></div><div class="label">Tamanho habitual</div></div>
                    </div>
                    <h3>Pedidos recentes</h3>
                    <?php if (empty($orders)): ?>
                        <div class="empty-state"><p>Você ainda não fez nenhum pedido.</p><a href="<?= BASE_URL ?>/roupas.php" class="btn btn-primary btn-sm">Explorar catálogo</a></div>
                    <?php else: foreach (array_slice($orders, 0, 3) as $o): ?>
                        <div class="order-row">
                            <div class="meta">
                                <strong><?= e($o['order_code']) ?></strong><br>
                                <span style="font-size:0.85rem; color:var(--graphite-soft);"><?= format_date_br($o['start_date']) ?> a <?= format_date_br($o['end_date']) ?></span>
                            </div>
                            <span class="status-tag"><?= e(status_label($o['status'])) ?></span>
                            <a href="<?= BASE_URL ?>/pedido-status.php?codigo=<?= urlencode($o['order_code']) ?>" class="btn-ghost btn-sm">Ver detalhes</a>
                        </div>
                    <?php endforeach; endif; ?>

                <?php elseif ($tab === 'pedidos'): ?>
                    <h3>Meus pedidos</h3>
                    <?php if (empty($orders)): ?>
                        <div class="empty-state"><p>Você ainda não fez nenhum pedido.</p></div>
                    <?php else: foreach ($orders as $o): ?>
                        <div class="order-row">
                            <div class="meta">
                                <strong><?= e($o['order_code']) ?></strong><br>
                                <span style="font-size:0.85rem; color:var(--graphite-soft);"><?= format_date_br($o['start_date']) ?> a <?= format_date_br($o['end_date']) ?> · <?= format_price($o['total']) ?></span>
                            </div>
                            <span class="status-tag"><?= e(status_label($o['status'])) ?></span>
                            <a href="<?= BASE_URL ?>/pedido-status.php?codigo=<?= urlencode($o['order_code']) ?>" class="btn-ghost btn-sm">Ver detalhes</a>
                        </div>
                    <?php endforeach; endif; ?>

                <?php elseif ($tab === 'dados-pessoais'): ?>
                    <h3>Dados pessoais</h3>
                    <form method="post" style="max-width:480px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="dados_pessoais">
                        <div class="form-row">
                            <div class="form-field"><label>Nome</label><input type="text" name="nome" value="<?= e($user['name']) ?>" required></div>
                            <div class="form-field"><label>Sobrenome</label><input type="text" name="sobrenome" value="<?= e($user['last_name']) ?>" required></div>
                        </div>
                        <div class="form-field"><label>E-mail</label><input type="email" value="<?= e($user['email']) ?>" disabled></div>
                        <div class="form-field"><label>Telefone</label><input type="text" name="telefone" value="<?= e($user['phone']) ?>" required></div>
                        <div class="form-field"><label>Data de nascimento</label><input type="date" name="nascimento" value="<?= e($user['birth_date'] ?? '') ?>"></div>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </form>

                <?php elseif ($tab === 'endereco'): ?>
                    <h3>Endereço</h3>
                    <form method="post" style="max-width:480px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="endereco">
                        <div class="form-field"><label>Endereço</label><input type="text" name="endereco" value="<?= e($user['address'] ?? '') ?>"></div>
                        <div class="form-row">
                            <div class="form-field"><label>Cidade</label><input type="text" name="cidade" value="<?= e($user['city'] ?? '') ?>"></div>
                            <div class="form-field"><label>Estado</label><input type="text" name="estado" maxlength="2" value="<?= e($user['state'] ?? '') ?>"></div>
                        </div>
                        <div class="form-field"><label>CEP</label><input type="text" name="cep" value="<?= e($user['zip'] ?? '') ?>"></div>
                        <button type="submit" class="btn btn-primary">Salvar endereço</button>
                    </form>

                <?php elseif ($tab === 'seguranca'): ?>
                    <h3>Segurança</h3>
                    <form method="post" style="max-width:480px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="senha">
                        <div class="form-field"><label>Senha atual</label><input type="password" name="senha_atual" required></div>
                        <div class="form-field"><label>Nova senha</label><input type="password" name="senha_nova" required></div>
                        <div class="form-field"><label>Confirmar nova senha</label><input type="password" name="senha_confirma" required></div>
                        <button type="submit" class="btn btn-primary">Alterar senha</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
