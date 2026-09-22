<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Confirmar solicitação';

if (empty($_SESSION['cart'])) {
    flash_set('info', 'Sua sacola está vazia.');
    redirect('/sacola.php');
}

$user = current_user();
if (!$user) {
    $_SESSION['login_redirect'] = '/pedido-confirmar.php';
    flash_set('info', 'Faça login ou crie uma conta para finalizar sua solicitação.');
    redirect('/login.php');
}

// ---------- Processar confirmação ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // ---------- Validar dados de entrega ----------
    $entrega = [
        'recipient'  => trim($_POST['delivery_recipient'] ?? ''),
        'phone'      => trim($_POST['delivery_phone'] ?? ''),
        'zip'        => trim($_POST['delivery_zip'] ?? ''),
        'address'    => trim($_POST['delivery_address'] ?? ''),
        'number'     => trim($_POST['delivery_number'] ?? ''),
        'complement' => trim($_POST['delivery_complement'] ?? ''),
        'district'   => trim($_POST['delivery_district'] ?? ''),
        'city'       => trim($_POST['delivery_city'] ?? ''),
        'state'      => strtoupper(trim($_POST['delivery_state'] ?? '')),
        'notes'      => trim($_POST['delivery_notes'] ?? ''),
    ];

    $erroEntrega = [];
    if ($entrega['recipient'] === '') $erroEntrega[] = 'Informe quem vai receber a entrega.';
    if ($entrega['phone'] === '')     $erroEntrega[] = 'Informe um telefone de contato para a entrega.';
    if ($entrega['zip'] === '')       $erroEntrega[] = 'Informe o CEP.';
    if ($entrega['address'] === '')   $erroEntrega[] = 'Informe o endereço de entrega.';
    if ($entrega['number'] === '')    $erroEntrega[] = 'Informe o número.';
    if ($entrega['district'] === '')  $erroEntrega[] = 'Informe o bairro.';
    if ($entrega['city'] === '')      $erroEntrega[] = 'Informe a cidade.';
    if (strlen($entrega['state']) !== 2) $erroEntrega[] = 'Informe o estado com 2 letras (ex: PE).';

    if (!empty($erroEntrega)) {
        foreach ($erroEntrega as $msg) flash_set('error', $msg);
        $_SESSION['delivery_draft'] = $entrega;
        redirect('/pedido-confirmar.php');
    }

    // Opcionalmente salva o endereço no perfil do cliente
    if (!empty($_POST['salvar_endereco'])) {
        $enderecoCompleto = $entrega['address'] . ', ' . $entrega['number']
            . ($entrega['complement'] !== '' ? ' - ' . $entrega['complement'] : '')
            . ' - ' . $entrega['district'];
        db()->prepare('UPDATE users SET address=?, city=?, state=?, zip=?, updated_at=NOW() WHERE id=?')
            ->execute([$enderecoCompleto, $entrega['city'], $entrega['state'], $entrega['zip'], $user['id']]);
    }

    $cart = $_SESSION['cart'];
    $validItems = [];
    $subtotal = 0;
    $deposit = 0;

    foreach ($cart as $item) {
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND status = "ativo" AND deleted_at IS NULL');
        $stmt->execute([$item['product_id']]);
        $produto = $stmt->fetch();
        if (!$produto) continue;

        if (!is_product_available((int)$produto['id'], $item['start_date'], $item['end_date'])) {
            flash_set('error', 'A peça "' . $produto['name'] . '" não está mais disponível nesse período. Ajuste sua sacola.');
            redirect('/sacola.php');
        }
        $validItems[] = ['produto' => $produto, 'item' => $item];
        $subtotal += (float)$produto['rental_price'];
        $deposit += (float)$produto['deposit'];
    }

    if (empty($validItems)) {
        flash_set('error', 'Sua sacola está vazia ou as peças não estão mais disponíveis.');
        redirect('/sacola.php');
    }

    $overallStart = min(array_column(array_column($validItems, 'item'), 'start_date'));
    $overallEnd = max(array_column(array_column($validItems, 'item'), 'end_date'));
    $deliveryFee = calculate_delivery_fee($subtotal);
    $total = $subtotal + $deposit + $deliveryFee;
    $code = generate_order_code();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO orders
            (order_code, user_id, delivery_recipient, delivery_phone, delivery_zip, delivery_address,
             delivery_number, delivery_complement, delivery_district, delivery_city, delivery_state,
             delivery_notes, delivery_fee, start_date, end_date, subtotal, deposit, total, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "aguardando_confirmacao")');
        $stmt->execute([
            $code, $user['id'], $entrega['recipient'], $entrega['phone'], $entrega['zip'], $entrega['address'],
            $entrega['number'], $entrega['complement'] ?: null, $entrega['district'], $entrega['city'], $entrega['state'],
            $entrega['notes'] ?: null, $deliveryFee, $overallStart, $overallEnd, $subtotal, $deposit, $total,
        ]);
        $orderId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, size, rental_price) VALUES (?, ?, ?, ?)');
        $availStmt = $pdo->prepare('INSERT INTO availability (product_id, order_id, start_date, end_date, status) VALUES (?, ?, ?, ?, "reservado")');

        foreach ($validItems as $row) {
            $itemStmt->execute([$orderId, $row['produto']['id'], $row['item']['size'], $row['produto']['rental_price']]);
            $availStmt->execute([$row['produto']['id'], $orderId, $row['item']['start_date'], $row['item']['end_date']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Não foi possível concluir seu pedido. Tente novamente.');
        redirect('/sacola.php');
    }

    unset($_SESSION['cart'], $_SESSION['delivery_draft']);
    redirect('/pedido-sucesso.php?codigo=' . urlencode($code));
}

// ---------- Tela de revisão (GET) ----------
$items = [];
$subtotal = 0;
$deposit = 0;
foreach ($_SESSION['cart'] as $item) {
    $stmt = db()->prepare('SELECT p.*, b.name AS brand_name,
                                   (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image_url
                            FROM products p JOIN brands b ON b.id = p.brand_id WHERE p.id = ?');
    $stmt->execute([$item['product_id']]);
    $produto = $stmt->fetch();
    if (!$produto) continue;
    $items[] = ['produto' => $produto, 'item' => $item];
    $subtotal += (float)$produto['rental_price'];
    $deposit += (float)$produto['deposit'];
}
$settings = get_settings();
$deliveryFee = calculate_delivery_fee($subtotal);
$total = $subtotal + $deposit + $deliveryFee;

// Pré-preenche com o rascunho anterior (se houve erro) ou com os dados do perfil
$draft = $_SESSION['delivery_draft'] ?? [];
$d = fn($key, $fallback = '') => e($draft[$key] ?? $fallback);

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container" style="max-width:760px;">
        <h1>Revise sua solicitação</h1>
        <p style="color:var(--graphite-soft);">Confira as peças, informe o endereço de entrega e confirme.</p>

        <?php foreach ($items as $row): $p = $row['produto']; $it = $row['item']; ?>
        <div class="order-row">
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
            <div class="meta">
                <strong><?= e($p['name']) ?></strong><br>
                <span style="font-size:0.85rem; color:var(--graphite-soft);">
                    <?= e($p['brand_name']) ?> · Tamanho <?= e($it['size']) ?> · <?= format_date_br($it['start_date']) ?> a <?= format_date_br($it['end_date']) ?>
                </span>
            </div>
            <strong><?= format_price($p['rental_price']) ?></strong>
        </div>
        <?php endforeach; ?>

        <form method="post" style="margin-top:28px;">
            <?= csrf_field() ?>

            <h3>Endereço de entrega</h3>
            <p style="font-size:0.85rem; color:var(--graphite-soft);"><?= e($settings['delivery_info'] ?? '') ?></p>

            <div class="form-row">
                <div class="form-field">
                    <label for="delivery_recipient">Quem vai receber *</label>
                    <input type="text" id="delivery_recipient" name="delivery_recipient" required
                           value="<?= $d('recipient', e($user['name'] . ' ' . $user['last_name'])) ?>">
                </div>
                <div class="form-field">
                    <label for="delivery_phone">Telefone de contato *</label>
                    <input type="text" id="delivery_phone" name="delivery_phone" required
                           value="<?= $d('phone', e($user['phone'])) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="delivery_zip">CEP *</label>
                    <input type="text" id="delivery_zip" name="delivery_zip" required
                           value="<?= $d('zip', e($user['zip'] ?? '')) ?>" placeholder="00000-000">
                </div>
                <div class="form-field">
                    <label for="delivery_number">Número *</label>
                    <input type="text" id="delivery_number" name="delivery_number" required value="<?= $d('number') ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="delivery_address">Endereço (rua/avenida) *</label>
                <input type="text" id="delivery_address" name="delivery_address" required
                       value="<?= $d('address', e($user['address'] ?? '')) ?>">
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="delivery_complement">Complemento</label>
                    <input type="text" id="delivery_complement" name="delivery_complement"
                           value="<?= $d('complement') ?>" placeholder="Apto, bloco, referência">
                </div>
                <div class="form-field">
                    <label for="delivery_district">Bairro *</label>
                    <input type="text" id="delivery_district" name="delivery_district" required value="<?= $d('district') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="delivery_city">Cidade *</label>
                    <input type="text" id="delivery_city" name="delivery_city" required
                           value="<?= $d('city', e($user['city'] ?? '')) ?>">
                </div>
                <div class="form-field">
                    <label for="delivery_state">Estado *</label>
                    <input type="text" id="delivery_state" name="delivery_state" required maxlength="2"
                           value="<?= $d('state', e($user['state'] ?? '')) ?>" placeholder="PE">
                </div>
            </div>

            <div class="form-field">
                <label for="delivery_notes">Observações para a entrega</label>
                <textarea id="delivery_notes" name="delivery_notes" rows="2"
                          placeholder="Ponto de referência, melhor horário para receber..."><?= $d('notes') ?></textarea>
            </div>

            <div class="form-field" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="salvar_endereco" name="salvar_endereco" value="1" style="width:auto;">
                <label for="salvar_endereco" style="margin:0; font-weight:400;">Salvar este endereço no meu perfil</label>
            </div>

            <h3 style="margin-top:28px;">Resumo</h3>
            <table class="spec-table">
                <tr><td>Subtotal do aluguel</td><td style="text-align:right;"><?= format_price($subtotal) ?></td></tr>
                <tr><td>Caução</td><td style="text-align:right;"><?= format_price($deposit) ?></td></tr>
                <tr>
                    <td>Entrega</td>
                    <td style="text-align:right;">
                        <?php if ($deliveryFee > 0): ?>
                            <?= format_price($deliveryFee) ?>
                        <?php else: ?>
                            <span style="color:#2E5A2E;">Grátis</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong><?= format_price($total) ?></strong></td></tr>
            </table>
            <?php
            $freeAbove = (float)($settings['delivery_free_above'] ?? 0);
            if ($deliveryFee > 0 && $freeAbove > 0):
                $falta = $freeAbove - $subtotal;
            ?>
                <p style="font-size:0.82rem; color:var(--graphite-soft);">Faltam <?= format_price($falta) ?> em aluguel para a entrega sair grátis.</p>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">Confirmar solicitação de aluguel</button>
        </form>
        <p style="font-size:0.82rem; color:var(--graphite-soft); margin-top:10px;">Após confirmar, você receberá um código único que deve ser enviado pelo Instagram da loja para validar sua reserva e combinarmos a entrega.</p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
