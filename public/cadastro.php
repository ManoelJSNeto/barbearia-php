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

    $nome  = trim($_POST['nome']  ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';

    if (mb_strlen($nome) < 3) {
        flash('err', 'Nome deve ter pelo menos 3 caracteres.');
        header('Location: /cadastro.php'); exit;
    }
    if (!$email) {
        flash('err', 'Informe um e-mail válido.');
        header('Location: /cadastro.php'); exit;
    }
    if (strlen($senha) < 8) {
        flash('err', 'A senha deve ter pelo menos 8 caracteres.');
        header('Location: /cadastro.php'); exit;
    }

    try {
        $stmt = db()->prepare(
            "INSERT INTO usuarios(nome, email, senha_hash, perfil) VALUES(?, ?, ?, 'cliente')"
        );
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12])]);
        $id = (int)db()->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'          => $id,
            'nome'        => $nome,
            'email'       => $email,
            'perfil'      => 'cliente',
            'force_reset' => 0,
        ];

        flash('ok', 'Conta criada. Agora escolha seu horário.');
        header('Location: /agendar.php');
        exit;
    } catch (PDOException) {
        flash('err', 'Este e-mail já possui cadastro. Tente entrar.');
        header('Location: /cadastro.php');
        exit;
    }
}

$titulo = 'Criar conta';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <p class="eyebrow">Primeiro acesso</p>
  <h1>Crie sua conta</h1>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="field">
      <label for="nome">Nome</label>
      <input id="nome" name="nome" type="text" required maxlength="100" autocomplete="name">
    </div>

    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required maxlength="150" autocomplete="email">
    </div>

    <div class="field">
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">
      <span class="form-hint">Mínimo de 8 caracteres.</span>
    </div>

    <button class="btn btn--full" type="submit">Criar conta</button>
  </form>

  <p class="auth-links">Já tem conta? <a href="/login.php">Entre aqui</a>.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
