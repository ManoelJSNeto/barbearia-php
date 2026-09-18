<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/slots.php';

exigir_login();
exigir_perfil('cliente');

$pdo     = db();
$usuario = usuario_logado();

// rota: ?barbeiro=ID inicia pelo barbeiro
$initBarbeiro = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;

// reiniciar agendamento
if (isset($_GET['reiniciar'])) {
    unset($_SESSION['booking']);
    header('Location: /agendar.php' . ($initBarbeiro ? "?barbeiro={$initBarbeiro}" : ''));
    exit;
}

// inicializar sessão de booking
if (!isset($_SESSION['booking']) || isset($_GET['barbeiro'])) {
    $_SESSION['booking'] = [
        'step'        => 1,
        'tipo'        => null,   // 'servico' | 'combo'
        'item_id'     => null,
        'duracao_min' => null,
        'preco'       => null,
        'barbeiro_id' => $initBarbeiro ?: null,
        'data'        => null,
        'hora'        => null,
    ];
    // se entrou via ?barbeiro, pula para step 2 (escolher serviço)
    if ($initBarbeiro) {
        $_SESSION['booking']['step'] = 1; // ainda escolhe serviço, mas barbeiro já fixado
    }
}

$bk = &$_SESSION['booking'];

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $acao = $_POST['acao'] ?? '';

    // STEP 1 → escolher serviço/combo
    if ($acao === 'escolher_servico') {
        $tipo    = $_POST['tipo']    ?? '';
        $item_id = (int)($_POST['item_id'] ?? 0);

        if (!in_array($tipo, ['servico', 'combo'], true) || $item_id < 1) {
            flash('err', 'Selecione um serviço ou combo.');
            header('Location: /agendar.php'); exit;
        }

        if ($tipo === 'servico') {
            $row = $pdo->prepare('SELECT id, nome, preco, duracao_min FROM servicos WHERE id=? AND ativo=1');
            $row->execute([$item_id]);
        } else {
            $row = $pdo->prepare('SELECT id, nome, preco, duracao_min FROM combos WHERE id=? AND ativo=1');
            $row->execute([$item_id]);
        }
        $item = $row->fetch();

        if (!$item) {
            flash('err', 'Serviço não encontrado.');
            header('Location: /agendar.php'); exit;
        }

        $bk['tipo']        = $tipo;
        $bk['item_id']     = $item['id'];
        $bk['duracao_min'] = (int)$item['duracao_min'];
        $bk['preco']       = (float)$item['preco'];
        $bk['nome_item']   = $item['nome'];
        $bk['step']        = 2;
        header('Location: /agendar.php'); exit;
    }

    // STEP 2 → escolher barbeiro + data
    if ($acao === 'escolher_barbeiro_data') {
        $barbeiro_id = (int)($_POST['barbeiro_id'] ?? 0);
        $data        = $_POST['data'] ?? '';

        if ($barbeiro_id < 1) {
            flash('err', 'Selecione um barbeiro.');
            header('Location: /agendar.php'); exit;
        }

        // valida data
        $hoje = new DateTimeImmutable('today');
        $dia  = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$dia || $dia < $hoje || $dia > $hoje->modify('+4 days')) {
            flash('err', 'Data inválida. Escolha entre hoje e os próximos 4 dias.');
            header('Location: /agendar.php'); exit;
        }

        // verifica se barbeiro existe e oferece o serviço
        if ($bk['tipo'] === 'servico') {
            $chk = $pdo->prepare(
                "SELECT 1 FROM usuarios u
                 JOIN barbeiro_servicos bs ON bs.barbeiro_id = u.id AND bs.servico_id = ?
                 WHERE u.id = ? AND u.perfil='barbeiro' AND u.ativo=1"
            );
            $chk->execute([$bk['item_id'], $barbeiro_id]);
        } else {
            $chk = $pdo->prepare(
                "SELECT 1 FROM usuarios u
                 JOIN barbeiro_combos bc ON bc.barbeiro_id = u.id AND bc.combo_id = ?
                 WHERE u.id = ? AND u.perfil='barbeiro' AND u.ativo=1"
            );
            $chk->execute([$bk['item_id'], $barbeiro_id]);
        }

        if (!$chk->fetch()) {
            flash('err', 'Este barbeiro não oferece o serviço selecionado.');
            header('Location: /agendar.php'); exit;
        }

        $bk['barbeiro_id'] = $barbeiro_id;
        $bk['data']        = $data;
        $bk['step']        = 3;
        header('Location: /agendar.php'); exit;
    }

    // STEP 3 → escolher horário e confirmar
    if ($acao === 'confirmar') {
        $hora           = $_POST['hora']    ?? '';
        $confirmarJa    = isset($_POST['confirmar_ja']); // checkbox na página

        // valida slot novamente (evita race condition)
        $slots = getSlots((int)$bk['barbeiro_id'], (string)$bk['data'], (int)$bk['duracao_min']);

        if (!in_array($hora, $slots, true)) {
            flash('err', 'Este horário não está mais disponível. Escolha outro.');
            $bk['step'] = 3;
            header('Location: /agendar.php'); exit;
        }

        $dataHora     = $bk['data'] . ' ' . $hora . ':00';
        $tokenConfirm = bin2hex(random_bytes(32));
        $statusInicial = $confirmarJa ? 'confirmado' : 'pendente';

        try {
            $pdo->beginTransaction();

            // revalida disponibilidade dentro da transação (anti race-condition)
            $lock = $pdo->prepare(
                "SELECT COUNT(*) FROM agendamentos
                 WHERE barbeiro_id=? AND data_hora=? AND status IN ('pendente','confirmado')"
            );
            $lock->execute([$bk['barbeiro_id'], $dataHora]);
            if ((int)$lock->fetchColumn() > 0) {
                $pdo->rollBack();
                flash('err', 'Este horário foi reservado agora mesmo por outra pessoa. Escolha outro.');
                $bk['step'] = 3;
                header('Location: /agendar.php'); exit;
            }

            $pdo->prepare(
                "INSERT INTO agendamentos
                 (cliente_id, barbeiro_id, servico_id, combo_id, data_hora,
                  duracao_min, preco, status, token_confirm, confirmado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))"
            )->execute([
                $usuario['id'],
                $bk['barbeiro_id'],
                $bk['tipo'] === 'servico' ? $bk['item_id'] : null,
                $bk['tipo'] === 'combo'   ? $bk['item_id'] : null,
                $dataHora,
                $bk['duracao_min'],
                $bk['preco'],
                $statusInicial,
                $tokenConfirm,
                $confirmarJa ? 1 : 0,
            ]);

            $agendamentoId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('err', 'Erro ao criar agendamento. Tente novamente.');
            header('Location: /agendar.php'); exit;
        }

        unset($_SESSION['booking']);

        // envia e-mail
        require_once __DIR__ . '/../includes/mail.php';
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
        $agEmail->execute([$agendamentoId]);
        $agDados = $agEmail->fetch();
        if ($agDados) {
            if ($confirmarJa) {
                mail_confirmacao($agDados);
            } else {
                mail_agendamento_criado($agDados, $tokenConfirm);
            }
        }

        // redireciona para resumo com token
        header("Location: /confirmar.php?token={$tokenConfirm}");
        exit;
    }

    // voltar um step
    if ($acao === 'voltar') {
        $bk['step'] = max(1, ($bk['step'] ?? 1) - 1);
        header('Location: /agendar.php'); exit;
    }
}

