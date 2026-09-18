<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();
$erros   = [];

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    // upload de foto
    if ($acao === 'upload') {
        $file      = $_FILES['foto'] ?? [];
        $legenda   = trim($_POST['legenda']   ?? '');
        $servico_id = (int)($_POST['servico_id'] ?? 0) ?: null;

        $resultado = salvar_imagem($file, 'portfolio');

        if (!$resultado['ok']) {
            $erros[] = $resultado['erro'];
        } else {
            $pdo->prepare(
                "INSERT INTO portfolio(barbeiro_id, servico_id, foto_path, legenda)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $usuario['id'],
                $servico_id,
                $resultado['path'],
                $legenda ?: null,
            ]);
            flash('ok', 'Foto adicionada ao portfólio.');
            header('Location: /barbeiro/portfolio.php'); exit;
        }
    }

    // excluir foto
    if ($acao === 'excluir') {
        $foto_id = (int)($_POST['foto_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT foto_path FROM portfolio WHERE id=? AND barbeiro_id=?");
        $stmt->execute([$foto_id, $usuario['id']]);
        $foto = $stmt->fetch();

        if ($foto) {
            remover_upload($foto['foto_path']);
            $pdo->prepare("DELETE FROM portfolio WHERE id=? AND barbeiro_id=?")->execute([$foto_id, $usuario['id']]);
            flash('ok', 'Foto removida.');
        }
        header('Location: /barbeiro/portfolio.php'); exit;
    }
}

// ── Dados ─────────────────────────────────────────────────────
$fotos = $pdo->prepare(
    "SELECT p.id, p.foto_path, p.legenda, p.criado_em, s.nome AS servico_nome
     FROM portfolio p
     LEFT JOIN servicos s ON s.id = p.servico_id
     WHERE p.barbeiro_id = ?
     ORDER BY p.criado_em DESC
     LIMIT 50"
);
$fotos->execute([$usuario['id']]);
$fotos = $fotos->fetchAll();

$meus_servicos = $pdo->prepare(
    "SELECT s.id, s.nome FROM barbeiro_servicos bs
     JOIN servicos s ON s.id = bs.servico_id AND s.ativo = 1
     WHERE bs.barbeiro_id = ? ORDER BY s.nome"
);
$meus_servicos->execute([$usuario['id']]);
$meus_servicos = $meus_servicos->fetchAll();

$titulo = 'Portfólio';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:36px; padding-bottom:80px;">

  <div class="page-head">
    <div><h1>Portfólio</h1><p class="sub">Mostre seu trabalho para os clientes</p></div>
  </div>

  <!-- formulário de upload -->
  <div class="card card--accent" style="margin-bottom:32px;">
    <p style="font-size:12px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--muted); margin-bottom:16px;">
      Adicionar foto
    </p>
    <?php foreach ($erros as $err): ?>
      <div class="flash flash--err"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="upload">
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; align-items:end;">
        <div class="field" style="margin:0; grid-column:1/-1;">
          <label>Foto (JPG, PNG ou WEBP — máx. 5 MB)</label>
          <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                 style="padding:6px; cursor:pointer;">
        </div>
        <div class="field" style="margin:0;">
          <label>Legenda (opcional)</label>
          <input type="text" name="legenda" maxlength="255" placeholder="Ex: Degradê + barba">
        </div>
        <div class="field" style="margin:0;">
          <label>Serviço relacionado (opcional)</label>
          <select name="servico_id">
            <option value="">Nenhum</option>
            <?php foreach ($meus_servicos as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex; align-items:flex-end; padding-bottom:1px;">
          <button class="btn btn--sm" type="submit">Enviar foto</button>
        </div>
      </div>
    </form>
  </div>

  <!-- grade de fotos -->
  <?php if ($fotos): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:12px;">
      <?php foreach ($fotos as $f): ?>
        <div class="card" style="padding:0; overflow:hidden; position:relative;">
          <img src="/uploads/<?= e($f['foto_path']) ?>"
               alt="<?= e($f['legenda'] ?? 'Foto do portfólio') ?>"
               style="width:100%; height:180px; object-fit:cover; display:block;">

          <div style="padding:10px 12px;">
            <?php if ($f['legenda']): ?>
              <p style="font-size:13px; color:var(--text); margin-bottom:3px;"><?= e($f['legenda']) ?></p>
            <?php endif; ?>
            <?php if ($f['servico_nome']): ?>
              <span class="badge badge--accent" style="font-size:10px;"><?= e($f['servico_nome']) ?></span>
            <?php endif; ?>
            <p style="font-size:11px; color:var(--faint); margin-top:4px;">
              <?= (new DateTimeImmutable($f['criado_em']))->format('d/m/Y') ?>
            </p>
          </div>

          <form method="post" style="position:absolute; top:8px; right:8px;"
                onsubmit="return confirm('Remover esta foto?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="foto_id" value="<?= (int)$f['id'] ?>">
            <button type="submit"
                    style="background:rgba(255,255,255,.85); border:none; padding:4px 8px; cursor:pointer; font-size:12px; color:var(--err); border-radius:3px;">
              Remover
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card" style="text-align:center; padding:48px 24px;">
      <p style="color:var(--muted); margin-bottom:12px;">Nenhuma foto no portfólio ainda.</p>
      <p style="font-size:13px; color:var(--faint);">Adicione fotos dos seus trabalhos para atrair mais clientes.</p>
    </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
