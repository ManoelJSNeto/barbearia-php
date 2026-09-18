<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_perfil('admin');

$pdo = db();

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cancelar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare(
            "UPDATE agendamentos SET status='cancelado', cancelado_em=NOW(), cancelado_por='admin' WHERE id=?"
        )->execute([$id]);

        // e-mail de cancelamento
        require_once __DIR__ . '/../../includes/mail.php';
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
        $agEmail->execute([$id]);
        $agDados = $agEmail->fetch();
        if ($agDados) { mail_cancelamento($agDados, 'admin'); }

        flash('ok', 'Agendamento cancelado.');
        header('Location: /admin/agendamentos.php'); exit;
    }
}

// ── Filtros ───────────────────────────────────────────────────
$filtroStatus   = $_GET['status']   ?? '';
$filtroBarbeiro = (int)($_GET['barbeiro'] ?? 0);
$filtroData     = $_GET['data']     ?? '';

$where  = ['1=1'];
$params = [];

if ($filtroStatus && in_array($filtroStatus, ['pendente','confirmado','cancelado','nao_confirmado'], true)) {
    $where[]  = 'a.status = ?';
    $params[] = $filtroStatus;
}
if ($filtroBarbeiro > 0) {
    $where[]  = 'a.barbeiro_id = ?';
    $params[] = $filtroBarbeiro;
}
if ($filtroData) {
    $where[]  = 'DATE(a.data_hora) = ?';
    $params[] = $filtroData;
}

$sql = "SELECT a.id, a.data_hora, a.preco, a.duracao_min, a.status, a.token_confirm,
               cli.nome AS cliente_nome, cli.email AS cliente_email,
               bar.nome AS barbeiro_nome,
               COALESCE(s.nome, c.nome) AS item_nome
        FROM agendamentos a
        JOIN usuarios cli ON cli.id = a.cliente_id
        JOIN usuarios bar ON bar.id = a.barbeiro_id
        LEFT JOIN servicos s ON s.id = a.servico_id
        LEFT JOIN combos c   ON c.id = a.combo_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.data_hora DESC
        LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agendamentos = $stmt->fetchAll();

$barbeiros = $pdo->query(
    "SELECT id, nome FROM usuarios WHERE perfil='barbeiro' AND ativo=1 ORDER BY nome"
)->fetchAll();

$statusLabel = [
    'pendente'       => ['Pendente',       'badge--gold'],
    'confirmado'     => ['Confirmado',     'badge--ok'],
    'cancelado'      => ['Cancelado',      'badge--err'],
    'nao_confirmado' => ['Não confirmado', 'badge--muted'],
];

$titulo = 'Agendamentos';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px;">

  <div class="page-head">
    <h1>Agendamentos</h1>
    <p class="sub">Visão completa com filtros</p>
  </div>

  <!-- filtros -->
  <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:28px; align-items:flex-end;">
    <div class="field" style="margin:0; min-width:140px;">
      <label>Status</label>
      <select name="status">
        <option value="">Todos</option>
        <?php foreach (['pendente','confirmado','cancelado','nao_confirmado'] as $s): ?>
          <option value="<?= $s ?>" <?= $filtroStatus === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="margin:0; min-width:160px;">
      <label>Barbeiro</label>
      <select name="barbeiro">
        <option value="">Todos</option>
        <?php foreach ($barbeiros as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $filtroBarbeiro === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="margin:0;">
      <label>Data</label>
      <input type="date" name="data" value="<?= e($filtroData) ?>">
    </div>
    <button class="btn btn--sm" type="submit">Filtrar</button>
    <?php if ($filtroStatus || $filtroBarbeiro || $filtroData): ?>
      <a class="btn btn--ghost btn--sm" href="/admin/agendamentos.php">Limpar</a>
    <?php endif; ?>
  </form>

  <!-- tabela -->
  <?php if ($agendamentos): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data / Hora</th>
            <th>Cliente</th>
            <th>Barbeiro</th>
            <th>Serviço</th>
            <th>Valor</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agendamentos as $ag):
            $dt = new DateTimeImmutable($ag['data_hora']);
            [$lbl, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
          ?>
          <tr>
            <td style="font-family:var(--font-mono); font-size:13px;">
              <?= $dt->format('d/m/Y') ?><br>
              <span style="color:var(--gold)"><?= $dt->format('H:i') ?></span>
            </td>
            <td>
              <?= e($ag['cliente_nome']) ?><br>
              <span style="font-size:12px; color:var(--muted)"><?= e($ag['cliente_email']) ?></span>
            </td>
            <td><?= e($ag['barbeiro_nome']) ?></td>
            <td style="font-size:13px;"><?= e($ag['item_nome']) ?></td>
            <td style="color:var(--gold)">R$ <?= number_format((float)$ag['preco'],2,',','.') ?></td>
            <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            <td>
              <?php if (in_array($ag['status'], ['pendente','confirmado'], true)): ?>
                <form method="post" onsubmit="return confirm('Cancelar este agendamento?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="cancelar">
                  <input type="hidden" name="id" value="<?= (int)$ag['id'] ?>">
                  <button type="submit" style="background:none;border:none;color:var(--err);font-size:12px;cursor:pointer;padding:0;">Cancelar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card"><p class="muted">Nenhum agendamento encontrado.</p></div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
