<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();

// ── POST: enviar mensagem rápida ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    if (($_POST['acao'] ?? '') === 'enviar_msg') {
        $ag_id  = (int)($_POST['ag_id']  ?? 0);
        $msg_id = (int)($_POST['msg_id'] ?? 0);

        $agStmt = $pdo->prepare(
            "SELECT a.data_hora,
                    cli.nome AS cliente_nome, cli.email AS cliente_email,
                    bar.nome AS barbeiro_nome,
                    COALESCE(s.nome, c.nome) AS item_nome
             FROM agendamentos a
             JOIN usuarios cli ON cli.id = a.cliente_id
             JOIN usuarios bar ON bar.id = a.barbeiro_id
             LEFT JOIN servicos s ON s.id = a.servico_id
             LEFT JOIN combos c   ON c.id = a.combo_id
             WHERE a.id = ? AND a.barbeiro_id = ?"
        );
        $agStmt->execute([$ag_id, $usuario['id']]);
        $ag = $agStmt->fetch();

        $msgStmt = $pdo->prepare("SELECT titulo, corpo FROM mensagens_rapidas WHERE id=? AND ativo=1");
        $msgStmt->execute([$msg_id]);
        $msg = $msgStmt->fetch();

        if ($ag && $msg) {
            $dt   = new DateTimeImmutable($ag['data_hora']);
            $vars = [
                '{nome_cliente}' => $ag['cliente_nome'],
                '{data_hora}'    => $dt->format('d/m/Y \à\s H:i'),
                '{barbeiro}'     => $ag['barbeiro_nome'],
                '{servico}'      => $ag['item_nome'],
            ];
            $corpo = strtr($msg['corpo'], $vars);
            $ok    = enviar_email(
                $ag['cliente_email'],
                $ag['cliente_nome'],
                $msg['titulo'],
                nl2br(e($corpo))
            );
            flash($ok ? 'ok' : 'err', $ok ? 'Mensagem enviada para ' . $ag['cliente_nome'] . '.' : 'Falha ao enviar. Verifique o SMTP.');
        }
        header('Location: /barbeiro/dashboard.php'); exit;
    }
}

// próximos 5 dias de agenda (todos os status relevantes)
$proximos = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.duracao_min, a.preco, a.status,
            cli.nome  AS cliente_nome,
            cli.email AS cliente_email,
            COALESCE(s.nome, c.nome) AS item_nome,
            COALESCE(s.descricao, c.descricao) AS item_desc,
            cat.nome AS categoria
     FROM agendamentos a
     JOIN usuarios cli ON cli.id = a.cliente_id
     LEFT JOIN servicos s  ON s.id = a.servico_id
     LEFT JOIN combos c    ON c.id = a.combo_id
     LEFT JOIN categorias cat ON cat.id = s.categoria_id
     WHERE a.barbeiro_id = ?
       AND a.data_hora >= CURDATE()
       AND a.data_hora <  CURDATE() + INTERVAL 6 DAY
       AND a.status IN ('pendente','confirmado')
     ORDER BY a.data_hora ASC"
);
$proximos->execute([$usuario['id']]);
$proximos = $proximos->fetchAll();

// agrupar por data
$porDia = [];
foreach ($proximos as $ag) {
    $dia = (new DateTimeImmutable($ag['data_hora']))->format('Y-m-d');
    $porDia[$dia][] = $ag;
}

// stats
$stmtHoje = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos
     WHERE barbeiro_id=? AND DATE(data_hora)=CURDATE()
       AND status IN ('pendente','confirmado')"
);
$stmtHoje->execute([$usuario['id']]);
$totalHoje = (int)$stmtHoje->fetchColumn();

$stmtMes = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos
     WHERE barbeiro_id=? AND MONTH(data_hora)=MONTH(NOW())
       AND YEAR(data_hora)=YEAR(NOW())
       AND status NOT IN ('cancelado')"
);
$stmtMes->execute([$usuario['id']]);
$totalMes = (int)$stmtMes->fetchColumn();

$stmtReceitaMes = $pdo->prepare(
    "SELECT COALESCE(SUM(preco),0) FROM agendamentos
     WHERE barbeiro_id=? AND MONTH(data_hora)=MONTH(NOW())
       AND YEAR(data_hora)=YEAR(NOW())
       AND status='confirmado'"
);
$stmtReceitaMes->execute([$usuario['id']]);
$receitaMes = (float)$stmtReceitaMes->fetchColumn();

$diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$hoje = date('Y-m-d');

// mensagens rápidas disponíveis
$mensagensRapidas = $pdo->query(
    "SELECT id, titulo FROM mensagens_rapidas WHERE ativo=1 ORDER BY titulo"
)->fetchAll();

