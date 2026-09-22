<?php
require_once __DIR__ . '/includes/functions.php';
unset($_SESSION['user_id']);
session_regenerate_id(true);
flash_set('info', 'Você saiu da sua conta.');
redirect('/index.php');
