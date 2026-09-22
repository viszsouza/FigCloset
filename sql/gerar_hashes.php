<?php
// Execute este script uma única vez via linha de comando (php sql/gerar_hashes.php)
// depois de importar schema.sql + seed_data.sql, para gerar senhas reais e seguras
// para o admin e para os clientes fictícios de demonstração.

$admin_hash  = password_hash('admin123', PASSWORD_DEFAULT);
$client_hash = password_hash('senha123', PASSWORD_DEFAULT);

echo "-- Cole e execute isto no seu banco (ou rode via PHP com PDO):\n\n";
echo "UPDATE users SET password_hash = '{$admin_hash}' WHERE email = 'admin@viszcloset.com.br';\n";
echo "UPDATE users SET password_hash = '{$client_hash}' WHERE role = 'cliente';\n";
