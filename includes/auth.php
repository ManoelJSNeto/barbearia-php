<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
    session_start();
}
function usuario_logado(): ?array { return $_SESSION['usuario'] ?? null; }
function exigir_login(): void {
    if (!usuario_logado()) { flash('err', 'Entre para acessar esta página.'); header('Location: /login.php'); exit; }
    // se o admin solicitou troca de senha, bloqueia acesso a qualquer página protegida
    if (!empty($_SESSION['usuario']['force_reset'])) {
        $atual = $_SERVER['REQUEST_URI'] ?? '';
        if (!str_starts_with($atual, '/recuperar-senha.php') && !str_starts_with($atual, '/logout.php')) {
            flash('err', 'Por segurança, redefina sua senha para continuar.');
            header('Location: /recuperar-senha.php?force=1'); exit;
        }
    }
}
function exigir_perfil(string ...$perfis): void {
    exigir_login();
    if (!in_array(usuario_logado()['perfil'], $perfis, true)) {
        http_response_code(403);
        // Tenta os caminhos em ordem: container Docker, instalação local, DOCUMENT_ROOT
        $candidatos = [
            dirname(__DIR__) . '/public/403.php',  // local: project/public/403.php
            dirname(__DIR__) . '/html/403.php',    // Docker: /var/www/html/403.php
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/403.php',
        ];
        foreach ($candidatos as $p) {
            if ($p !== '/403.php' && is_file($p)) { require $p; exit; }
        }
        // fallback inline
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>403</title>'
           . '<style>body{font-family:sans-serif;padding:40px;background:#F7F5F2;color:#1C1814}'
           . 'h1{font-size:1.8rem;margin-bottom:12px}a{color:#7C5C3E}</style></head>'
           . '<body><h1>403 — Acesso restrito</h1>'
           . '<p>Sua conta não tem permissão para esta página.</p>'
           . '<p style="margin-top:16px"><a href="/">Voltar</a></p></body></html>';
        exit;
    }
}
function eh_admin(): bool { return (usuario_logado()['perfil'] ?? '') === 'admin'; }
function csrf_token(): string { return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); }
function validar_csrf(): void { if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Solicitação inválida. Atualize a página e tente novamente.'); } }
function e(string|null $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function flash(string $tipo, string $msg): void { $_SESSION['flash'] = compact('tipo', 'msg'); }
function destino_por_perfil(string $perfil): string { return match($perfil) { 'admin' => '/admin/dashboard.php', 'barbeiro' => '/barbeiro/dashboard.php', default => '/cliente/dashboard.php' }; }