// ── Dados para a view ────────────────────────────────────────
$step = (int)($bk['step'] ?? 1);

// step 1: serviços e combos
$servicos = $pdo->query(
    'SELECT s.id, s.nome, s.descricao, s.preco, s.duracao_min, c.nome AS categoria
     FROM servicos s
     JOIN categorias c ON c.id = s.categoria_id
     WHERE s.ativo=1 AND c.ativo=1
     ORDER BY c.ordem, s.nome'
)->fetchAll();

$combos = $pdo->query(
    'SELECT id, nome, descricao, preco, duracao_min FROM combos WHERE ativo=1 ORDER BY nome'
)->fetchAll();

// step 2: barbeiros que oferecem o item selecionado
$barbeiros = [];
if ($step >= 2 && $bk['item_id']) {
    if ($bk['tipo'] === 'servico') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.nome FROM usuarios u
             JOIN barbeiro_servicos bs ON bs.barbeiro_id = u.id AND bs.servico_id = ?
             WHERE u.perfil='barbeiro' AND u.ativo=1
             ORDER BY u.nome"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.nome FROM usuarios u
             JOIN barbeiro_combos bc ON bc.barbeiro_id = u.id AND bc.combo_id = ?
             WHERE u.perfil='barbeiro' AND u.ativo=1
             ORDER BY u.nome"
        );
    }
    $stmt->execute([$bk['item_id']]);
    $barbeiros = $stmt->fetchAll();
}

