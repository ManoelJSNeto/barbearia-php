<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$token = trim($_GET['token'] ?? '');

$row = null;
if ($token !== '') {
    $stmt = db()->prepare(
        "SELECT ts.id, ts.usuario_id, u.nome
         FROM tokens_senha ts
         JOIN usuarios u ON u.id = ts.usuario_id
         WHERE ts.token = ? AND ts.usado = 0 AND ts.expira_em > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
}

if (!$row) {
    flash('err', 'Este link é inválido ou já expirou. Solicite um novo.');
    header('Location: /recuperar-senha.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $nova     = $_POST['senha']     ?? '';
    $confirma = $_POST['confirmar'] ?? '';
    $tkPost   = trim($_POST['token'] ?? '');

    if (!hash_equals($token, $tkPost)) {
        flash('err', 'Dados inválidos. Tente novamente.');
        header('Location: /recuperar-senha.php'); exit;
    }
    if (strlen($nova) < 8) {
        flash('err', 'A senha deve ter pelo menos 8 caracteres.');
        header('Location: /resetar-senha.php?token=' . urlencode($token)); exit;
    }
    if (!hash_equals($nova, $confirma)) {
        flash('err', 'As senhas não coincidem.');
        header('Location: /resetar-senha.php?token=' . urlencode($token)); exit;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE usuarios SET senha_hash=?, force_reset=0 WHERE id=?")
            ->execute([password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]), $row['usuario_id']]);
        $pdo->prepare("UPDATE tokens_senha SET usado=1 WHERE id=?")
            ->execute([$row['id']]);
        $pdo->commit();

        flash('ok', 'Senha redefinida. Entre com a nova senha.');
        header('Location: /login.php'); exit;
    } catch (PDOException) {
        $pdo->rollBack();
        flash('err', 'Erro ao salvar. Tente novamente.');
        header('Location: /resetar-senha.php?token=' . urlencode($token)); exit;
    }
}

$titulo = 'Nova senha';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <p class="eyebrow">Redefinição de senha</p>
  <h1>Nova senha</h1>
  <p class="lead" style="font-size:14px; margin-bottom:24px;">
    Olá, <?= e($row['nome']) ?>. Escolha uma senha com pelo menos 8 caracteres.
  </p>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="field">
      <label for="senha">Nova senha</label>
      <input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">
    </div>

    <div class="field">
      <label for="confirmar">Confirmar nova senha</label>
      <input id="confirmar" name="confirmar" type="password" minlength="8" required autocomplete="new-password">
    </div>

    <button class="btn btn--full" type="submit">Redefinir senha</button>
  </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
