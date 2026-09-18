<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('cliente');

$pdo     = db();
$usuario = usuario_logado();

$proximos = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.duracao_min, a.preco, a.status, a.token_confirm,
            u.nome AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios u ON u.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.cliente_id = ? AND a.data_hora >= NOW()
       AND a.status IN ('pendente','confirmado')
     ORDER BY a.data_hora ASC
     LIMIT 10"
);
$proximos->execute([$usuario['id']]);
$proximos = $proximos->fetchAll();

$historico = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.preco, a.status,
            u.nome AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome
     FROM agendamentos a
     JOIN usuarios u ON u.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos c   ON c.id = a.combo_id
     WHERE a.cliente_id = ?
       AND (a.data_hora < NOW() OR a.status IN ('cancelado','nao_confirmado'))
     ORDER BY a.data_hora DESC
     LIMIT 20"
);
$historico->execute([$usuario['id']]);
$historico = $historico->fetchAll();

$statusLabel = [
    'pendente'       => ['Pendente',      'badge--gold'],
    'confirmado'     => ['Confirmado',    'badge--ok'],
    'cancelado'      => ['Cancelado',     'badge--err'],
    'nao_confirmado' => ['Não confirmado','badge--muted'],
];

$titulo = 'Meus agendamentos';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div>
      <h1>Olá, <?= e($usuario['nome']) ?></h1>
      <p class="sub">Seus agendamentos na Navalha</p>
    </div>
    <a class="btn btn--sm" href="/agendar.php">+ Novo agendamento</a>
  </div>

  <!-- ── próximos ─────────────────────────────────────────── -->
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem;">Próximos</h2>
      <span class="section-head-line"></span>
    </div>

    <?php if ($proximos): ?>
      <div style="display:flex; flex-direction:column; gap:1px; background:var(--border); border:1px solid var(--border);">
        <?php foreach ($proximos as $ag):
          $dt = new DateTimeImmutable($ag['data_hora']);
          [$label, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
          $isPendente = $ag['status'] === 'pendente';
        ?>
          <div style="background:var(--surface); padding:16px 18px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">

            <!-- horário -->
            <div style="min-width:56px; flex-shrink:0;">
              <div style="font-family:var(--font-mono); font-size:1.1rem; color:var(--gold);"><?= $dt->format('H:i') ?></div>
              <div style="font-size:11px; color:var(--muted);"><?= $dt->format('d/m/Y') ?></div>
            </div>

            <!-- info -->
            <div style="flex:1; min-width:140px;">
              <div style="font-size:14px; font-weight:500; color:var(--text);"><?= e($ag['item_nome']) ?></div>
              <div style="font-size:12px; color:var(--muted); margin-top:2px;">
                com <?= e($ag['barbeiro_nome']) ?> &nbsp;·&nbsp; R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?>
              </div>
            </div>

            <!-- status + ações -->
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; flex-shrink:0;">
              <span class="badge <?= $cls ?>"><?= $label ?></span>

              <?php if ($isPendente): ?>
                <a class="btn btn--sm" href="/confirmar.php?token=<?= urlencode($ag['token_confirm']) ?>">
                  Confirmar
                </a>
              <?php endif; ?>

              <a class="btn btn--ghost btn--sm"
                 href="/cliente/cancelar.php?token=<?= urlencode($ag['token_confirm']) ?>"
                 style="color:var(--err); border-color:var(--err);">
                Cancelar
              </a>
            </div>

          </div>
        <?php endforeach; ?>
      </div>

      <?php
      $temPendente = count(array_filter($proximos, fn($a) => $a['status'] === 'pendente'));
      if ($temPendente): ?>
        <p style="font-size:12px; color:var(--muted); margin-top:10px; padding:10px 14px; background:var(--surface); border:1px solid var(--border); border-left:3px solid var(--gold-dim);">
          Você tem <?= $temPendente ?> agendamento<?= $temPendente > 1 ? 's' : '' ?> aguardando confirmação.
          Confirme até 2h antes do horário para garantir seu lugar.
        </p>
      <?php endif; ?>

    <?php else: ?>
      <div class="card" style="text-align:center; padding:40px 24px;">
        <p style="color:var(--muted); font-size:15px; margin-bottom:16px;">Nenhum agendamento futuro.</p>
        <a class="btn" href="/agendar.php">Agendar agora</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── histórico ────────────────────────────────────────── -->
  <?php if ($historico): ?>
  <div>
    <div class="section-head">
      <h2 style="font-size:1.3rem;">Histórico</h2>
      <span class="section-head-line"></span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Serviço</th>
            <th>Barbeiro</th>
            <th>Valor</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historico as $ag):
            $dt = new DateTimeImmutable($ag['data_hora']);
            [$label, $cls] = $statusLabel[$ag['status']] ?? ['—','badge--muted'];
          ?>
          <tr>
            <td style="white-space:nowrap; font-family:var(--font-mono); font-size:13px;">
              <?= $dt->format('d/m/Y') ?><br>
              <span style="color:var(--muted)"><?= $dt->format('H:i') ?></span>
            </td>
            <td><?= e($ag['item_nome']) ?></td>
            <td style="color:var(--muted)"><?= e($ag['barbeiro_nome']) ?></td>
            <td style="color:var(--gold); white-space:nowrap;">R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></td>
            <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
