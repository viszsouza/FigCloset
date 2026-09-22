<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/roupas.php');
}
csrf_check();

$productId = (int)($_POST['product_id'] ?? 0);
$size = trim($_POST['selected_size'] ?? '');
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';

$stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND status = "ativo" AND deleted_at IS NULL');
$stmt->execute([$productId]);
$produto = $stmt->fetch();

$backUrl = '/produto.php?slug=' . urlencode($produto['slug'] ?? '');

if (!$produto) {
    flash_set('error', 'Peça não encontrada.');
    redirect('/roupas.php');
}
if ($size === '') {
    flash_set('error', 'Selecione um tamanho antes de continuar.');
    redirect($backUrl);
}
$sizeStmt = db()->prepare('SELECT * FROM product_sizes WHERE product_id = ? AND size = ?');
$sizeStmt->execute([$productId, $size]);
$sizeRow = $sizeStmt->fetch();
if (!$sizeRow || $sizeRow['stock'] <= 0) {
    flash_set('error', 'Esse tamanho não está disponível no momento.');
    redirect($backUrl);
}

$today = date('Y-m-d');
if (!$startDate || !$endDate || $startDate < $today || $endDate < $startDate) {
    flash_set('error', 'Selecione um período de aluguel válido.');
    redirect($backUrl);
}

if (!is_product_available($productId, $startDate, $endDate)) {
    flash_set('error', 'Essa peça já está reservada nesse período. Escolha outras datas.');
    redirect($backUrl);
}

if (empty($_SESSION['cart'])) $_SESSION['cart'] = [];
$_SESSION['cart'][] = [
    'product_id' => $productId,
    'size' => $size,
    'start_date' => $startDate,
    'end_date' => $endDate,
];

flash_set('success', 'Peça adicionada à sacola.');
redirect('/sacola.php');
