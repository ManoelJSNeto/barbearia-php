<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

exigir_perfil('admin');

$pdo = db();

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao      = $_POST['acao']    ?? '';
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $obs       = trim($_POST['obs_admin'] ?? '');

    if (!$ticket_id) { flash('err', 'Ticket inválido.'); header('Location: /admin/tickets.php'); exit; }

    $ticket = $pdo->prepare(
        "SELECT t.*, u.nome AS barbeiro_nome, u.email AS barbeiro_email
         FROM tickets t JOIN usuarios u ON u.id = t.barbeiro_id WHERE t.id=?"
    );
    $ticket->execute([$ticket_id]);
    $tk = $ticket->fetch();

    if (!$tk) { flash('err', 'Ticket não encontrado.'); header('Location: /admin/tickets.php'); exit; }

    if ($acao === 'aprovar') {
        // insere o serviço e associa ao barbeiro
        $cat_id      = $tk['categoria_id'];
        $preco       = (float)($tk['preco']       ?? 0);
        $duracao_min = (int)($tk['duracao_min']   ?? 30);

        $pdo->prepare(
            "INSERT INTO servicos(categoria_id, nome, descricao, preco, duracao_min)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$cat_id, $tk['nome'], $tk['descricao'], $preco, $duracao_min]);

        $servico_id = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT IGNORE INTO barbeiro_servicos(barbeiro_id, servico_id) VALUES(?,?)"
        )->execute([$tk['barbeiro_id'], $servico_id]);

        $pdo->prepare(
            "UPDATE tickets SET status='aprovado', obs_admin=?, resolvido_em=NOW() WHERE id=?"
        )->execute([$obs, $ticket_id]);

        // e-mail ao barbeiro
        $corpo = "
            <p>Olá, <strong style='color:#1C1814'>" . e($tk['barbeiro_nome']) . "</strong>.</p>
            <p>Sua sugestão de serviço <strong>" . e($tk['nome']) . "</strong> foi <strong style='color:#3A7D5A'>aprovada</strong>.</p>
            <p>O serviço já está disponível no seu painel e pode ser oferecido a clientes.</p>
            " . ($obs ? "<p style='color:#6E6258'>Observação do admin: " . e($obs) . "</p>" : '') . "
        ";
        enviar_email($tk['barbeiro_email'], $tk['barbeiro_nome'], 'Ticket aprovado — ' . e($tk['nome']), $corpo);

        flash('ok', "Serviço \"" . e($tk['nome']) . "\" aprovado e associado ao barbeiro.");
        header('Location: /admin/tickets.php'); exit;
    }

    if ($acao === 'recusar') {
        $pdo->prepare(
            "UPDATE tickets SET status='recusado', obs_admin=?, resolvido_em=NOW() WHERE id=?"
        )->execute([$obs, $ticket_id]);

        $corpo = "
            <p>Olá, <strong style='color:#1C1814'>" . e($tk['barbeiro_nome']) . "</strong>.</p>
            <p>Sua sugestão de serviço <strong>" . e($tk['nome']) . "</strong> foi <strong style='color:#B04040'>recusada</strong>.</p>
            " . ($obs ? "<p style='color:#6E6258'>Motivo: " . e($obs) . "</p>" : '') . "
            <p style='color:#6E6258;font-size:13px;'>Você pode enviar uma nova sugestão com ajustes pelo seu painel.</p>
        ";
        enviar_email($tk['barbeiro_email'], $tk['barbeiro_nome'], 'Ticket recusado — ' . e($tk['nome']), $corpo);

        flash('ok', "Ticket recusado.");
        header('Location: /admin/tickets.php'); exit;
    }
}

// ── Dados ─────────────────────────────────────────────────────
$filtro    = $_GET['status'] ?? 'pendente';
$validos   = ['pendente','aprovado','recusado','todos'];
if (!in_array($filtro, $validos, true)) $filtro = 'pendente';

$sql    = "SELECT t.id, t.nome, t.descricao, t.preco, t.duracao_min, t.status,
                  t.obs_admin, t.criado_em, t.resolvido_em,
                  u.nome AS barbeiro_nome,
                  cat.nome AS categoria_nome
           FROM tickets t
           JOIN usuarios u ON u.id = t.barbeiro_id
           LEFT JOIN categorias cat ON cat.id = t.categoria_id";
