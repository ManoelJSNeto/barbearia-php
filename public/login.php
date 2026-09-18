<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (usuario_logado()) {
    header('Location: ' . destino_por_perfil(usuario_logado()['perfil']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';

    $stmt = db()->prepare(
        'SELECT id, nome, email, senha_hash, perfil, ativo, force_reset FROM usuarios WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
        flash('err', 'E-mail ou senha inválidos.');
        header('Location: /login.php' . (isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : ''));
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'          => (int)$u['id'],
        'nome'        => $u['nome'],
        'email'       => $u['email'],
        'perfil'      => $u['perfil'],
        'force_reset' => (bool)$u['force_reset'],
    ];

    // força troca de senha se admin solicitou
    if ($u['force_reset']) {
        flash('err', 'Por segurança, redefina sua senha para continuar.');
        header('Location: /recuperar-senha.php?force=1');
        exit;
    }

    // redireciona para ?next= se veio de lá (ex: link de confirmação de agendamento)
    $next = $_GET['next'] ?? '';
    if ($next && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        flash('ok', 'Bem-vindo de volta, ' . $u['nome'] . '.');
        header('Location: ' . $next);
        exit;
    }

    flash('ok', 'Bem-vindo de volta, ' . $u['nome'] . '.');
    header('Location: ' . destino_por_perfil($u['perfil']));
    exit;
}

$titulo = 'Entrar';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <p class="eyebrow">Acesse sua conta</p>
  <h1>Entrar</h1>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autocomplete="email">
    </div>

    <div class="field">
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" required autocomplete="current-password">
    </div>

    <button class="btn btn--full" type="submit">Entrar</button>
  </form>

  <p class="auth-links">
    <a href="/recuperar-senha.php">Esqueci minha senha</a>
    &nbsp;·&nbsp;
    <a href="/cadastro.php">Criar conta</a>
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
