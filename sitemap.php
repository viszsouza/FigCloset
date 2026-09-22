<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'seu-dominio.com.br';
$base = $scheme . '://' . $host . BASE_URL;

$staticPages = ['/index.php', '/roupas.php', '/como-funciona.php', '/sobre.php', '/login.php', '/cadastro.php'];
$produtos = db()->query('SELECT slug, updated_at FROM products WHERE status = "ativo" AND deleted_at IS NULL')->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPages as $page) {
    echo '  <url><loc>' . e($base . $page) . '</loc></url>' . "\n";
}
foreach ($produtos as $p) {
    echo '  <url><loc>' . e($base . '/produto.php?slug=' . $p['slug']) . '</loc><lastmod>' . date('Y-m-d', strtotime($p['updated_at'])) . '</lastmod></url>' . "\n";
}

echo '</urlset>';