$params = [];
if ($filtro !== 'todos') {
    $sql   .= " WHERE t.status = ?";
    $params = [$filtro];
}
$sql .= " ORDER BY t.criado_em DESC LIMIT 60";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$categorias = $pdo->query('SELECT id, nome FROM categorias WHERE ativo=1 ORDER BY ordem')->fetchAll();

$titulo = 'Tickets';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div><h1>Tickets de serviço</h1><p class="sub">Sugestões enviadas pelos barbeiros</p></div>
  </div>

  <!-- filtro de status -->
  <div class="quick-actions" style="margin-top:0; margin-bottom:24px;">
    <?php foreach (['pendente'=>'Pendentes','aprovado'=>'Aprovados','recusado'=>'Recusados','todos'=>'Todos'] as $v => $l): ?>
      <a href="?status=<?= $v ?>"
         class="btn btn--sm <?= $filtro === $v ? '' : 'btn--ghost' ?>"
         style="font-size:12px;">
        <?= $l ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($tickets): ?>
  <div style="display:flex; flex-direction:column; gap:10px;">
    <?php foreach ($tickets as $tk): ?>
    <div class="card <?= $tk['status'] === 'pendente' ? 'card--warn' : ($tk['status'] === 'aprovado' ? 'card--ok' : 'card--err') ?>">
      <div style="display:grid; grid-template-columns:1fr auto; gap:16px; align-items:start;">

        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
            <span style="font-size:15px; font-weight:600; color:var(--text)"><?= e($tk['nome']) ?></span>
            <?php if ($tk['categoria_nome']): ?>
              <span class="badge badge--accent" style="font-size:10px"><?= e($tk['categoria_nome']) ?></span>
            <?php endif; ?>
            <span class="badge <?= $tk['status'] === 'pendente' ? 'badge--warn' : ($tk['status'] === 'aprovado' ? 'badge--ok' : 'badge--err') ?>">
              <?= ucfirst($tk['status']) ?>
            </span>
          </div>
          <p style="font-size:13px; color:var(--muted); margin-bottom:6px;">
            por <strong style="color:var(--text)"><?= e($tk['barbeiro_nome']) ?></strong>
            &nbsp;·&nbsp; <?= (new DateTimeImmutable($tk['criado_em']))->format('d/m/Y') ?>
          </p>
          <?php if ($tk['descricao']): ?>
            <p style="font-size:13px; color:var(--muted); margin-bottom:8px;"><?= e($tk['descricao']) ?></p>
          <?php endif; ?>
          <div style="display:flex; gap:16px; font-size:13px; color:var(--muted);">
            <?php if ($tk['preco']): ?>
              <span>R$ <?= number_format((float)$tk['preco'],2,',','.') ?></span>
            <?php endif; ?>
            <?php if ($tk['duracao_min']): ?>
              <span><?= (int)$tk['duracao_min'] ?> min</span>
            <?php endif; ?>
          </div>
          <?php if ($tk['obs_admin']): ?>
            <p style="font-size:12px; color:var(--muted); margin-top:8px; padding:8px 12px; background:var(--surface-2); border-radius:var(--radius);">
              <strong>Admin:</strong> <?= e($tk['obs_admin']) ?>
            </p>
          <?php endif; ?>
        </div>

        <?php if ($tk['status'] === 'pendente'): ?>
        <div style="min-width:200px;">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="ticket_id" value="<?= (int)$tk['id'] ?>">
            <div class="field" style="margin-bottom:8px;">
              <label>Observação (opcional)</label>
              <textarea name="obs_admin" rows="2" placeholder="Motivo ou observação…"></textarea>
            </div>
            <div style="display:flex; gap:6px;">
              <button class="btn btn--sm" type="submit" name="acao" value="aprovar">Aprovar</button>
              <button class="btn btn--sm btn--danger" type="submit" name="acao" value="recusar">Recusar</button>
            </div>
          </form>
        </div>
        <?php endif; ?>

      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="card">
      <p class="muted">Nenhum ticket <?= $filtro !== 'todos' ? "com status \"$filtro\"" : '' ?>.</p>
    </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
