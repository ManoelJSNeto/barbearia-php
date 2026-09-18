<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Limpa os dados da sessão
$_SESSION = [];

// Remove o cookie de sessão
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destrói e reinicia sessão limpa para poder usar flash()
session_destroy();
session_start();
session_regenerate_id(true);

flash('ok', 'Você saiu da sua conta.');
header('Location: /');
exit;
