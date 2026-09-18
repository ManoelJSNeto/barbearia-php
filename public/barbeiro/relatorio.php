<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();

// ── Período ───────────────────────────────────────────────────
$periodo = $_GET['periodo'] ?? 'semana';
$dataIni = $_GET['de']      ?? '';
$dataFim = $_GET['ate']     ?? '';

$hoje = new DateTimeImmutable('today');

switch ($periodo) {
    case 'hoje':
        $de  = $hoje->format('Y-m-d');
        $ate = $hoje->format('Y-m-d');
        break;
    case 'semana':
        // segunda a domingo da semana atual
        $diaSem = (int)$hoje->format('N'); // 1=seg … 7=dom
        $de  = $hoje->modify('-' . ($diaSem - 1) . ' days')->format('Y-m-d');
        $ate = $hoje->modify('+' . (7 - $diaSem) . ' days')->format('Y-m-d');
        break;
    case 'mes':
        $de  = $hoje->format('Y-m-01');
        $ate = $hoje->format('Y-m-t');
        break;
    case 'mes_anterior':
        $mesAnt = $hoje->modify('first day of last month');
        $de  = $mesAnt->format('Y-m-01');
        $ate = $mesAnt->format('Y-m-t');
        break;
    case 'personalizado':
        // valida formato
        $de  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni) ? $dataIni : $hoje->format('Y-m-01');
        $ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim) ? $dataFim : $hoje->format('Y-m-d');
        if ($de > $ate) [$de, $ate] = [$ate, $de]; // inverte se precisar
        break;
    default:
        $periodo = 'semana';
        $diaSem  = (int)$hoje->format('N');
        $de  = $hoje->modify('-' . ($diaSem - 1) . ' days')->format('Y-m-d');
        $ate = $hoje->modify('+' . (7 - $diaSem) . ' days')->format('Y-m-d');
}

// ── Resumo ────────────────────────────────────────────────────
$resumo = $pdo->prepare(
    "SELECT
        COUNT(*)                                                                   AS total_atendimentos,
        COALESCE(SUM(CASE WHEN status='confirmado' THEN preco END), 0)             AS receita_confirmada,
        COALESCE(SUM(CASE WHEN status IN ('pendente','confirmado') THEN preco END), 0) AS receita_total,
        COUNT(CASE WHEN status='confirmado'     THEN 1 END)                        AS confirmados,
        COUNT(CASE WHEN status='pendente'       THEN 1 END)                        AS pendentes,
        COUNT(CASE WHEN status='cancelado'      THEN 1 END)                        AS cancelados,
        COUNT(CASE WHEN status='nao_confirmado' THEN 1 END)                        AS nao_confirmados
     FROM agendamentos
     WHERE barbeiro_id = ?
       AND DATE(data_hora) BETWEEN ? AND ?"
);
$resumo->execute([$usuario['id'], $de, $ate]);
$res = $resumo->fetch();

// ── Por serviço ───────────────────────────────────────────────
$porServico = $pdo->prepare(
    "SELECT COALESCE(s.nome, c.nome) AS item_nome,
            COUNT(*)                  AS qtd,
            COALESCE(SUM(a.preco), 0) AS receita
     FROM agendamentos a
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos   c ON c.id = a.combo_id
     WHERE a.barbeiro_id = ?
       AND DATE(a.data_hora) BETWEEN ? AND ?
       AND a.status IN ('pendente','confirmado','nao_confirmado')
     GROUP BY item_nome
     ORDER BY receita DESC"
);
$porServico->execute([$usuario['id'], $de, $ate]);
$porServico = $porServico->fetchAll();

// ── Por dia ───────────────────────────────────────────────────
$porDia = $pdo->prepare(
    "SELECT DATE(data_hora) AS dia,
            COUNT(*)         AS qtd,
            COALESCE(SUM(CASE WHEN status='confirmado' THEN preco END), 0) AS receita
     FROM agendamentos
     WHERE barbeiro_id = ?
       AND DATE(data_hora) BETWEEN ? AND ?
       AND status IN ('pendente','confirmado','nao_confirmado')
     GROUP BY dia
     ORDER BY dia ASC"
);
$porDia->execute([$usuario['id'], $de, $ate]);
$porDia = $porDia->fetchAll();

// ── Agendamentos do período ────────────────────────────────────
$agendamentos = $pdo->prepare(
    "SELECT a.data_hora, a.preco, a.duracao_min, a.status,
            cli.nome AS cliente_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios cli ON cli.id = a.cliente_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos   c ON c.id = a.combo_id
     WHERE a.barbeiro_id = ?
       AND DATE(a.data_hora) BETWEEN ? AND ?
       AND a.status != 'cancelado'
     ORDER BY a.data_hora DESC
     LIMIT 100"
);
$agendamentos->execute([$usuario['id'], $de, $ate]);
$agendamentos = $agendamentos->fetchAll();

$statusLabel = [
    'pendente'       => ['Pendente',      'badge--warn'],
    'confirmado'     => ['Confirmado',    'badge--ok'],
    'nao_confirmado' => ['Não confirmado','badge--muted'],
];