// step 3: slots disponíveis
$slots = [];
$barbeiroNome = '';
if ($step === 3 && $bk['barbeiro_id'] && $bk['data']) {
    $slots = getSlots((int)$bk['barbeiro_id'], (string)$bk['data'], (int)$bk['duracao_min']);
    $stmt  = $pdo->prepare('SELECT nome FROM usuarios WHERE id=?');
    $stmt->execute([$bk['barbeiro_id']]);
    $barbeiroNome = $stmt->fetchColumn() ?: '';
}

// datas disponíveis (próximos 5 dias incluindo hoje)
$datas = [];
for ($i = 0; $i <= 4; $i++) {
    $d = new DateTimeImmutable("today +{$i} days");
    $datas[] = [
        'value'  => $d->format('Y-m-d'),
        'label'  => $d->format('d/m') . ' (' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)$d->format('w')] . ')',
    ];
}

$titulo = 'Agendar horário';
require __DIR__ . '/../includes/header.php';
?>

<div style="padding-top: 40px; padding-bottom: 80px;">

  <!-- steps indicator -->
  <div class="booking-steps" style="margin-bottom: 36px;">
    <div class="booking-step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">
      <span class="sn"><?= $step > 1 ? '✓' : '1' ?></span>
      Serviço
    </div>
    <div class="booking-step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">
      <span class="sn"><?= $step > 2 ? '✓' : '2' ?></span>
      Barbeiro &amp; Data
    </div>
    <div class="booking-step <?= $step === 3 ? 'active' : '' ?>">
      <span class="sn">3</span>
      Horário
    </div>
  </div>

  <?php if ($step === 1): ?>
  <!-- ── STEP 1: escolher serviço ─────────────────────────── -->
  <p class="eyebrow">Passo 1</p>
  <h2 style="margin-bottom: 28px;">Qual serviço você quer?</h2>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="escolher_servico">
    <input type="hidden" name="tipo" id="tipo_input" value="">
    <input type="hidden" name="item_id" id="item_id_input" value="">

    <div class="services-grid" style="margin-bottom: 24px;">
      <?php foreach ($servicos as $s): ?>
        <div class="service-card" style="cursor:pointer" onclick="selecionarItem('servico', <?= (int)$s['id'] ?>, this)">
          <p class="service-cat"><?= e($s['categoria']) ?></p>
          <p class="service-name"><?= e($s['nome']) ?></p>
          <?php if ($s['descricao']): ?><p class="service-desc"><?= e($s['descricao']) ?></p><?php endif; ?>
          <div class="service-meta">
            <span class="service-price">R$ <?= number_format((float)$s['preco'], 2, ',', '.') ?></span>
            <span class="service-time"><?= (int)$s['duracao_min'] ?> min</span>
          </div>
        </div>
      <?php endforeach; ?>

      <?php foreach ($combos as $c): ?>
        <div class="service-card service-card--combo" style="cursor:pointer" onclick="selecionarItem('combo', <?= (int)$c['id'] ?>, this)">
          <p class="service-cat">Combo</p>
          <p class="service-name"><?= e($c['nome']) ?></p>
          <?php if ($c['descricao']): ?><p class="service-desc"><?= e($c['descricao']) ?></p><?php endif; ?>
          <div class="service-meta">
            <span class="service-price">R$ <?= number_format((float)$c['preco'], 2, ',', '.') ?></span>
            <span class="service-time"><?= (int)$c['duracao_min'] ?> min</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button class="btn" type="submit" id="btn-proxima" disabled>Próximo passo</button>
  </form>

  <style>
    .service-card.selecionado {
      outline: 2px solid var(--accent);
      outline-offset: -2px;
      background: var(--accent-light);
    }
  </style>
  <script>
    function selecionarItem(tipo, id, el) {
      document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selecionado'));
      el.classList.add('selecionado');
      document.getElementById('tipo_input').value   = tipo;
      document.getElementById('item_id_input').value = id;
      document.getElementById('btn-proxima').disabled = false;
    }
  </script>

  <?php elseif ($step === 2): ?>
  <!-- ── STEP 2: barbeiro + data ───────────────────────────── -->
  <p class="eyebrow">Passo 2</p>
  <h2 style="margin-bottom: 6px;">Barbeiro e data</h2>
  <p class="muted" style="margin-bottom: 28px;">
    Serviço escolhido: <strong style="color:var(--text)"><?= e($bk['nome_item'] ?? '') ?></strong>
    — R$ <?= number_format((float)($bk['preco'] ?? 0), 2, ',', '.') ?>
    · <?= (int)($bk['duracao_min'] ?? 0) ?> min
  </p>

  <?php if (!$barbeiros): ?>
    <div class="card" style="border-left: 3px solid var(--err);">
      <p>Nenhum barbeiro disponível para este serviço no momento.</p>
      <p style="margin-top: 12px;"><a class="btn btn--ghost btn--sm" href="/agendar.php?reiniciar=1">Escolher outro serviço</a></p>
    </div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="escolher_barbeiro_data">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
      <div class="field">
        <label for="barbeiro_id">Barbeiro</label>
        <select name="barbeiro_id" id="barbeiro_id" required>
          <option value="">Selecione…</option>
          <?php foreach ($barbeiros as $b): ?>
            <option value="<?= (int)$b['id'] ?>"
              <?= $bk['barbeiro_id'] == $b['id'] ? 'selected' : '' ?>>
              <?= e($b['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="data">Data</label>
        <select name="data" id="data" required>
          <option value="">Selecione…</option>
          <?php foreach ($datas as $d): ?>
            <option value="<?= e($d['value']) ?>"
              <?= ($bk['data'] ?? '') === $d['value'] ? 'selected' : '' ?>>
              <?= e($d['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">Ver horários disponíveis</button>
      <button class="btn btn--ghost" type="submit" formaction="/agendar.php" name="acao" value="voltar">Voltar</button>
    </div>
  </form>
  <?php endif; ?>

  <?php elseif ($step === 3): ?>
  <!-- ── STEP 3: horário ───────────────────────────────────── -->
  <p class="eyebrow">Passo 3</p>
  <h2 style="margin-bottom: 6px;">Escolha o horário</h2>
  <p class="muted" style="margin-bottom: 28px;">
    <?= e($bk['nome_item'] ?? '') ?> com <strong style="color:var(--text)"><?= e($barbeiroNome) ?></strong>
    em <?= date('d/m/Y', strtotime((string)$bk['data'])) ?>
  </p>

  <?php if ($slots): ?>
  <form method="post" id="form-slot">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="confirmar">
    <input type="hidden" name="hora" id="hora_input" value="">

    <div class="slots-grid">
      <?php foreach ($slots as $slot): ?>
        <button type="button" class="slot-btn" onclick="escolherSlot('<?= e($slot) ?>', this)">
          <?= e($slot) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- confirmar presença já na hora de criar -->
    <div style="margin-top:24px; padding:16px 18px; background:var(--surface); border:1px solid var(--border);">
      <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer;">
        <input type="checkbox" name="confirmar_ja" id="confirmar_ja" value="1" checked
               style="width:auto; margin-top:2px; flex-shrink:0;">
        <span>
          <strong style="font-size:14px; color:var(--text); font-family:var(--font-body); letter-spacing:0; text-transform:none;">
            Confirmar presença agora
          </strong><br>
          <span style="font-size:12px; color:var(--muted);">
            Marque se você vai comparecer com certeza. Pode também confirmar depois pelo e-mail.
          </span>
        </span>
      </label>
    </div>

    <div class="form-actions" style="margin-top:20px;">
      <button class="btn" type="submit" id="btn-confirmar" disabled>Finalizar agendamento</button>
      <button class="btn btn--ghost" type="submit" formaction="/agendar.php" name="acao" value="voltar">Voltar</button>
    </div>
  </form>

  <script>
    function escolherSlot(hora, el) {
      document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('hora_input').value = hora;
      document.getElementById('btn-confirmar').disabled = false;
    }
  </script>

  <?php else: ?>
  <div class="card card--warn">
    <p style="color:var(--muted);">Nenhum horário disponível para este barbeiro nesta data.</p>
    <p style="margin-top:12px;">
      <a class="btn btn--ghost btn--sm" href="javascript:history.back()">Escolher outra data</a>
    </p>
  </div>
  <form method="post" style="margin-top:14px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <button class="btn btn--ghost btn--sm" type="submit" name="acao" value="voltar">← Voltar</button>
  </form>
  <?php endif; ?>

  <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
