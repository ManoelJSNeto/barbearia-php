<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();

// próximos 4 dias de agenda
$proximos = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.duracao_min, a.preco, a.status,
            u.nome AS cliente_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios u ON u.id = a.cliente_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.barbeiro_id = ?
       AND a.data_hora >= NOW()
       AND a.data_hora <  NOW() + INTERVAL 5 DAY
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

// total do dia de hoje
$hoje = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos
     WHERE barbeiro_id = ? AND DATE(data_hora) = CURDATE()
       AND status IN ('pendente','confirmado')"
);
$hoje->execute([$usuario['id']]);
$totalHoje = (int)$hoje->fetchColumn();

// total do mês
$mes = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos
     WHERE barbeiro_id = ? AND MONTH(data_hora) = MONTH(NOW())
       AND YEAR(data_hora) = YEAR(NOW())
       AND status IN ('confirmado','pendente')"
);
$mes->execute([$usuario['id']]);
$totalMes = (int)$mes->fetchColumn();

$diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$statusLabel = [
    'pendente'   => ['Pendente',   'badge--gold'],
    'confirmado' => ['Confirmado', 'badge--ok'],
];

$titulo = 'Minha agenda';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px;">

  <div class="page-head">
    <h1><?= e($usuario['nome']) ?></h1>
    <p class="sub">Sua agenda dos próximos dias</p>
  </div>

  <!-- stats -->
  <div class="panel-grid" style="margin-bottom:40px;">
    <div class="stat-card">
      <p class="stat-label">Hoje</p>
      <p class="stat-value"><?= $totalHoje ?></p>
      <p class="stat-sub">agendamentos</p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Este mês</p>
      <p class="stat-value"><?= $totalMes ?></p>
      <p class="stat-sub">agendamentos</p>
    </div>
  </div>

  <!-- agenda por dia -->
  <?php if ($porDia): ?>
    <?php foreach ($porDia as $dataStr => $ags):
      $dObj = new DateTimeImmutable($dataStr);
      $isHoje = $dataStr === date('Y-m-d');
    ?>
      <div style="margin-bottom:32px;">
        <p style="font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--gold-pale); margin-bottom:10px;">
          <?= $diasSemana[(int)$dObj->format('w')] ?>, <?= $dObj->format('d/m') ?>
          <?= $isHoje ? ' <span style="color:var(--gold)">· hoje</span>' : '' ?>
        </p>
        <div class="agenda-list">
          <?php foreach ($ags as $ag):
            $dt = new DateTimeImmutable($ag['data_hora']);
            [$lbl, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
          ?>
            <div class="agenda-item">
              <div>
                <div class="agenda-time"><?= $dt->format('H:i') ?></div>
                <div class="agenda-date-label"><?= (int)$ag['duracao_min'] ?> min</div>
              </div>
              <div>
                <p class="agenda-client"><?= e($ag['cliente_nome']) ?></p>
                <p class="agenda-service"><?= e($ag['item_nome']) ?> · R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></p>
              </div>
              <span class="badge <?= $cls ?>"><?= $lbl ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="card">
      <p class="muted">Nenhum agendamento nos próximos dias.</p>
    </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
