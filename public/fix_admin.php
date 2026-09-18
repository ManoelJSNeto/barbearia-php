<?php
require '/var/www/html/includes/db.php';
$hash = password_hash('12345678', PASSWORD_BCRYPT, ['cost' => 12]);
$pdo  = db();
$stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE email = 'teste@adm.com'");
$stmt->execute([$hash]);
echo "OK — hash atualizado: " . $hash . PHP_EOL;
