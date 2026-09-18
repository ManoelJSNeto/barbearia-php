<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_perfil('admin');

$pdo = db();

$totalClientes  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='cliente' AND ativo=1")->fetchColumn();
$totalBarbeiros = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='barbeiro' AND ativo=1")->fetchColumn();
$totalHoje      = (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE DATE(data_hora)=CURDATE() AND status IN ('pendente','confirmado')")->fetchColumn();
$totalMes       = (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE MONTH(data_hora)=MONTH(NOW()) AND YEAR(data_hora)=YEAR(NOW()) AND status NOT IN ('cancelado')")->fetchColumn();

// próximos agendamentos do dia
$agHoje = $pdo->query(
    "SELECT a.data_hora, a.status,
            cli.nome AS cliente_nome,
            bar.nome AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios cli ON cli.id = a.cliente_id
     JOIN usuarios bar ON bar.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE DATE(a.data_hora) = CURDATE()
       AND a.status IN ('pendente','confirmado')
     ORDER BY a.data_hora ASC
     LIMIT 15"
)->fetchAll();

$statusLabel = [
    'pendente'   => ['Pendente',   'badge--gold'],
    'confirmado' => ['Confirmado', 'badge--ok'],
];

$titulo = 'Dashboard';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px;">

  <div class="page-head">
    <h1>Dashboard</h1>
    <p class="sub">Visão geral da barbearia</p>
  </div>

  <div class="panel-grid">
    <div class="stat-card">
      <p class="stat-label">Agendamentos hoje</p>
      <p class="stat-value"><?= $totalHoje ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Agendamentos no mês</p>
      <p class="stat-value"><?= $totalMes ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Clientes cadastrados</p>
      <p class="stat-value"><?= $totalClientes ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Barbeiros ativos</p>
      <p class="stat-value"><?= $totalBarbeiros ?></p>
    </div>
  </div>

  <!-- agenda de hoje -->
  <div class="section-head">
    <h2 style="font-size:1.4rem">Agenda de hoje</h2>
    <span class="section-head-line"></span>
  </div>

  <?php if ($agHoje): ?>
    <div class="agenda-list">
      <?php foreach ($agHoje as $ag):
        $dt = new DateTimeImmutable($ag['data_hora']);
        [$lbl, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
      ?>
        <div class="agenda-item">
          <div>
            <div class="agenda-time"><?= $dt->format('H:i') ?></div>
          </div>
          <div>
            <p class="agenda-client"><?= e($ag['cliente_nome']) ?></p>
            <p class="agenda-service"><?= e($ag['item_nome']) ?> · com <?= e($ag['barbeiro_nome']) ?></p>
          </div>
          <span class="badge <?= $cls ?>"><?= $lbl ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card"><p class="muted">Nenhum agendamento para hoje.</p></div>
  <?php endif; ?>

  <!-- links rápidos -->
  <div style="margin-top:36px; display:flex; gap:12px; flex-wrap:wrap;">
    <a class="btn btn--ghost" href="/admin/barbeiros.php">Gerenciar barbeiros</a>
    <a class="btn btn--ghost" href="/admin/servicos.php">Gerenciar serviços</a>
    <a class="btn btn--ghost" href="/admin/configuracoes.php">Configurações SMTP</a>
  </div>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
