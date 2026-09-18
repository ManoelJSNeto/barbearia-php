<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('cliente');

$token   = trim($_GET['token'] ?? '');
$pdo     = db();
$usuario = usuario_logado();

$stmt = $pdo->prepare(
    "SELECT a.id, a.status, a.data_hora, a.cliente_id,
            u.nome AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios u ON u.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.token_confirm = ?
     LIMIT 1"
);
$stmt->execute([$token]);
$ag = $stmt->fetch();

if (!$ag || (int)$ag['cliente_id'] !== (int)$usuario['id']) {
    http_response_code(404);
    flash('err', 'Agendamento não encontrado.');
    header('Location: /cliente/dashboard.php'); exit;
}

if (!in_array($ag['status'], ['pendente', 'confirmado'], true)) {
    flash('err', 'Este agendamento não pode ser cancelado (status: ' . $ag['status'] . ').');
    header('Location: /cliente/dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $pdo->prepare(
        "UPDATE agendamentos
         SET status='cancelado', cancelado_em=NOW(), cancelado_por='cliente'
         WHERE id=?"
    )->execute([$ag['id']]);

    flash('ok', 'Agendamento cancelado.');
    header('Location: /cliente/dashboard.php'); exit;
}

$dt     = new DateTimeImmutable($ag['data_hora']);
$titulo = 'Cancelar agendamento';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px; max-width:520px;">

  <p class="eyebrow">Cancelamento</p>
  <h1 style="font-size:2rem; margin-bottom:24px;">Cancelar agendamento</h1>

  <div class="card" style="border-left:3px solid var(--err); margin-bottom:24px;">
    <p style="font-size:14px; margin-bottom:16px;">Você quer cancelar:</p>
    <p style="font-size:15px; font-weight:500; color:var(--text)"><?= e($ag['item_nome']) ?></p>
    <p style="font-size:13px; color:var(--muted); margin-top:4px">
      com <?= e($ag['barbeiro_nome']) ?> em <?= $dt->format('d/m/Y') ?> às <?= $dt->format('H:i') ?>
    </p>
  </div>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-actions">
      <button class="btn btn--danger" type="submit">Sim, cancelar</button>
      <a class="btn btn--ghost" href="/cliente/dashboard.php">Voltar</a>
    </div>
  </form>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
