<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

http_response_code(403);
$titulo = 'Acesso restrito';
require __DIR__ . '/../includes/header.php';
?>

<div style="padding: 80px 0; max-width: 480px;">
  <p class="eyebrow">403</p>
  <h1 style="font-size: 2rem; margin-bottom: 12px;">Acesso restrito</h1>
  <p class="lead" style="font-size: 14px; margin-bottom: 28px;">
    Sua conta não tem permissão para acessar esta página.
  </p>
  <a class="btn btn--ghost" href="/">Voltar ao início</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
