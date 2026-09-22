<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['status' => 'login_required']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token inválido']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
if (!$productId) {
    echo json_encode(['status' => 'error']);
    exit;
}

$check = db()->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
$check->execute([$user['id'], $productId]);
$existing = $check->fetch();

if ($existing) {
    $del = db()->prepare('DELETE FROM favorites WHERE id = ?');
    $del->execute([$existing['id']]);
    echo json_encode(['status' => 'ok', 'favorited' => false]);
} else {
    $ins = db()->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
    $ins->execute([$user['id'], $productId]);
    echo json_encode(['status' => 'ok', 'favorited' => true]);
}
