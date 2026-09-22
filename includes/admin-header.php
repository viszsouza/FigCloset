<?php
// Espera $admin (usuário admin logado) e opcionalmente $activeMenu no escopo.
$__flashes = flash_get();
$activeMenu = $activeMenu ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — Admin VISZ Closet' : 'Admin VISZ Closet' ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="<?= BASE_URL ?>/admin/index.php" class="logo">VISZ <span>Admin</span></a>
        <nav>
            <a href="<?= BASE_URL ?>/admin/index.php" class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

            <div class="admin-section-label">Catálogo</div>
            <a href="<?= BASE_URL ?>/admin/roupas.php" class="<?= $activeMenu === 'roupas' ? 'active' : '' ?>">Roupas</a>
            <a href="<?= BASE_URL ?>/admin/marcas.php" class="<?= $activeMenu === 'marcas' ? 'active' : '' ?>">Marcas</a>
            <a href="<?= BASE_URL ?>/admin/categorias.php" class="<?= $activeMenu === 'categorias' ? 'active' : '' ?>">Categorias</a>

            <div class="admin-section-label">Operação</div>
            <a href="<?= BASE_URL ?>/admin/pedidos.php" class="<?= $activeMenu === 'pedidos' ? 'active' : '' ?>">Pedidos</a>
            <a href="<?= BASE_URL ?>/admin/calendario.php" class="<?= $activeMenu === 'calendario' ? 'active' : '' ?>">Calendário</a>
            <a href="<?= BASE_URL ?>/admin/clientes.php" class="<?= $activeMenu === 'clientes' ? 'active' : '' ?>">Clientes</a>

            <div class="admin-section-label">Sistema</div>
            <a href="<?= BASE_URL ?>/admin/configuracoes.php" class="<?= $activeMenu === 'configuracoes' ? 'active' : '' ?>">Configurações</a>
            <a href="<?= BASE_URL ?>/index.php">Ver site</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Sair</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1><?= isset($pageTitle) ? e($pageTitle) : 'Painel' ?></h1>
            <span class="admin-user">Olá, <?= e($admin['name'] ?? '') ?></span>
        </div>

        <?php foreach ($__flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