$titulo = 'Minha agenda';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div>
      <h1><?= e($usuario['nome']) ?></h1>
      <p class="sub">Agenda dos próximos dias</p>
    </div>
    <div class="inline-actions">
      <a class="btn btn--ghost btn--sm" href="/barbeiro/servicos.php">Meus serviços</a>
      <a class="btn btn--ghost btn--sm" href="/barbeiro/bloquear.php">Minhas folgas</a>
      <a class="btn btn--ghost btn--sm" href="/barbeiro/relatorio.php">Relatório</a>
    </div>
  </div>

  <!-- stats -->
  <div class="panel-grid">
    <div class="stat-card">
      <p class="stat-label">Hoje</p>
      <p class="stat-value"><?= $totalHoje ?></p>
      <p class="stat-sub"><?= $totalHoje === 1 ? 'agendamento' : 'agendamentos' ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Este mês</p>
      <p class="stat-value"><?= $totalMes ?></p>
      <p class="stat-sub"><?= $totalMes === 1 ? 'agendamento' : 'agendamentos' ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Receita confirmada</p>
      <p class="stat-value" style="font-size:1.5rem;">R$ <?= number_format($receitaMes, 0, ',', '.') ?></p>
      <p class="stat-sub">este mês</p>
    </div>
  </div>

  <!-- agenda agrupada por dia -->
  <?php if ($porDia): ?>
    <?php foreach ($porDia as $dataStr => $ags):
      $dObj   = new DateTimeImmutable($dataStr);
      $isHoje = ($dataStr === $hoje);
      $nomeDia = $diasSemana[(int)$dObj->format('w')];
    ?>
      <div class="agenda-day">

        <div class="agenda-day-label">
          <span><?= $nomeDia ?>, <?= $dObj->format('d/m') ?></span>
          <?php if ($isHoje): ?>
            <span class="today-tag">hoje</span>
          <?php endif; ?>
          <span style="color:var(--faint); font-weight:400; text-transform:none; letter-spacing:0;">
            — <?= count($ags) ?> <?= count($ags) === 1 ? 'cliente' : 'clientes' ?>
          </span>
        </div>

        <div class="agenda-list">
          <?php foreach ($ags as $ag):
            $dt          = new DateTimeImmutable($ag['data_hora']);
            $confirmado  = $ag['status'] === 'confirmado';
            $cardClass   = $confirmado ? 'agenda-card--confirmed' : 'agenda-card--pending';
          ?>
            <div class="agenda-card <?= $cardClass ?>">

              <!-- horário -->
              <div class="agenda-time">
                <span class="time-main"><?= $dt->format('H:i') ?></span>
                <span class="time-dur"><?= (int)$ag['duracao_min'] ?> min</span>
              </div>

              <!-- detalhes -->
              <div class="agenda-info">
                <div class="agenda-service-name"><?= e($ag['item_nome']) ?></div>
                <div class="agenda-client-name">
                  <?php if ($ag['categoria']): ?>
                    <span style="font-size:11px; color:var(--accent); font-weight:600; text-transform:uppercase; letter-spacing:.04em;">
                      <?= e($ag['categoria']) ?>
                    </span>
                    &ensp;
                  <?php endif; ?>
                  Cliente: <strong style="color:var(--text);"><?= e($ag['cliente_nome']) ?></strong>
                </div>
                <?php if ($ag['item_desc']): ?>
                  <div style="font-size:12px; color:var(--faint); margin-top:3px;"><?= e($ag['item_desc']) ?></div>
                <?php endif; ?>
                <div class="agenda-price">R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></div>
              </div>

              <!-- lado direito: badge + msg rápida -->
              <div class="agenda-card-side">
                <?php if ($confirmado): ?>
                  <span class="badge badge--ok">Confirmado</span>
                <?php else: ?>
                  <span class="badge badge--warn">Pendente</span>
                <?php endif; ?>

                <?php if ($mensagensRapidas): ?>
                <form method="post" style="display:flex; gap:4px; align-items:center; margin-top:6px; flex-wrap:wrap; justify-content:flex-end;">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="enviar_msg">
                  <input type="hidden" name="ag_id" value="<?= (int)$ag['id'] ?>">
                  <select name="msg_id" style="font-size:11px; padding:3px 6px; border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:var(--radius); max-width:140px;">
                    <?php foreach ($mensagensRapidas as $m): ?>
                      <option value="<?= (int)$m['id'] ?>"><?= e($m['titulo']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn--xs btn--ghost">Enviar</button>
                </form>
                <?php endif; ?>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php else: ?>
    <div class="card" style="text-align:center; padding:40px 24px;">
      <p style="color:var(--muted); margin-bottom:14px;">Nenhum agendamento nos próximos 5 dias.</p>
      <a class="btn btn--ghost btn--sm" href="/barbeiro/servicos.php">Verificar serviços cadastrados</a>
    </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
