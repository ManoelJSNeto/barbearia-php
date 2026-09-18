<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
    session_start();
}
function usuario_logado(): ?array { return $_SESSION['usuario'] ?? null; }
function exigir_login(): void { if (!usuario_logado()) { flash('err', 'Entre para acessar esta página.'); header('Location: /login.php'); exit; } }
function exigir_perfil(string ...$perfis): void { exigir_login(); if (!in_array(usuario_logado()['perfil'], $perfis, true)) { http_response_code(403); require __DIR__ . '/../public/403.php'; exit; } }
function eh_admin(): bool { return (usuario_logado()['perfil'] ?? '') === 'admin'; }
function csrf_token(): string { return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); }
function validar_csrf(): void { if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Solicitação inválida. Atualize a página e tente novamente.'); } }
function e(string|null $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function flash(string $tipo, string $msg): void { $_SESSION['flash'] = compact('tipo', 'msg'); }
function destino_por_perfil(string $perfil): string { return match($perfil) { 'admin' => '/admin/dashboard.php', 'barbeiro' => '/barbeiro/dashboard.php', default => '/cliente/dashboard.php' }; }
