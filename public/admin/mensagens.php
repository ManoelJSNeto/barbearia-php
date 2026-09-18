<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_perfil('admin');

$pdo   = db();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao      = $_POST['acao']  ?? '';
    $id        = (int)($_POST['id'] ?? 0);
    $titulo_m  = trim($_POST['titulo'] ?? '');
    $corpo     = trim($_POST['corpo']  ?? '');

    if ($acao === 'salvar') {
        if (mb_strlen($titulo_m) < 2) $erros[] = 'Título obrigatório.';
        if (mb_strlen($corpo)    < 5) $erros[] = 'Corpo obrigatório.';

        if (!$erros) {
            if ($id) {
                $pdo->prepare("UPDATE mensagens_rapidas SET titulo=?, corpo=? WHERE id=?")->execute([$titulo_m, $corpo, $id]);
            } else {
                $pdo->prepare("INSERT INTO mensagens_rapidas(titulo, corpo) VALUES(?,?)")->execute([$titulo_m, $corpo]);
            }
            flash('ok', 'Mensagem salva.');
            header('Location: /admin/mensagens.php'); exit;
        }
    }

    if ($acao === 'toggle' && $id) {
        $pdo->prepare("UPDATE mensagens_rapidas SET ativo = NOT ativo WHERE id=?")->execute([$id]);
        flash('ok', 'Status atualizado.');
        header('Location: /admin/mensagens.php'); exit;
    }
}

$mensagens = $pdo->query('SELECT id, titulo, corpo, ativo FROM mensagens_rapidas ORDER BY titulo')->fetchAll();
$editando  = null;
if (isset($_GET['editar'])) {
    $s = $pdo->prepare('SELECT * FROM mensagens_rapidas WHERE id=?');
    $s->execute([(int)$_GET['editar']]);
    $editando = $s->fetch();
}

$titulo = 'Mensagens rápidas';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <div><h1>Mensagens rápidas</h1><p class="sub">Modelos para envio a clientes</p></div>
  </div>

  <div class="panel-cols">

    <!-- lista -->
    <div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Título</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($mensagens as $m): ?>
            <tr>
              <td>
                <strong><?= e($m['titulo']) ?></strong><br>
                <span style="font-size:12px; color:var(--muted);"><?= e(mb_strimwidth($m['corpo'], 0, 60, '…')) ?></span>
              </td>
              <td><span class="badge <?= $m['ativo'] ? 'badge--ok' : 'badge--muted' ?>"><?= $m['ativo'] ? 'Ativa' : 'Inativa' ?></span></td>
              <td style="white-space:nowrap; font-size:12px;">
                <a href="?editar=<?= (int)$m['id'] ?>" style="color:var(--accent);">Editar</a>
                &nbsp;
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                  <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;color:var(--muted);font-size:12px;"><?= $m['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$mensagens): ?><tr><td colspan="3" style="color:var(--muted);text-align:center;padding:24px;">Nenhuma mensagem cadastrada.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- form -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem"><?= $editando ? 'Editar mensagem' : 'Nova mensagem' ?></h2>
        <span class="section-head-line"></span>
        <?php if ($editando): ?><a href="/admin/mensagens.php" style="font-size:12px; color:var(--muted)">Cancelar</a><?php endif; ?>
      </div>
      <div class="panel-form">
        <?php foreach ($erros as $err): ?>
          <div class="flash flash--err"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int)($editando['id'] ?? 0) ?>">
          <div class="field">
            <label>Título</label>
            <input type="text" name="titulo" required maxlength="100" value="<?= e($editando['titulo'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Corpo</label>
            <textarea name="corpo" rows="5" required><?= e($editando['corpo'] ?? '') ?></textarea>
          </div>
          <p style="font-size:12px; color:var(--faint); margin-bottom:14px;">
            Variáveis disponíveis: <code>{nome_cliente}</code> · <code>{data_hora}</code> · <code>{barbeiro}</code> · <code>{servico}</code>
          </p>
          <button class="btn btn--sm" type="submit"><?= $editando ? 'Salvar alterações' : 'Criar mensagem' ?></button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
