<?php
// =========================================================
// Configuração geral do site
// =========================================================

// Ajuste para o caminho real do seu domínio, ex: '' se estiver na raiz
// ou '/visz-locacoes' se estiver numa subpasta.
define('BASE_URL', '/visz-closet/visz-locacoes');

define('SITE_NAME', 'VISZ Closet');

// Dados do banco — ajuste para o ambiente da sua hospedagem
define('DB_HOST', 'localhost');
define('DB_NAME', 'visz_locacoes');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Segurança de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
// Ative a linha abaixo quando o site estiver rodando em HTTPS de produção
// ini_set('session.cookie_secure', 1);

date_default_timezone_set('America/Recife');

session_start();
