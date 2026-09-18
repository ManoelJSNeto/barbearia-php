<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $srv_ids   = array_map('intval', (array)($_POST['servicos'] ?? []));
    $combo_ids = array_map('intval', (array)($_POST['combos']   ?? []));

    // sincroniza servicos
    $pdo->prepare("DELETE FROM barbeiro_servicos WHERE barbeiro_id=?")->execute([$usuario['id']]);
    $ins = $pdo->prepare("INSERT IGNORE INTO barbeiro_servicos(barbeiro_id, servico_id) VALUES(?,?)");
    foreach ($srv_ids as $id) { if ($id > 0) $ins->execute([$usuario['id'], $id]); }

    // sincroniza combos
    $pdo->prepare("DELETE FROM barbeiro_combos WHERE barbeiro_id=?")->execute([$usuario['id']]);
    $ins = $pdo->prepare("INSERT IGNORE INTO barbeiro_combos(barbeiro_id, combo_id) VALUES(?,?)");
    foreach ($combo_ids as $id) { if ($id > 0) $ins->execute([$usuario['id'], $id]); }

    flash('ok', 'Serviços atualizados.');
    header('Location: /barbeiro/servicos.php'); exit;
}

// serviços disponíveis
$todoServicos = $pdo->query(
    'SELECT s.id, s.nome, c.nome AS categoria
     FROM servicos s JOIN categorias c ON c.id = s.categoria_id
     WHERE s.ativo = 1 AND c.ativo = 1
     ORDER BY c.ordem, s.nome'
)->fetchAll();

$todoCombos = $pdo->query(
    'SELECT id, nome FROM combos WHERE ativo=1 ORDER BY nome'
)->fetchAll();

// o que o barbeiro já oferece
$meusSrvIds = $pdo->prepare("SELECT servico_id FROM barbeiro_servicos WHERE barbeiro_id=?");
$meusSrvIds->execute([$usuario['id']]);
$meusSrv = array_column($meusSrvIds->fetchAll(), 'servico_id');

$meusComboIds = $pdo->prepare("SELECT combo_id FROM barbeiro_combos WHERE barbeiro_id=?");
$meusComboIds->execute([$usuario['id']]);
$meusCombos = array_column($meusComboIds->fetchAll(), 'combo_id');

$titulo = 'Meus serviços';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px; max-width:680px;">

  <div class="page-head">
    <h1>Meus serviços</h1>
    <p class="sub">Marque os serviços e combos que você oferece</p>
  </div>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- serviços -->
    <?php
    $catAtual = null;
    foreach ($todoServicos as $s):
      if ($s['categoria'] !== $catAtual):
        if ($catAtual !== null) echo '</div>';
        $catAtual = $s['categoria'];
    ?>
      <p style="font-size:11px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--gold-pale); margin:24px 0 10px;"><?= e($catAtual) ?></p>
      <div style="display:flex; flex-direction:column; gap:1px; background:var(--border); border:1px solid var(--border); margin-bottom:4px;">
    <?php endif; ?>
      <label style="display:flex; align-items:center; gap:14px; background:var(--surface); padding:14px 18px; cursor:pointer; transition:background .12s;"
             onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='var(--surface)'">
        <input type="checkbox" name="servicos[]" value="<?= (int)$s['id'] ?>"
               <?= in_array((int)$s['id'], array_map('intval', $meusSrv)) ? 'checked' : '' ?>>
        <span style="flex:1; font-size:14px; color:var(--text);"><?= e($s['nome']) ?></span>
      </label>
    <?php endforeach; ?>
    <?php if ($catAtual !== null) echo '</div>'; ?>

    <!-- combos -->
    <?php if ($todoCombos): ?>
      <p style="font-size:11px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--gold); margin:24px 0 10px;">Combos</p>
      <div style="display:flex; flex-direction:column; gap:1px; background:var(--border); border:1px solid var(--border); border-left:3px solid var(--gold); margin-bottom:24px;">
        <?php foreach ($todoCombos as $c): ?>
          <label style="display:flex; align-items:center; gap:14px; background:var(--surface); padding:14px 18px; cursor:pointer; transition:background .12s;"
                 onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='var(--surface)'">
            <input type="checkbox" name="combos[]" value="<?= (int)$c['id'] ?>"
                   <?= in_array((int)$c['id'], array_map('intval', $meusCombos)) ? 'checked' : '' ?>>
            <span style="flex:1; font-size:14px; color:var(--text);"><?= e($c['nome']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button class="btn" type="submit">Salvar seleção</button>
  </form>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