$periodoLabel = [
    'hoje'          => 'Hoje',
    'semana'        => 'Esta semana',
    'mes'           => 'Este mês',
    'mes_anterior'  => 'Mês anterior',
    'personalizado' => 'Personalizado',
];

$fmtData = fn(string $d) => (new DateTimeImmutable($d))->format('d/m/Y');

$titulo = 'Relatório financeiro';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div>
      <h1>Relatório financeiro</h1>
      <p class="sub">
        <?= $periodoLabel[$periodo] ?>
        — <?= $fmtData($de) ?> a <?= $fmtData($ate) ?>
      </p>
    </div>
  </div>

  <!-- filtro de período -->
  <form method="get" class="filter-bar" style="margin-bottom:28px;">
    <div class="field">
      <label>Período</label>
      <select name="periodo" id="sel-periodo" onchange="toggleCustom(this.value)">
        <?php foreach ($periodoLabel as $v => $l): ?>
          <option value="<?= $v ?>" <?= $periodo === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="custom-dates" style="display:<?= $periodo === 'personalizado' ? 'contents' : 'none' ?>">
      <div class="field">
        <label>De</label>
        <input type="date" name="de" value="<?= e($de) ?>">
      </div>
      <div class="field">
        <label>Até</label>
        <input type="date" name="ate" value="<?= e($ate) ?>">
      </div>
    </div>
    <button class="btn btn--sm" type="submit">Aplicar</button>
  </form>
  <script>
  function toggleCustom(v) {
    const el = document.getElementById('custom-dates');
    el.style.display = v === 'personalizado' ? 'contents' : 'none';
  }
  </script>

  <!-- cards de resumo -->
  <div class="panel-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); margin-bottom:32px;">
    <div class="stat-card" style="border-top:3px solid var(--accent);">
      <p class="stat-label">Receita confirmada</p>
      <p class="stat-value" style="font-size:1.6rem; color:var(--accent);">
        R$ <?= number_format((float)$res['receita_confirmada'], 2, ',', '.') ?>
      </p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Atendimentos</p>
      <p class="stat-value"><?= (int)$res['total_atendimentos'] ?></p>
      <p class="stat-sub">no período</p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Confirmados</p>
      <p class="stat-value" style="color:var(--ok)"><?= (int)$res['confirmados'] ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Pendentes</p>
      <p class="stat-value" style="color:var(--warn)"><?= (int)$res['pendentes'] ?></p>
    </div>
    <div class="stat-card">
      <p class="stat-label">Receita total</p>
      <p class="stat-value" style="font-size:1.4rem;">
        R$ <?= number_format((float)$res['receita_total'], 2, ',', '.') ?>
      </p>
      <p class="stat-sub">confirmados + pendentes</p>
    </div>
  </div>

  <?php if ($porServico || $porDia): ?>
  <div class="panel-cols" style="margin-bottom:36px;">

    <!-- por serviço -->
    <?php if ($porServico): ?>
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem">Por serviço</h2>
        <span class="section-head-line"></span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Serviço / Combo</th><th>Qtd</th><th>Receita</th></tr></thead>
          <tbody>
            <?php foreach ($porServico as $r): ?>
            <tr>
              <td><?= e($r['item_nome']) ?></td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$r['qtd'] ?></td>
              <td style="color:var(--accent); font-weight:500;">R$ <?= number_format((float)$r['receita'],2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- por dia -->
    <?php if ($porDia): ?>
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem">Por dia</h2>
        <span class="section-head-line"></span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Data</th><th>Atend.</th><th>Receita conf.</th></tr></thead>
          <tbody>
            <?php
            $diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            foreach ($porDia as $r):
              $dObj = new DateTimeImmutable($r['dia']);
            ?>
            <tr>
              <td style="white-space:nowrap; font-size:13px;">
                <?= $diasSemana[(int)$dObj->format('w')] ?>, <?= $dObj->format('d/m') ?>
              </td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$r['qtd'] ?></td>
              <td style="color:var(--accent);">
                <?= $r['receita'] > 0 ? 'R$ ' . number_format((float)$r['receita'],2,',','.') : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <!-- lista de agendamentos -->
  <div class="section-head">
    <h2 style="font-size:1.2rem">Agendamentos do período</h2>
    <span class="section-head-line"></span>
  </div>

  <?php if ($agendamentos): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Data / Hora</th><th>Cliente</th><th>Serviço</th><th>Valor</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($agendamentos as $ag):
            $dt = new DateTimeImmutable($ag['data_hora']);
            [$lbl, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
          ?>
          <tr>
            <td style="font-family:var(--font-mono); font-size:13px; white-space:nowrap;">
              <?= $dt->format('d/m/Y') ?><br>
              <span style="color:var(--accent)"><?= $dt->format('H:i') ?></span>
            </td>
            <td><?= e($ag['cliente_nome']) ?></td>
            <td style="font-size:13px;"><?= e($ag['item_nome']) ?></td>
            <td style="color:var(--accent); white-space:nowrap;">R$ <?= number_format((float)$ag['preco'],2,',','.') ?></td>
            <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card"><p class="muted">Nenhum atendimento no período selecionado.</p></div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
