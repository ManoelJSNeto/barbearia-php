<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

exigir_login();

$token = trim($_GET['token'] ?? '');
$pdo   = db();

$stmt = $pdo->prepare(
    "SELECT a.id, a.status, a.data_hora, a.duracao_min, a.preco,
            a.confirmado_em, a.cliente_id,
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

$usuario = usuario_logado();

// token inválido ou agendamento não pertence ao usuário
if (!$ag || (int)$ag['cliente_id'] !== (int)$usuario['id']) {
    http_response_code(404);
    $titulo = 'Link inválido';
    require __DIR__ . '/../includes/header.php';
    echo '<div style="padding:60px 0 80px"><p class="eyebrow">Oops</p><h1 style="font-size:2rem;margin-bottom:12px">Link inválido</h1><p class="muted">Este link não existe ou não pertence à sua conta.</p><p style="margin-top:20px"><a class="btn btn--ghost" href="/">Voltar ao início</a></p></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$dataHora = new DateTimeImmutable($ag['data_hora']);
$limite   = $dataHora->modify('-2 hours'); // pode confirmar até 2h antes
$agora    = new DateTimeImmutable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    if ($ag['status'] !== 'pendente') {
        flash('err', 'Este agendamento não pode ser confirmado (status: ' . $ag['status'] . ').');
        header("Location: /confirmar.php?token={$token}"); exit;
    }
    if ($agora > $limite) {
        $upd = $pdo->prepare("UPDATE agendamentos SET status='nao_confirmado' WHERE id=?");
        $upd->execute([$ag['id']]);
        flash('err', 'O prazo de confirmação expirou (até 2h antes do horário).');
        header("Location: /confirmar.php?token={$token}"); exit;
    }

    $upd = $pdo->prepare(
        "UPDATE agendamentos SET status='confirmado', confirmado_em=NOW() WHERE id=?"
    );
    $upd->execute([$ag['id']]);

    // e-mail de confirmação
    require_once __DIR__ . '/../includes/mail.php';
    $agEmail = $pdo->prepare(
        "SELECT a.data_hora, a.preco,
                cli.nome AS cliente_nome, cli.email AS cliente_email,
                bar.nome AS barbeiro_nome,
                COALESCE(s.nome, c.nome) AS item_nome
         FROM agendamentos a
         JOIN usuarios cli ON cli.id = a.cliente_id
         JOIN usuarios bar ON bar.id = a.barbeiro_id
         LEFT JOIN servicos s ON s.id = a.servico_id
         LEFT JOIN combos c   ON c.id = a.combo_id
         WHERE a.id = ?"
    );
    $agEmail->execute([$ag['id']]);
    $agDados = $agEmail->fetch();
    if ($agDados) { mail_confirmacao($agDados); }

    flash('ok', 'Presença confirmada! Te esperamos em ' . $dataHora->format('d/m') . ' às ' . $dataHora->format('H:i') . '.');
    header('Location: /cliente/dashboard.php'); exit;
}

$titulo = 'Confirmar presença';
require __DIR__ . '/../includes/header.php';
?>

<div style="padding-top: 40px; padding-bottom: 80px; max-width: 560px;">

  <p class="eyebrow">Confirmação</p>
  <h1 style="font-size: 2rem; margin-bottom: 28px;">Confirme sua presença</h1>

  <div class="card" style="border-left: 3px solid var(--gold); margin-bottom: 24px;">
    <table style="width:100%; border-collapse:collapse; font-size:14px;">
      <tr>
        <td style="padding:9px 0; color:var(--muted); width:120px; border-bottom:1px solid var(--border)">Serviço</td>
        <td style="padding:9px 0; border-bottom:1px solid var(--border)"><?= e($ag['item_nome']) ?></td>
      </tr>
      <tr>
        <td style="padding:9px 0; color:var(--muted); border-bottom:1px solid var(--border)">Barbeiro</td>
        <td style="padding:9px 0; border-bottom:1px solid var(--border)"><?= e($ag['barbeiro_nome']) ?></td>
      </tr>
      <tr>
        <td style="padding:9px 0; color:var(--muted); border-bottom:1px solid var(--border)">Data</td>
        <td style="padding:9px 0; border-bottom:1px solid var(--border)"><?= $dataHora->format('d/m/Y') ?></td>
      </tr>
      <tr>
        <td style="padding:9px 0; color:var(--muted); border-bottom:1px solid var(--border)">Horário</td>
        <td style="padding:9px 0; border-bottom:1px solid var(--border)"><?= $dataHora->format('H:i') ?></td>
      </tr>
      <tr>
        <td style="padding:9px 0; color:var(--muted)">Valor</td>
        <td style="padding:9px 0; color:var(--gold); font-weight:500">R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></td>
      </tr>
    </table>
  </div>

  <?php if ($ag['status'] === 'confirmado'): ?>
    <div class="flash flash--ok">Presença já confirmada em <?= (new DateTimeImmutable($ag['confirmado_em']))->format('d/m H:i') ?>.</div>
    <a class="btn btn--ghost" href="/cliente/dashboard.php">Ver meus agendamentos</a>

  <?php elseif ($ag['status'] === 'cancelado'): ?>
    <div class="flash flash--err">Este agendamento foi cancelado.</div>
    <a class="btn btn--ghost" href="/agendar.php">Fazer novo agendamento</a>

  <?php elseif ($ag['status'] === 'nao_confirmado'): ?>
    <div class="flash flash--err">O prazo de confirmação expirou.</div>
    <a class="btn btn--ghost" href="/agendar.php">Fazer novo agendamento</a>

  <?php else: ?>

    <?php if ($agora > $limite): ?>
      <div class="flash flash--err">O prazo para confirmação já expirou (até 2h antes).</div>
    <?php else: ?>
      <p class="muted" style="font-size:13px; margin-bottom:20px;">
        Confirme sua presença até <?= $limite->format('d/m') ?> às <?= $limite->format('H:i') ?>.
      </p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button class="btn" type="submit">Confirmar presença</button>
        <a class="btn btn--ghost" href="/cliente/cancelar.php?token=<?= urlencode($token) ?>" style="margin-left:10px">Cancelar agendamento</a>
      </form>
    <?php endif; ?>

  <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
