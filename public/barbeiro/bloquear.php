<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    // criar bloqueio
    if ($acao === 'criar') {
        $data  = $_POST['data']        ?? '';
        $ini   = $_POST['hora_inicio'] ?? '';
        $fim   = $_POST['hora_fim']    ?? '';

        $hoje = new DateTimeImmutable('today');
        $dia  = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

        if (!$dia || $dia < $hoje) {
            flash('err', 'Data inválida.');
            header('Location: /barbeiro/bloquear.php'); exit;
        }
        if (!$ini || !$fim || $ini >= $fim) {
            flash('err', 'Horário inválido. Início deve ser anterior ao fim.');
            header('Location: /barbeiro/bloquear.php'); exit;
        }

        // avisa se há agendamentos sobrepostos (não impede, só alerta)
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM agendamentos
             WHERE barbeiro_id=? AND DATE(data_hora)=?
               AND status IN ('pendente','confirmado')
               AND data_hora < TIMESTAMP(?, ?)
               AND TIMESTAMPADD(MINUTE, duracao_min, data_hora) > TIMESTAMP(?, ?)"
        );
        $chk->execute([$usuario['id'], $data, $data, $fim.':00', $data, $ini.':00']);
        $conflitos = (int)$chk->fetchColumn();

        $pdo->prepare(
            "INSERT INTO bloqueios(barbeiro_id, data, hora_inicio, hora_fim) VALUES(?,?,?,?)"
        )->execute([$usuario['id'], $data, $ini, $fim]);

        $msg = 'Bloqueio criado.';
        if ($conflitos > 0) {
            $msg .= " Atenção: há {$conflitos} agendamento(s) neste período — cancele-os manualmente.";
        }
        flash($conflitos > 0 ? 'err' : 'ok', $msg);
        header('Location: /barbeiro/bloquear.php'); exit;
    }

    // remover bloqueio
    if ($acao === 'remover') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare(
            "DELETE FROM bloqueios WHERE id=? AND barbeiro_id=?"
        )->execute([$id, $usuario['id']]);
        flash('ok', 'Bloqueio removido.');
        header('Location: /barbeiro/bloquear.php'); exit;
    }
}

// ── Dados ────────────────────────────────────────────────────
// bloqueios dos próximos 30 dias
$bloqueios = $pdo->prepare(
    "SELECT id, data, hora_inicio, hora_fim
     FROM bloqueios
     WHERE barbeiro_id=? AND data >= CURDATE() AND data <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY data, hora_inicio"
);
$bloqueios->execute([$usuario['id']]);
$bloqueios = $bloqueios->fetchAll();

// datas disponíveis (30 dias)
$datas = [];
for ($i = 0; $i <= 30; $i++) {
    $d = new DateTimeImmutable("today +{$i} days");
    $datas[] = [
        'value' => $d->format('Y-m-d'),
        'label' => $d->format('d/m') . ' (' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)$d->format('w')] . ')',
    ];
}

$titulo = 'Bloquear agenda';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px;">

  <div class="page-head">
    <h1>Bloquear agenda</h1>
    <p class="sub">Indique períodos em que você não estará disponível</p>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:start;">

    <!-- lista de bloqueios -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.3rem">Bloqueios ativos</h2>
        <span class="section-head-line"></span>
      </div>

      <?php if ($bloqueios): ?>
        <div class="agenda-list">
          <?php foreach ($bloqueios as $b):
            $d = new DateTimeImmutable($b['data']);
          ?>
            <div class="agenda-item">
              <div>
                <div class="agenda-time"><?= e(substr($b['hora_inicio'], 0, 5)) ?></div>
                <div class="agenda-date-label"><?= $d->format('d/m') ?></div>
              </div>
              <div>
                <p class="agenda-client" style="color:var(--muted); font-size:13px;">
                  até <?= e(substr($b['hora_fim'], 0, 5)) ?>
                </p>
                <p class="agenda-service"><?= $d->format('d/m/Y') ?></p>
              </div>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="remover">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button type="submit" style="background:none;border:none;color:var(--err);font-size:12px;cursor:pointer;padding:0;">Remover</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card"><p class="muted">Nenhum bloqueio nos próximos 30 dias.</p></div>
      <?php endif; ?>
    </div>

    <!-- formulário novo bloqueio -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.3rem">Novo bloqueio</h2>
        <span class="section-head-line"></span>
      </div>
      <div class="card">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="criar">

          <div class="field">
            <label for="data">Data</label>
            <select name="data" id="data" required>
              <option value="">Selecione…</option>
              <?php foreach ($datas as $d): ?>
                <option value="<?= e($d['value']) ?>"><?= e($d['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="field">
              <label for="hora_inicio">Início</label>
              <input type="time" name="hora_inicio" id="hora_inicio" required>
            </div>
            <div class="field">
              <label for="hora_fim">Fim</label>
              <input type="time" name="hora_fim" id="hora_fim" required>
            </div>
          </div>

          <button class="btn btn--sm" type="submit">Criar bloqueio</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
