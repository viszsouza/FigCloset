<?php
// Espera que config/db.php e includes/functions.php já tenham sido incluídos
// pela página que chama este header.
$__user = current_user();
$__cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$__flashes = flash_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — VISZ Closet' : 'VISZ Closet — Aluguel de roupas de marca' ?></title>
<meta name="description" content="<?= isset($pageDescription) ? e($pageDescription) : 'Alugue peças de marcas exclusivas no tamanho ideal para o seu corpo. Vestidos, blazers, ternos e mais, com curadoria premium.' ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="VISZ Closet">
<meta property="og:title" content="<?= isset($pageTitle) ? e($pageTitle) : 'VISZ Closet' ?>">
<meta property="og:description" content="<?= isset($pageDescription) ? e($pageDescription) : 'Alugue peças de marcas exclusivas no tamanho ideal para o seu corpo.' ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= $jsonLd ?></script><?php endif; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container">
        <a href="<?= BASE_URL ?>/index.php" class="logo">VISZ <span>Closet</span></a>

        <nav class="main-nav" aria-label="Navegação principal">
            <a href="<?= BASE_URL ?>/index.php">Início</a>
            <a href="<?= BASE_URL ?>/roupas.php">Roupas</a>
            <a href="<?= BASE_URL ?>/index.php#marcas">Marcas</a>
            <a href="<?= BASE_URL ?>/index.php#categorias">Categorias</a>
            <a href="<?= BASE_URL ?>/como-funciona.php">Como funciona</a>
            <a href="<?= BASE_URL ?>/sobre.php">Sobre nós</a>
        </nav>

        <div class="header-actions">
            <a href="<?= BASE_URL ?>/roupas.php" class="icon-link" aria-label="Buscar" title="Buscar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </a>
            <a href="<?= BASE_URL ?>/favoritos.php" class="icon-link" aria-label="Favoritos" title="Favoritos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
            </a>
            <a href="<?= BASE_URL ?>/<?= $__user ? 'minha-conta.php' : 'login.php' ?>" class="icon-link" aria-label="Minha conta" title="Minha conta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
            </a>
            <a href="<?= BASE_URL ?>/sacola.php" class="icon-link" aria-label="Sacola" title="Sacola">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                <?php if ($__cartCount > 0): ?><span class="icon-badge"><?= $__cartCount ?></span><?php endif; ?>
            </a>
            <button class="menu-toggle" aria-label="Abrir menu" onclick="document.querySelector('.main-nav').classList.toggle('mobile-open')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>
</header>

<?php if (!empty($__flashes)): ?>
<div class="container" style="padding-top:20px;">
    <?php foreach ($__flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
