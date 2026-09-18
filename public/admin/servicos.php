<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

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
                $id = (int)$pdo->lastInsertId();
            }

            // upload de foto direta
            if (!empty($_FILES['foto']['tmp_name'])) {
                $res = salvar_imagem($_FILES['foto'], 'servicos', 1200);
                if ($res['ok']) {
                    $pdo->prepare(
                        "INSERT INTO servico_fotos(servico_id, foto_path, ordem) VALUES(?,?,0)"
                    )->execute([$id, $res['path']]);
                } else {
                    $erros[] = 'Serviço salvo, mas foto falhou: ' . $res['erro'];
                }
            }

            // vincular foto do portfólio como foto do serviço
            $portfolio_path = trim($_POST['portfolio_foto'] ?? '');
            if ($portfolio_path !== '') {
                // verifica que o path existe na tabela portfolio (segurança)
                $chk = $pdo->prepare("SELECT id FROM portfolio WHERE foto_path=? LIMIT 1");
                $chk->execute([$portfolio_path]);
                if ($chk->fetch()) {
                    $pdo->prepare(
                        "INSERT INTO servico_fotos(servico_id, foto_path, ordem) VALUES(?,?,0)"
                    )->execute([$id, $portfolio_path]);
                }
            }

            if (!$erros) {
                flash('ok', 'Serviço salvo.');
                header('Location: /admin/servicos.php'); exit;
            }
        }
    }

    // remover foto de serviço
    if ($acao === 'remover_foto_srv') {
        $foto_id  = (int)($_POST['foto_id']  ?? 0);
        $srv_id   = (int)($_POST['srv_id']   ?? 0);
        $stmt = $pdo->prepare("SELECT foto_path FROM servico_fotos WHERE id=? AND servico_id=?");
        $stmt->execute([$foto_id, $srv_id]);
        $foto = $stmt->fetch();
        if ($foto) {
            remover_upload($foto['foto_path']);
            $pdo->prepare("DELETE FROM servico_fotos WHERE id=?")->execute([$foto_id]);
            flash('ok', 'Foto removida.');
        }
        header('Location: /admin/servicos.php'); exit;
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

// fotos de cada serviço
$fotosServicos = [];
foreach ($pdo->query('SELECT id, servico_id, foto_path FROM servico_fotos ORDER BY ordem') as $f) {
    $fotosServicos[$f['servico_id']][] = $f;
}

$combos = $pdo->query('SELECT * FROM combos ORDER BY nome')->fetchAll();

$todoServicos = $pdo->query('SELECT id, nome FROM servicos WHERE ativo=1 ORDER BY nome')->fetchAll();

$combosServicos = [];
foreach ($pdo->query('SELECT combo_id, servico_id FROM combo_servicos') as $row) {
    $combosServicos[$row['combo_id']][] = $row['servico_id'];
}

// fotos de portfólio de todos os barbeiros (para o seletor)
$portFotos = $pdo->query(
    "SELECT p.id, p.foto_path, p.legenda, p.servico_id,
            u.nome AS barbeiro_nome,
            s.nome AS servico_nome
     FROM portfolio p
     JOIN usuarios u ON u.id = p.barbeiro_id
     LEFT JOIN servicos s ON s.id = p.servico_id
     ORDER BY u.nome, p.criado_em DESC"
)->fetchAll();

// agrupa por barbeiro para o seletor
$portPorBarbeiro = [];
foreach ($portFotos as $f) {
    $portPorBarbeiro[$f['barbeiro_nome']][] = $f;
}

$titulo = 'Serviços';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <h1>Serviços &amp; Combos</h1>
    <p class="sub">Gerencie o catálogo</p>
  </div>

  <?php foreach ($erros as $e): ?>
    <div class="flash flash--err"><?= e($e) ?></div>
  <?php endforeach; ?>

  <!-- ── CATEGORIAS ─────────────────────────────────────────── -->
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Categorias</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols">

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

      <div class="panel-form">
        <p class="panel-form-title">Nova categoria</p>
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
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Serviços</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols panel-cols--wide">

      <!-- lista -->
      <div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Nome</th><th>Categoria</th><th>Preço</th><th>Duração</th><th>Fotos</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($servicos as $s): ?>
              <tr>
                <td>
                  <strong><?= e($s['nome']) ?></strong><br>
                  <span style="font-size:12px;color:var(--muted)"><?= e(mb_strimwidth($s['descricao'] ?? '', 0, 50, '…')) ?></span>
                </td>
                <td style="font-size:13px; color:var(--muted)"><?= e($s['categoria']) ?></td>
                <td style="color:var(--accent)">R$ <?= number_format((float)$s['preco'], 2, ',', '.') ?></td>
                <td style="font-size:13px; color:var(--muted)"><?= (int)$s['duracao_min'] ?> min</td>
                <td>
                  <?php $fSrv = $fotosServicos[$s['id']] ?? []; ?>
                  <?php if ($fSrv): ?>
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                      <?php foreach ($fSrv as $foto): ?>
                        <div style="position:relative; display:inline-block;">
                          <img src="/uploads/<?= e($foto['foto_path']) ?>"
                               alt="Foto do serviço"
                               style="width:36px; height:36px; object-fit:cover; border-radius:3px; border:1px solid var(--border);">
                          <form method="post" style="display:inline" onsubmit="return confirm('Remover foto?')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="acao" value="remover_foto_srv">
                            <input type="hidden" name="foto_id" value="<?= (int)$foto['id'] ?>">
                            <input type="hidden" name="srv_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" title="Remover"
                                    style="position:absolute;top:-4px;right:-4px;background:var(--err);color:#fff;border:none;border-radius:50%;width:14px;height:14px;font-size:9px;cursor:pointer;padding:0;line-height:14px;text-align:center;">✕</button>
                          </form>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span style="font-size:12px; color:var(--faint);">—</span>
                  <?php endif; ?>
                </td>
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
      </div>

      <!-- formulário -->
      <div class="panel-form">
        <p class="panel-form-title">Novo serviço</p>
        <form method="post" enctype="multipart/form-data">
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
          <div class="form-grid-2">
            <div class="field"><label>Preço (R$)</label><input type="text" name="preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="duracao_min" required min="5" max="480"></div>
          </div>

          <!-- upload direto -->
          <div class="field">
            <label>Foto do serviço (opcional)</label>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                   style="padding:6px; cursor:pointer;">
            <span class="form-hint">JPG, PNG ou WEBP — máx. 5 MB</span>
          </div>

          <!-- ou selecionar do portfólio -->
          <?php if ($portFotos): ?>
          <div class="field">
            <label>Ou usar foto do portfólio dos barbeiros</label>
            <div id="portfolio-picker" style="display:none; margin-top:8px;">
              <input type="hidden" name="portfolio_foto" id="portfolio_foto_input" value="">
              <div style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius); padding:10px; background:var(--bg);">
                <?php foreach ($portPorBarbeiro as $bNome => $fotos): ?>
                  <p style="font-size:11px; font-weight:600; color:var(--muted); letter-spacing:.06em; text-transform:uppercase; margin:8px 0 6px;"><?= e($bNome) ?></p>
                  <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(70px,1fr)); gap:6px; margin-bottom:8px;">
                    <?php foreach ($fotos as $pf): ?>
                      <div class="port-pick-item" data-path="<?= e($pf['foto_path']) ?>"
                           style="cursor:pointer; border:2px solid transparent; border-radius:3px; overflow:hidden; position:relative;"
                           onclick="selecionarPortFoto(this)"
                           title="<?= e($pf['legenda'] ?: $pf['servico_nome'] ?: '') ?>">
                        <img src="/uploads/<?= e($pf['foto_path']) ?>"
                             alt="<?= e($pf['legenda'] ?: 'Foto') ?>"
                             style="width:100%; height:70px; object-fit:cover; display:block;">
                        <?php if ($pf['legenda']): ?>
                          <div style="font-size:10px; color:var(--muted); padding:2px 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= e($pf['legenda']) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <p id="portfolio-selecionado" style="font-size:12px; color:var(--ok); margin-top:6px; display:none;">✓ Foto selecionada</p>
            </div>
            <button type="button" class="btn btn--ghost btn--xs" style="margin-top:6px;"
                    onclick="togglePicker()">Escolher do portfólio</button>
          </div>
          <?php endif; ?>

          <button class="btn btn--sm" type="submit">Salvar serviço</button>
        </form>

        <script>
        function togglePicker() {
          const el = document.getElementById('portfolio-picker');
          el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
        function selecionarPortFoto(el) {
          document.querySelectorAll('.port-pick-item').forEach(i => {
            i.style.borderColor = 'transparent';
          });
          el.style.borderColor = 'var(--accent)';
          document.getElementById('portfolio_foto_input').value = el.dataset.path;
          document.getElementById('portfolio-selecionado').style.display = 'block';
        }
        </script>
      </div>

    </div>
  </div>

  <!-- ── COMBOS ─────────────────────────────────────────────── -->
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Combos</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols panel-cols--wide">

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
              <td style="color:var(--accent)">R$ <?= number_format((float)$c['preco'], 2, ',', '.') ?></td>
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

      <div class="panel-form">
        <p class="panel-form-title">Novo combo</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar_combo">
          <div class="field"><label>Nome</label><input type="text" name="combo_nome" required maxlength="100"></div>
          <div class="field"><label>Descrição</label><textarea name="combo_desc" rows="2"></textarea></div>
          <div class="form-grid-2">
            <div class="field"><label>Preço (R$)</label><input type="text" name="combo_preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="combo_dur" required min="5" max="480"></div>
          </div>
          <div class="field">
            <label>Serviços incluídos</label>
            <div style="display:flex; flex-direction:column; gap:6px; max-height:160px; overflow-y:auto; padding:8px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius);">
              <?php foreach ($todoServicos as $s): ?>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text); cursor:pointer; text-transform:none; letter-spacing:0; font-weight:400;">
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

