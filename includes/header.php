<?php
$titulo  = $titulo ?? 'Navalha';
$usuario = usuario_logado();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1A1208">
  <title><?= e($titulo) ?> — Navalha</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=IBM+Plex+Sans:wght@300;400;500&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/global.css">
</head>
<body>

<nav class="nav">
  <div class="nav-inner">

    <a class="brand" href="/">
      <span class="brand-word">Navalha</span>
      <span class="brand-dot"></span>
    </a>

    <button class="nav-toggle" id="nav-toggle" aria-label="Menu" aria-expanded="false">&#9776;</button>

    <ul class="nav-links" id="nav-links" role="list">

      <?php if (!$usuario): ?>
        <li><a href="/#servicos">Serviços</a></li>
        <li><a href="/#barbeiros">Barbeiros</a></li>
        <li><span class="nav-sep" aria-hidden="true"></span></li>
        <li><a href="/login.php">Entrar</a></li>
        <li><a class="nav-btn" href="/cadastro.php">Criar conta</a></li>

      <?php elseif ($usuario['perfil'] === 'admin'): ?>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li><a href="/admin/agendamentos.php">Agenda</a></li>
        <li><a href="/admin/barbeiros.php">Barbeiros</a></li>
        <li><a href="/admin/servicos.php">Serviços</a></li>
        <li><a href="/admin/clientes.php">Clientes</a></li>
        <li><a href="/admin/tickets.php">Tickets</a></li>
        <li><a href="/admin/configuracoes.php">Config</a></li>
        <li><span class="nav-sep" aria-hidden="true"></span></li>
        <li><a href="/logout.php" class="nav-exit">Sair</a></li>

      <?php elseif ($usuario['perfil'] === 'barbeiro'): ?>
        <li><a href="/barbeiro/dashboard.php">Agenda</a></li>
        <li><a href="/barbeiro/servicos.php">Serviços</a></li>
        <li><a href="/barbeiro/portfolio.php">Portfólio</a></li>
        <li><a href="/barbeiro/ticket.php">Sugerir serviço</a></li>
        <li><a href="/barbeiro/bloquear.php">Folgas</a></li>
        <li><span class="nav-sep" aria-hidden="true"></span></li>
        <li><a href="/logout.php" class="nav-exit">Sair</a></li>

      <?php else: ?>
        <li><a class="nav-btn" href="/agendar.php">Agendar</a></li>
        <li><a href="/cliente/dashboard.php">Meus agendamentos</a></li>
        <li><span class="nav-sep" aria-hidden="true"></span></li>
        <li><a href="/logout.php" class="nav-exit">Sair</a></li>
      <?php endif; ?>

    </ul>
  </div>
</nav>

<script>
(function() {
  var btn = document.getElementById('nav-toggle');
  var menu = document.getElementById('nav-links');
  if (btn && menu) {
    btn.addEventListener('click', function() {
      var open = menu.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.innerHTML = open ? '&#10005;' : '&#9776;';
    });
  }
})();
</script>

<div class="wrap">
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div style="padding-top:18px">
    <div class="flash flash--<?= $f['tipo'] === 'ok' ? 'ok' : 'err' ?>"><?= e($f['msg']) ?></div>
  </div>
<?php endif; ?>
