<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_perfil('admin');

$pdo   = db();
$erros = [];

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    // criar/editar categoria
    if ($acao === 'salvar_categoria') {
        $id    = (int)($_POST['cat_id'] ?? 0);
        $nome  = trim($_POST['cat_nome']  ?? '');
        $ordem = (int)($_POST['cat_ordem'] ?? 0);
        if (mb_strlen($nome) < 2) $erros[] = 'Nome da categoria obrigatório.';

        if (!$erros) {
            if ($id) {
                $pdo->prepare("UPDATE categorias SET nome=?, ordem=? WHERE id=?")->execute([$nome, $ordem, $id]);
            } else {
                $pdo->prepare("INSERT INTO categorias(nome, ordem) VALUES(?,?)")->execute([$nome, $ordem]);
            }
            flash('ok', 'Categoria salva.');
            header('Location: /admin/servicos.php'); exit;
        }
    }

    // toggle ativo categoria
    if ($acao === 'toggle_cat') {
        $pdo->prepare("UPDATE categorias SET ativo = NOT ativo WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok', 'Categoria atualizada.');
        header('Location: /admin/servicos.php'); exit;
    }

    // criar/editar serviço
    if ($acao === 'salvar_servico') {
        $id          = (int)($_POST['srv_id']      ?? 0);
        $cat_id      = (int)($_POST['categoria_id'] ?? 0);
        $nome        = trim($_POST['nome']          ?? '');
        $descricao   = trim($_POST['descricao']     ?? '');
        $preco       = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
        $duracao_min = (int)($_POST['duracao_min']  ?? 0);

        if (mb_strlen($nome) < 2)  $erros[] = 'Nome do serviço obrigatório.';
        if ($cat_id < 1)           $erros[] = 'Categoria obrigatória.';
        if ($preco <= 0)           $erros[] = 'Preço inválido.';
        if ($duracao_min < 5)      $erros[] = 'Duração mínima de 5 minutos.';

        if (!$erros) {
            if ($id) {
                $pdo->prepare(
                    "UPDATE servicos SET categoria_id=?, nome=?, descricao=?, preco=?, duracao_min=? WHERE id=?"
                )->execute([$cat_id, $nome, $descricao, $preco, $duracao_min, $id]);
            } else {
                $pdo->prepare(
                    "INSERT INTO servicos(categoria_id, nome, descricao, preco, duracao_min) VALUES(?,?,?,?,?)"
                )->execute([$cat_id, $nome, $descricao, $preco, $duracao_min]);
            }
            flash('ok', 'Serviço salvo.');
            header('Location: /admin/servicos.php'); exit;
        }
    }

    // toggle ativo serviço
    if ($acao === 'toggle_srv') {
        $pdo->prepare("UPDATE servicos SET ativo = NOT ativo WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok', 'Serviço atualizado.');
        header('Location: /admin/servicos.php'); exit;
    }

    // criar/editar combo
    if ($acao === 'salvar_combo') {
        $id          = (int)($_POST['combo_id']    ?? 0);
        $nome        = trim($_POST['combo_nome']   ?? '');
        $descricao   = trim($_POST['combo_desc']   ?? '');
        $preco       = (float)str_replace(',', '.', $_POST['combo_preco'] ?? '0');
        $duracao_min = (int)($_POST['combo_dur']   ?? 0);
        $srv_ids     = array_map('intval', (array)($_POST['combo_servicos'] ?? []));

        if (mb_strlen($nome) < 2) $erros[] = 'Nome do combo obrigatório.';
        if ($preco <= 0)          $erros[] = 'Preço inválido.';
        if ($duracao_min < 5)     $erros[] = 'Duração mínima de 5 minutos.';

        if (!$erros) {
            if ($id) {
                $pdo->prepare(
                    "UPDATE combos SET nome=?, descricao=?, preco=?, duracao_min=? WHERE id=?"
                )->execute([$nome, $descricao, $preco, $duracao_min, $id]);
            } else {
                $pdo->prepare(
                    "INSERT INTO combos(nome, descricao, preco, duracao_min) VALUES(?,?,?,?)"
                )->execute([$nome, $descricao, $preco, $duracao_min]);
                $id = (int)$pdo->lastInsertId();
            }
            // sincronizar serviços do combo
            $pdo->prepare("DELETE FROM combo_servicos WHERE combo_id=?")->execute([$id]);
            $ins = $pdo->prepare("INSERT IGNORE INTO combo_servicos(combo_id, servico_id) VALUES(?,?)");
            foreach ($srv_ids as $sid) { if ($sid > 0) $ins->execute([$id, $sid]); }

            flash('ok', 'Combo salvo.');
            header('Location: /admin/servicos.php'); exit;
        }
    }

    // toggle ativo combo
    if ($acao === 'toggle_combo') {
        $pdo->prepare("UPDATE combos SET ativo = NOT ativo WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok', 'Combo atualizado.');
        header('Location: /admin/servicos.php'); exit;
    }
}

// ── Dados ────────────────────────────────────────────────────
$categorias = $pdo->query(
    'SELECT id, nome, ordem, ativo FROM categorias ORDER BY ordem, nome'
)->fetchAll();

$servicos = $pdo->query(
    'SELECT s.id, s.nome, s.descricao, s.preco, s.duracao_min, s.ativo, c.nome AS categoria
     FROM servicos s JOIN categorias c ON c.id = s.categoria_id
     ORDER BY c.ordem, s.nome'
)->fetchAll();

$combos = $pdo->query('SELECT * FROM combos ORDER BY nome')->fetchAll();

$todoServicos = $pdo->query('SELECT id, nome FROM servicos WHERE ativo=1 ORDER BY nome')->fetchAll();

$combosServicos = [];
foreach ($pdo->query('SELECT combo_id, servico_id FROM combo_servicos') as $row) {
    $combosServicos[$row['combo_id']][] = $row['servico_id'];
}

$titulo = 'Serviços';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:40px; padding-bottom:80px;">

  <div class="page-head">
    <h1>Serviços &amp; Combos</h1>
    <p class="sub">Gerencie o catálogo</p>
  </div>

  <?php foreach ($erros as $e): ?>
    <div class="flash flash--err"><?= e($e) ?></div>
  <?php endforeach; ?>

  <!-- ── CATEGORIAS ─────────────────────────────────────────── -->
  <div style="margin-bottom:48px;">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Categorias</h2>
      <span class="section-head-line"></span>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:32px; align-items:start;">

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Nome</th><th>Ordem</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($categorias as $cat): ?>
            <tr>
              <td><?= e($cat['nome']) ?></td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$cat['ordem'] ?></td>
              <td><span class="badge <?= $cat['ativo'] ? 'badge--ok' : 'badge--muted' ?>"><?= $cat['ativo'] ? 'Ativa' : 'Inativa' ?></span></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="toggle_cat">
                  <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                  <button type="submit" style="background:none;border:none;color:var(--muted);font-size:12px;cursor:pointer;padding:0"><?= $cat['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <p style="font-size:12px; font-weight:500; letter-spacing:.07em; text-transform:uppercase; color:var(--gold-pale); margin-bottom:14px">Nova categoria</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar_categoria">
          <div class="field"><label>Nome</label><input type="text" name="cat_nome" required maxlength="60"></div>
          <div class="field"><label>Ordem</label><input type="number" name="cat_ordem" value="0" min="0" max="99"></div>
          <button class="btn btn--sm" type="submit">Salvar</button>
        </form>
      </div>

    </div>
  </div>

  <!-- ── SERVIÇOS ───────────────────────────────────────────── -->
  <div style="margin-bottom:48px;">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Serviços</h2>
      <span class="section-head-line"></span>
    </div>
    <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:32px; align-items:start;">

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Nome</th><th>Categoria</th><th>Preço</th><th>Duração</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($servicos as $s): ?>
            <tr>
              <td><strong><?= e($s['nome']) ?></strong><br><span style="font-size:12px;color:var(--muted)"><?= e(mb_strimwidth($s['descricao'] ?? '', 0, 50, '…')) ?></span></td>
              <td style="font-size:13px; color:var(--muted)"><?= e($s['categoria']) ?></td>
              <td style="color:var(--gold)">R$ <?= number_format((float)$s['preco'], 2, ',', '.') ?></td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$s['duracao_min'] ?> min</td>
              <td><span class="badge <?= $s['ativo'] ? 'badge--ok' : 'badge--muted' ?>"><?= $s['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="toggle_srv">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit" style="background:none;border:none;color:var(--muted);font-size:12px;cursor:pointer;padding:0"><?= $s['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <p style="font-size:12px; font-weight:500; letter-spacing:.07em; text-transform:uppercase; color:var(--gold-pale); margin-bottom:14px">Novo serviço</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar_servico">
          <div class="field">
            <label>Categoria</label>
            <select name="categoria_id" required>
              <option value="">Selecione…</option>
              <?php foreach ($categorias as $cat): ?>
                <?php if (!$cat['ativo']) continue; ?>
                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Nome</label><input type="text" name="nome" required maxlength="100"></div>
          <div class="field"><label>Descrição</label><textarea name="descricao" rows="2"></textarea></div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="field"><label>Preço (R$)</label><input type="text" name="preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="duracao_min" required min="5" max="480"></div>
          </div>
          <button class="btn btn--sm" type="submit">Salvar serviço</button>
        </form>
      </div>

    </div>
  </div>

  <!-- ── COMBOS ─────────────────────────────────────────────── -->
  <div>
    <div class="section-head">
      <h2 style="font-size:1.3rem">Combos</h2>
      <span class="section-head-line"></span>
    </div>
    <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:32px; align-items:start;">

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Nome</th><th>Preço</th><th>Duração</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($combos as $c): ?>
            <tr>
              <td>
                <strong><?= e($c['nome']) ?></strong>
                <?php $sids = $combosServicos[$c['id']] ?? []; ?>
                <?php if ($sids): ?>
                  <br><span style="font-size:11px;color:var(--muted)">
                    <?php
                    $nomes = array_filter(array_map(function($s) use ($sids) {
                        return in_array((int)$s['id'], $sids) ? $s['nome'] : null;
                    }, $todoServicos));
                    echo e(implode(' + ', $nomes));
                    ?>
                  </span>
                <?php endif; ?>
              </td>
              <td style="color:var(--gold)">R$ <?= number_format((float)$c['preco'], 2, ',', '.') ?></td>
              <td style="font-size:13px; color:var(--muted)"><?= (int)$c['duracao_min'] ?> min</td>
              <td><span class="badge <?= $c['ativo'] ? 'badge--ok' : 'badge--muted' ?>"><?= $c['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="toggle_combo">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" style="background:none;border:none;color:var(--muted);font-size:12px;cursor:pointer;padding:0"><?= $c['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <p style="font-size:12px; font-weight:500; letter-spacing:.07em; text-transform:uppercase; color:var(--gold-pale); margin-bottom:14px">Novo combo</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar_combo">
          <div class="field"><label>Nome</label><input type="text" name="combo_nome" required maxlength="100"></div>
          <div class="field"><label>Descrição</label><textarea name="combo_desc" rows="2"></textarea></div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="field"><label>Preço (R$)</label><input type="text" name="combo_preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="combo_dur" required min="5" max="480"></div>
          </div>
          <div class="field">
            <label>Serviços incluídos</label>
            <div style="display:flex; flex-direction:column; gap:6px; max-height:160px; overflow-y:auto; padding:8px; background:var(--bg); border:1px solid var(--border);">
              <?php foreach ($todoServicos as $s): ?>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text); cursor:pointer; text-transform:none; letter-spacing:0;">
                  <input type="checkbox" name="combo_servicos[]" value="<?= (int)$s['id'] ?>">
                  <?= e($s['nome']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="btn btn--sm" type="submit">Salvar combo</button>
        </form>
      </div>

    </div>
  </div>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
