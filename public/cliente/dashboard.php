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
    'pendente'       => ['Pendente',        'badge--gold'],
    'confirmado'     => ['Confirmado',       'badge--ok'],
    'cancelado'      => ['Cancelado',        'badge--err'],
    'nao_confirmado' => ['Não confirmado',   'badge--muted'],
];

$titulo = 'Meus agendamentos';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top: 40px; padding-bottom: 80px;">

  <div class="page-head">
    <h1>Olá, <?= e($usuario['nome']) ?></h1>
    <p class="sub">Gerencie seus agendamentos</p>
  </div>

  <!-- próximos -->
  <div style="margin-bottom: 48px;">
    <div class="section-head">
      <h2 style="font-size:1.4rem">Próximos agendamentos</h2>
      <span class="section-head-line"></span>
      <a class="btn btn--sm" href="/agendar.php">+ Agendar</a>
    </div>

    <?php if ($proximos): ?>
      <div class="agenda-list">
        <?php foreach ($proximos as $ag):
          $dt = new DateTimeImmutable($ag['data_hora']);
          [$label, $cls] = $statusLabel[$ag['status']] ?? ['—', 'badge--muted'];
        ?>
          <div class="agenda-item">
            <div>
              <div class="agenda-time"><?= $dt->format('H:i') ?></div>
              <div class="agenda-date-label"><?= $dt->format('d/m/Y') ?></div>
            </div>
            <div>
              <p class="agenda-client"><?= e($ag['item_nome']) ?></p>
              <p class="agenda-service">com <?= e($ag['barbeiro_nome']) ?> · R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></p>
            </div>
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px">
              <span class="badge <?= $cls ?>"><?= $label ?></span>
              <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end">
                <?php if ($ag['status'] === 'pendente'): ?>
                  <a class="btn btn--sm" href="/confirmar.php?token=<?= urlencode($ag['token_confirm']) ?>">Confirmar</a>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm btn--danger" href="/cliente/cancelar.php?token=<?= urlencode($ag['token_confirm']) ?>">Cancelar</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card">
        <p class="muted">Nenhum agendamento futuro.</p>
        <p style="margin-top:12px"><a class="btn btn--sm" href="/agendar.php">Agendar agora</a></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- histórico -->
  <?php if ($historico): ?>
  <div>
    <div class="section-head">
      <h2 style="font-size:1.4rem">Histórico</h2>
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
            [$label, $cls] = $statusLabel[$ag['status']] ?? ['—', 'badge--muted'];
          ?>
          <tr>
            <td><?= $dt->format('d/m/Y H:i') ?></td>
            <td><?= e($ag['item_nome']) ?></td>
            <td><?= e($ag['barbeiro_nome']) ?></td>
            <td style="color:var(--gold)">R$ <?= number_format((float)$ag['preco'], 2, ',', '.') ?></td>
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
