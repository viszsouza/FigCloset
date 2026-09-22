<?php
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['admin_id'])) {
    admin_log($_SESSION['admin_id'], 'Logout do painel administrativo');
}
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('/admin/login.php');
