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
     JOIN u ON u.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.token_confirm = ?
     LIMIT 1"
);
// alias correto
$stmt = $pdo->prepare(
    "SELECT a.id, a.status, a.data_hora, a.duracao_min, a.preco,
            a.confirmado_em, a.cliente_id,
            bar.nome AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios bar ON bar.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.token_confirm = ?
     LIMIT 1"
);
$stmt->execute([$token]);
$ag = $stmt->fetch();

$usuario = usuario_logado();

if (!$ag || (int)$ag['cliente_id'] !== (int)$usuario['id']) {
    http_response_code(404);
    $titulo = 'Link inválido';
    require __DIR__ . '/../includes/header.php';
    echo '<div style="padding:60px 0 80px"><p class="eyebrow">404</p><h1 style="font-size:1.8rem;margin-bottom:12px">Link inválido</h1><p class="muted">Este link não existe ou não pertence à sua conta.</p><p style="margin-top:20px"><a class="btn btn--ghost" href="/">Voltar ao início</a></p></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$dataHora = new DateTimeImmutable($ag['data_hora']);
$limite   = $dataHora->modify('-2 hours');
$agora    = new DateTimeImmutable();

// POST: confirmar presença
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    if ($ag['status'] !== 'pendente') {
        flash('err', 'Este agendamento não pode ser confirmado (status: ' . $ag['status'] . ').');
        header("Location: /confirmar.php?token={$token}"); exit;
    }
    if ($agora > $limite) {
        $pdo->prepare("UPDATE agendamentos SET status='nao_confirmado' WHERE id=?")->execute([$ag['id']]);
        flash('err', 'O prazo de confirmação expirou (até 2h antes do horário).');
        header("Location: /confirmar.php?token={$token}"); exit;
    }

    $pdo->prepare(
        "UPDATE agendamentos SET status='confirmado', confirmado_em=NOW() WHERE id=?"
    )->execute([$ag['id']]);

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

    // recarrega para mostrar estado atualizado
    header("Location: /confirmar.php?token={$token}");
    exit;
}

// recarrega status atualizado para exibir
$stmt->execute([$token]);
$ag = $stmt->fetch();

$titulo = 'Agendamento';
require __DIR__ . '/../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px; max-width:560px;">

  <?php if ($ag['status'] === 'confirmado'): ?>
    <!-- ── CONFIRMADO ───────────────────────────────────────── -->
    <p class="eyebrow">Tudo certo</p>
    <h1 style="font-size:1.8rem; margin-bottom:20px;">Presença confirmada</h1>

    <div class="booking-summary">
      <div class="booking-summary-row">
        <span class="bsr-label">Serviço</span>
        <span class="bsr-value"><?= e($ag['item_nome']) ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Barbeiro</span>
        <span class="bsr-value"><?= e($ag['barbeiro_nome']) ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Data</span>
        <span class="bsr-value"><?= $dataHora->format('d/m/Y') ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Horário</span>
        <span class="bsr-value" style="font-family:var(--font-mono); color:var(--gold); font-size:1.1rem;">
          <?= $dataHora->format('H:i') ?>
        </span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Valor</span>
        <span class="bsr-value" style="color:var(--gold); font-weight:500;">
          R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?>
        </span>
      </div>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn" href="/agendar.php">Novo agendamento</a>
      <a class="btn btn--ghost" href="/cliente/dashboard.php">Ver meus agendamentos</a>
    </div>

  <?php elseif ($ag['status'] === 'pendente'): ?>
    <!-- ── PENDENTE: mostrar resumo + botão confirmar inline ── -->
    <p class="eyebrow">Agendamento criado</p>
    <h1 style="font-size:1.8rem; margin-bottom:8px;">Confirme sua presença</h1>
    <p class="muted" style="font-size:13px; margin-bottom:24px;">
      Seu horário está reservado mas ainda precisa de confirmação.
    </p>

    <div class="booking-summary">
      <div class="booking-summary-row">
        <span class="bsr-label">Serviço</span>
        <span class="bsr-value"><?= e($ag['item_nome']) ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Barbeiro</span>
        <span class="bsr-value"><?= e($ag['barbeiro_nome']) ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Data</span>
        <span class="bsr-value"><?= $dataHora->format('d/m/Y') ?></span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Horário</span>
        <span class="bsr-value" style="font-family:var(--font-mono); color:var(--gold); font-size:1.1rem;">
          <?= $dataHora->format('H:i') ?>
        </span>
      </div>
      <div class="booking-summary-row">
        <span class="bsr-label">Valor</span>
        <span class="bsr-value" style="color:var(--gold); font-weight:500;">
          R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?>
        </span>
      </div>
    </div>

    <?php if ($agora <= $limite): ?>
      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button class="btn btn--full" type="submit" style="padding:14px;">Confirmar presença agora</button>
      </form>
      <p style="font-size:12px; color:var(--muted); margin-bottom:20px;">
        Prazo para confirmar: até <?= $limite->format('d/m') ?> às <?= $limite->format('H:i') ?>.
      </p>
    <?php else: ?>
      <div class="flash flash--err">O prazo de confirmação expirou.</div>
    <?php endif; ?>

    <a class="btn btn--ghost btn--sm" href="/cliente/cancelar.php?token=<?= urlencode($token) ?>">
      Cancelar este agendamento
    </a>

  <?php elseif ($ag['status'] === 'cancelado'): ?>
    <p class="eyebrow">Cancelado</p>
    <h1 style="font-size:1.8rem; margin-bottom:12px;">Agendamento cancelado</h1>
    <p class="muted" style="margin-bottom:24px;">
      <?= e($ag['item_nome']) ?> com <?= e($ag['barbeiro_nome']) ?> — <?= $dataHora->format('d/m/Y H:i') ?>
    </p>
    <a class="btn" href="/agendar.php">Fazer novo agendamento</a>

  <?php else: ?>
    <p class="eyebrow">Expirado</p>
    <h1 style="font-size:1.8rem; margin-bottom:12px;">Confirmação não realizada</h1>
    <p class="muted" style="margin-bottom:24px;">O prazo de confirmação passou.</p>
    <a class="btn" href="/agendar.php">Fazer novo agendamento</a>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
