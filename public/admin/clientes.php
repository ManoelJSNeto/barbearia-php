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
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'desativar' && $id) {
        // soft delete: desativa cliente e cancela agendamentos futuros
        $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=? AND perfil='cliente'")->execute([$id]);
        $pdo->prepare(
            "UPDATE agendamentos SET status='cancelado', cancelado_em=NOW(), cancelado_por='admin'
             WHERE cliente_id=? AND data_hora > NOW() AND status IN ('pendente','confirmado')"
        )->execute([$id]);
        flash('ok', 'Cliente desativado e agendamentos futuros cancelados.');
        header('Location: /admin/clientes.php'); exit;
    }

    if ($acao === 'reativar' && $id) {
        $pdo->prepare("UPDATE usuarios SET ativo=1 WHERE id=? AND perfil='cliente'")->execute([$id]);
        flash('ok', 'Cliente reativado.');
        header('Location: /admin/clientes.php'); exit;
    }

    if ($acao === 'reset_senha' && $id) {
        $pdo->prepare("UPDATE usuarios SET force_reset=1 WHERE id=? AND perfil='cliente'")->execute([$id]);
        flash('ok', 'Na próxima vez que o cliente acessar, será solicitada troca de senha.');
        header('Location: /admin/clientes.php'); exit;
    }
}

// ── Filtro ────────────────────────────────────────────────────
$busca = trim($_GET['q'] ?? '');
$detalhe = (int)($_GET['ver'] ?? 0);

// ── Lista de clientes ─────────────────────────────────────────
$sql    = "SELECT u.id, u.nome, u.email, u.ativo, u.criado_em,
                  COUNT(DISTINCT a.id) AS total_ag
           FROM usuarios u
           LEFT JOIN agendamentos a ON a.cliente_id = u.id
           WHERE u.perfil = 'cliente'";
$params = [];
if ($busca) {
    $sql   .= " AND (u.nome LIKE ? OR u.email LIKE ?)";
    $params = ["%{$busca}%", "%{$busca}%"];
}
$sql .= " GROUP BY u.id ORDER BY u.criado_em DESC LIMIT 80";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// ── Detalhe do cliente ────────────────────────────────────────
$clienteSel  = null;
$historicoSel = [];
if ($detalhe) {
    $s = $pdo->prepare("SELECT id, nome, email, ativo, criado_em FROM usuarios WHERE id=? AND perfil='cliente'");
    $s->execute([$detalhe]);
    $clienteSel = $s->fetch();

    if ($clienteSel) {
        $h = $pdo->prepare(
            "SELECT a.data_hora, a.preco, a.status,
                    bar.nome AS barbeiro_nome,
                    COALESCE(srv.nome, cb.nome) AS item_nome
             FROM agendamentos a
             JOIN usuarios bar ON bar.id = a.barbeiro_id
             LEFT JOIN servicos srv ON srv.id = a.servico_id
             LEFT JOIN combos cb   ON cb.id  = a.combo_id
             WHERE a.cliente_id = ?
             ORDER BY a.data_hora DESC
             LIMIT 30"
        );
        $h->execute([$detalhe]);
        $historicoSel = $h->fetchAll();
    }
}

$statusLabel = [
    'pendente'       => ['Pendente',      'badge--warn'],
    'confirmado'     => ['Confirmado',    'badge--ok'],
    'cancelado'      => ['Cancelado',     'badge--err'],
    'nao_confirmado' => ['Não confirmado','badge--muted'],
];

