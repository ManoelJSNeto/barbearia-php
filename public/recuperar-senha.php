<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Fluxo especial: usuário já logado com force_reset=1
// O link /recuperar-senha.php?force=1 cai aqui
$forcado = isset($_GET['force']) && usuario_logado();

if ($forcado) {
    $usuario = usuario_logado();
    // troca de senha direta para usuário logado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validar_csrf();
        $nova     = $_POST['senha']     ?? '';
        $confirma = $_POST['confirmar'] ?? '';

        if (strlen($nova) < 8) {
            flash('err', 'A senha deve ter pelo menos 8 caracteres.');
            header('Location: /recuperar-senha.php?force=1'); exit;
        }
        if (!hash_equals($nova, $confirma)) {
            flash('err', 'As senhas não coincidem.');
            header('Location: /recuperar-senha.php?force=1'); exit;
        }

        $pdo = db();
        $pdo->prepare("UPDATE usuarios SET senha_hash=?, force_reset=0 WHERE id=?")
            ->execute([password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]), $usuario['id']]);

        // atualiza a sessão para remover o flag
        $_SESSION['usuario']['force_reset'] = 0;

        flash('ok', 'Senha alterada com sucesso.');
        header('Location: ' . destino_por_perfil($usuario['perfil'])); exit;
    }

    $titulo = 'Redefinir senha';
    require __DIR__ . '/../includes/header.php';
    ?>
<div class="auth-wrap">
  <p class="eyebrow">Segurança</p>
  <h1>Redefina sua senha</h1>
  <p class="lead" style="font-size:14px; margin-bottom:24px;">
    Por segurança, você precisa criar uma nova senha antes de continuar.
  </p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="field">
      <label for="senha">Nova senha</label>
      <input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">
    </div>
    <div class="field">
      <label for="confirmar">Confirmar nova senha</label>
      <input id="confirmar" name="confirmar" type="password" minlength="8" required autocomplete="new-password">
    </div>
    <button class="btn btn--full" type="submit">Salvar nova senha</button>
  </form>
</div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if ($email) {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u) {
            // invalida tokens anteriores deste usuário
            db()->prepare("UPDATE tokens_senha SET usado=1 WHERE usuario_id=? AND usado=0")
               ->execute([$u['id']]);

            $token = bin2hex(random_bytes(32));
            db()->prepare(
                "INSERT INTO tokens_senha(usuario_id, token, expira_em)
                 VALUES(?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            )->execute([$u['id'], $token]);

            // envia e-mail com o link de redefinição
            $nomeStmt = db()->prepare('SELECT nome FROM usuarios WHERE id=?');
            $nomeStmt->execute([$u['id']]);
            $nome = (string)($nomeStmt->fetchColumn() ?: '');
            require_once __DIR__ . '/../includes/mail.php';
            mail_recuperar_senha($email, $nome, $token);
        }
    }

    // Sempre mostra a mesma mensagem (evita enumeração de e-mails)
    flash('ok', 'Se o e-mail estiver cadastrado, enviaremos as instruções.');
    header('Location: /login.php');
    exit;
}

$titulo = 'Recuperar senha';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <p class="eyebrow">Recuperação de acesso</p>
  <h1>Recuperar senha</h1>
  <p class="lead" style="font-size:14px; margin-bottom:24px;">
    Informe seu e-mail e enviaremos um link para redefinir a senha.
  </p>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autocomplete="email">
    </div>

    <button class="btn btn--full" type="submit">Enviar instruções</button>
  </form>

  <p class="auth-links"><a href="/login.php">Voltar ao login</a></p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
