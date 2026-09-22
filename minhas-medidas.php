<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$pageTitle = 'Minhas medidas';

$existing = get_user_measurements($user['id']);
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = ['height','weight','bust','waist','hip','shoulders','leg_length','shoe_size'];
    $values = [];
    foreach ($fields as $f) {
        $v = trim($_POST[$f] ?? '');
        $values[$f] = ($v === '') ? null : (int)$v;
    }
    $usualSize = trim($_POST['usual_size'] ?? '');

    if (!$values['bust'] || !$values['waist'] || !$values['hip']) {
        $errors[] = 'Busto, cintura e quadril são obrigatórios para calcularmos sua recomendação de tamanho.';
    }

    if (empty($errors)) {
        if ($existing) {
            $stmt = db()->prepare('UPDATE measurements SET height=?, weight=?, bust=?, waist=?, hip=?, shoulders=?, leg_length=?, usual_size=?, shoe_size=?, updated_at=NOW() WHERE user_id=?');
            $stmt->execute([$values['height'], $values['weight'], $values['bust'], $values['waist'], $values['hip'], $values['shoulders'], $values['leg_length'], $usualSize ?: null, $values['shoe_size'], $user['id']]);
        } else {
            $stmt = db()->prepare('INSERT INTO measurements (user_id, height, weight, bust, waist, hip, shoulders, leg_length, usual_size, shoe_size, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$user['id'], $values['height'], $values['weight'], $values['bust'], $values['waist'], $values['hip'], $values['shoulders'], $values['leg_length'], $usualSize ?: null, $values['shoe_size']]);
        }
        flash_set('success', 'Medidas atualizadas com sucesso.');
        redirect('/minhas-medidas.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section-tight">
    <div class="container" style="max-width:760px;">
        <h1>Minhas medidas</h1>
        <p style="color:var(--graphite-soft);">Cadastre suas medidas reais para receber recomendações precisas de tamanho em cada peça do catálogo.</p>

        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

        <div style="display:flex; gap:24px; margin:24px 0; flex-wrap:wrap;">
            <img src="https://picsum.photos/seed/medidasguia/360/440" alt="Guia visual de como medir o corpo" style="border-radius:4px; width:220px;">
            <div style="flex:1; min-width:240px;">
                <h4 style="font-size:0.9rem;">Como medir</h4>
                <ul style="font-size:0.88rem; color:var(--graphite-soft); padding-left:18px;">
                    <li><strong>Busto:</strong> na parte mais larga, sobre os mamilos.</li>
                    <li><strong>Cintura:</strong> na parte mais fina do tronco, geralmente acima do umbigo.</li>
                    <li><strong>Quadril:</strong> na parte mais larga dos quadris/glúteos.</li>
                    <li><strong>Ombros:</strong> de uma ponta a outra, na parte de trás.</li>
                    <li>Use uma fita métrica flexível, sem apertar, com roupas leves.</li>
                </ul>
            </div>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-field">
                    <label for="height">Altura (cm)</label>
                    <input type="number" id="height" name="height" value="<?= e((string)($existing['height'] ?? '')) ?>">
                </div>
                <div class="form-field">
                    <label for="weight">Peso (kg, opcional)</label>
                    <input type="number" id="weight" name="weight" value="<?= e((string)($existing['weight'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="bust">Busto (cm) *</label>
                    <input type="number" id="bust" name="bust" required value="<?= e((string)($existing['bust'] ?? '')) ?>">
                </div>
                <div class="form-field">
                    <label for="waist">Cintura (cm) *</label>
                    <input type="number" id="waist" name="waist" required value="<?= e((string)($existing['waist'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="hip">Quadril (cm) *</label>
                    <input type="number" id="hip" name="hip" required value="<?= e((string)($existing['hip'] ?? '')) ?>">
                </div>
                <div class="form-field">
                    <label for="shoulders">Ombros (cm)</label>
                    <input type="number" id="shoulders" name="shoulders" value="<?= e((string)($existing['shoulders'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="leg_length">Comprimento da perna (cm)</label>
                    <input type="number" id="leg_length" name="leg_length" value="<?= e((string)($existing['leg_length'] ?? '')) ?>">
                </div>
                <div class="form-field">
                    <label for="shoe_size">Número do calçado</label>
                    <input type="number" id="shoe_size" name="shoe_size" value="<?= e((string)($existing['shoe_size'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-field">
                <label for="usual_size">Tamanho habitual de roupa</label>
                <select id="usual_size" name="usual_size">
                    <option value="">Selecione</option>
                    <?php foreach (['PP','P','M','G','GG'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($existing['usual_size'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Salvar medidas</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