<div class="panel">

  <div class="page-head">
    <h1>Serviços &amp; Combos</h1>
    <p class="sub">Gerencie o catálogo</p>
  </div>

  <?php foreach ($erros as $e): ?>
    <div class="flash flash--err"><?= e($e) ?></div>
  <?php endforeach; ?>

  <!-- ── CATEGORIAS ─────────────────────────────────────────── -->
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Categorias</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols">

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

      <div class="panel-form">
        <p class="panel-form-title">Nova categoria</p>
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
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Serviços</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols panel-cols--wide">

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Nome</th><th>Categoria</th><th>Preço</th><th>Duração</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($servicos as $s): ?>
            <tr>
              <td><strong><?= e($s['nome']) ?></strong><br><span style="font-size:12px;color:var(--muted)"><?= e(mb_strimwidth($s['descricao'] ?? '', 0, 50, '…')) ?></span></td>
              <td style="font-size:13px; color:var(--muted)"><?= e($s['categoria']) ?></td>
              <td style="color:var(--accent)">R$ <?= number_format((float)$s['preco'], 2, ',', '.') ?></td>
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

      <div class="panel-form">
        <p class="panel-form-title">Novo serviço</p>
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
          <div class="form-grid-2">
            <div class="field"><label>Preço (R$)</label><input type="text" name="preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="duracao_min" required min="5" max="480"></div>
          </div>
          <button class="btn btn--sm" type="submit">Salvar serviço</button>
        </form>
      </div>

    </div>
  </div>

  <!-- ── COMBOS ─────────────────────────────────────────────── -->
  <div class="panel-section">
    <div class="section-head">
      <h2 style="font-size:1.3rem">Combos</h2>
      <span class="section-head-line"></span>
    </div>
    <div class="panel-cols panel-cols--wide">

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
              <td style="color:var(--accent)">R$ <?= number_format((float)$c['preco'], 2, ',', '.') ?></td>
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

      <div class="panel-form">
        <p class="panel-form-title">Novo combo</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar_combo">
          <div class="field"><label>Nome</label><input type="text" name="combo_nome" required maxlength="100"></div>
          <div class="field"><label>Descrição</label><textarea name="combo_desc" rows="2"></textarea></div>
          <div class="form-grid-2">
            <div class="field"><label>Preço (R$)</label><input type="text" name="combo_preco" required placeholder="0.00"></div>
            <div class="field"><label>Duração (min)</label><input type="number" name="combo_dur" required min="5" max="480"></div>
          </div>
          <div class="field">
            <label>Serviços incluídos</label>
            <div style="display:flex; flex-direction:column; gap:6px; max-height:160px; overflow-y:auto; padding:8px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius);">
              <?php foreach ($todoServicos as $s): ?>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text); cursor:pointer; text-transform:none; letter-spacing:0; font-weight:400;">
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