$titulo = 'Clientes';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div><h1>Clientes</h1><p class="sub">Gerencie a carteira de clientes</p></div>
  </div>

  <!-- busca -->
  <form method="get" class="filter-bar">
    <div class="field" style="flex:1; min-width:200px;">
      <label>Buscar por nome ou e-mail</label>
      <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Digite…">
    </div>
    <button class="btn btn--sm" type="submit">Buscar</button>
    <?php if ($busca): ?><a class="btn btn--ghost btn--sm" href="/admin/clientes.php">Limpar</a><?php endif; ?>
  </form>

  <div class="panel-cols <?= $clienteSel ? '' : '' ?>" style="grid-template-columns:<?= $clienteSel ? '1fr 1fr' : '1fr' ?>">

    <!-- lista -->
    <div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Nome</th><th>Cadastro</th><th>Agend.</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
              <td>
                <strong><?= e($c['nome']) ?></strong><br>
                <span style="font-size:12px; color:var(--muted)"><?= e($c['email']) ?></span>
              </td>
              <td style="font-size:12px; color:var(--muted); white-space:nowrap">
                <?= (new DateTimeImmutable($c['criado_em']))->format('d/m/Y') ?>
              </td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$c['total_ag'] ?></td>
              <td>
                <span class="badge <?= $c['ativo'] ? 'badge--ok' : 'badge--muted' ?>">
                  <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td style="white-space:nowrap; font-size:12px;">
                <a href="?ver=<?= (int)$c['id'] ?><?= $busca ? '&q='.urlencode($busca) : '' ?>"
                   style="color:var(--accent);">Ver</a>
                &nbsp;
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="acao" value="<?= $c['ativo'] ? 'desativar' : 'reativar' ?>">
                  <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;color:var(--muted);font-size:12px;">
                    <?= $c['ativo'] ? 'Desativar' : 'Reativar' ?>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?>
            <tr><td colspan="5" style="color:var(--muted); text-align:center; padding:24px;">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- detalhe -->
    <?php if ($clienteSel): ?>
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem">Detalhe</h2>
        <span class="section-head-line"></span>
        <a href="/admin/clientes.php<?= $busca ? '?q='.urlencode($busca) : '' ?>" style="font-size:12px; color:var(--muted)">Fechar</a>
      </div>

      <div class="card card--accent" style="margin-bottom:16px;">
        <p style="font-size:15px; font-weight:600; margin-bottom:4px;"><?= e($clienteSel['nome']) ?></p>
        <p style="font-size:13px; color:var(--muted)"><?= e($clienteSel['email']) ?></p>
        <p style="font-size:12px; color:var(--muted); margin-top:6px;">
          Cadastrado em <?= (new DateTimeImmutable($clienteSel['criado_em']))->format('d/m/Y') ?>
        </p>
      </div>

      <div class="inline-actions">
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= (int)$clienteSel['id'] ?>">
          <input type="hidden" name="acao" value="reset_senha">
          <button class="btn btn--ghost btn--sm" type="submit">Forçar troca de senha</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Desativar este cliente?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= (int)$clienteSel['id'] ?>">
          <input type="hidden" name="acao" value="<?= $clienteSel['ativo'] ? 'desativar' : 'reativar' ?>">
          <button class="btn btn--sm <?= $clienteSel['ativo'] ? 'btn--danger' : '' ?>" type="submit">
            <?= $clienteSel['ativo'] ? 'Desativar conta' : 'Reativar conta' ?>
          </button>
        </form>
      </div>

      <?php if ($historicoSel): ?>
      <p style="font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--muted); margin-bottom:10px;">
        Histórico de agendamentos
      </p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Data</th><th>Serviço</th><th>Valor</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($historicoSel as $ag):
              $dt = new DateTimeImmutable($ag['data_hora']);
              [$lbl, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
            ?>
            <tr>
              <td style="white-space:nowrap; font-size:13px;">
                <?= $dt->format('d/m/Y') ?><br>
                <span style="color:var(--muted)"><?= $dt->format('H:i') ?></span>
              </td>
              <td style="font-size:13px;">
                <?= e($ag['item_nome']) ?><br>
                <span style="font-size:11px; color:var(--muted)"><?= e($ag['barbeiro_nome']) ?></span>
              </td>
              <td style="color:var(--accent); font-size:13px;">R$ <?= number_format((float)$ag['preco'],2,',','.') ?></td>
              <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="muted" style="font-size:13px;">Nenhum agendamento registrado.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
