<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

exigir_login();
exigir_perfil('barbeiro');

$pdo     = db();
$usuario = usuario_logado();
$erros   = [];

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $nome        = trim($_POST['nome']        ?? '');
    $descricao   = trim($_POST['descricao']   ?? '');
    $cat_id      = (int)($_POST['categoria_id'] ?? 0);
    $preco       = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
    $duracao_min = (int)($_POST['duracao_min'] ?? 0);

    if (mb_strlen($nome) < 2)   $erros[] = 'Nome do serviço obrigatório.';
    if ($preco <= 0)             $erros[] = 'Informe um preço válido.';
    if ($duracao_min < 5)        $erros[] = 'Duração mínima de 5 minutos.';

    if (!$erros) {
        $pdo->prepare(
            "INSERT INTO tickets(barbeiro_id, nome, descricao, categoria_id, preco, duracao_min)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $usuario['id'],
            $nome,
            $descricao ?: null,
            $cat_id ?: null,
            $preco,
            $duracao_min,
        ]);

        // notifica todos os admins
        $admins = $pdo->query("SELECT nome, email FROM usuarios WHERE perfil='admin' AND ativo=1")->fetchAll();
        $corpo  = "
            <p>O barbeiro <strong>" . e($usuario['nome']) . "</strong> sugeriu um novo serviço:</p>
            <p style='font-size:1rem; font-weight:600; color:#1C1814; margin:14px 0 4px;'>" . e($nome) . "</p>
            " . ($descricao ? "<p style='color:#6E6258;font-size:13px;'>" . e($descricao) . "</p>" : '') . "
            <p style='color:#6E6258; font-size:13px; margin-top:8px;'>
              Preço: R$ " . number_format($preco, 2, ',', '.') . " &nbsp;·&nbsp; Duração: {$duracao_min} min
            </p>
            <p style='margin-top:16px;'>
              <a href='" . e(env('APP_URL') ?: 'http://localhost:8080') . "/admin/tickets.php'
                 style='display:inline-block; background:#7C5C3E; color:#fff; padding:10px 20px; text-decoration:none; font-weight:600;'>
                Ver tickets pendentes
              </a>
            </p>
        ";
        foreach ($admins as $admin) {
            enviar_email($admin['email'], $admin['nome'], "Novo ticket: " . e($nome), $corpo);
        }

        flash('ok', 'Sugestão enviada para análise da administração.');
        header('Location: /barbeiro/ticket.php'); exit;
    }
}

// ── Dados ─────────────────────────────────────────────────────
$meus_tickets = $pdo->prepare(
    "SELECT t.id, t.nome, t.status, t.obs_admin, t.criado_em, t.resolvido_em,
            cat.nome AS categoria_nome
     FROM tickets t
     LEFT JOIN categorias cat ON cat.id = t.categoria_id
     WHERE t.barbeiro_id = ?
     ORDER BY t.criado_em DESC
     LIMIT 20"
);
$meus_tickets->execute([$usuario['id']]);
$meus_tickets = $meus_tickets->fetchAll();

$categorias = $pdo->query('SELECT id, nome FROM categorias WHERE ativo=1 ORDER BY ordem')->fetchAll();

$titulo = 'Sugerir serviço';
require __DIR__ . '/../../includes/header.php';
?>

<div style="padding-top:36px; padding-bottom:80px;">

  <div class="page-head">
    <div><h1>Sugerir serviço</h1><p class="sub">Envie sugestões de novos serviços para o admin avaliar</p></div>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:start;">

    <!-- formulário -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem">Nova sugestão</h2>
        <span class="section-head-line"></span>
      </div>
      <div class="card">
        <?php foreach ($erros as $err): ?>
          <div class="flash flash--err"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="field">
            <label>Nome do serviço</label>
            <input type="text" name="nome" required maxlength="100" placeholder="Ex: Luzes, Relaxamento…">
          </div>
          <div class="field">
            <label>Categoria (opcional)</label>
            <select name="categoria_id">
              <option value="">Sem categoria</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Descrição</label>
            <textarea name="descricao" rows="3" placeholder="Descreva o serviço brevemente…"></textarea>
          </div>
          <div class="form-row">
            <div class="field">
              <label>Preço sugerido (R$)</label>
              <input type="text" name="preco" required placeholder="0,00">
            </div>
            <div class="field">
              <label>Duração sugerida (min)</label>
              <input type="number" name="duracao_min" required min="5" max="480" placeholder="30">
            </div>
          </div>
          <button class="btn" type="submit">Enviar sugestão</button>
        </form>
      </div>
    </div>

    <!-- minhas sugestões -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.2rem">Minhas sugestões</h2>
        <span class="section-head-line"></span>
      </div>

      <?php if ($meus_tickets): ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($meus_tickets as $tk): ?>
          <div class="card <?= $tk['status'] === 'aprovado' ? 'card--ok' : ($tk['status'] === 'recusado' ? 'card--err' : 'card--warn') ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
              <div>
                <p style="font-size:14px; font-weight:600; color:var(--text); margin-bottom:3px;"><?= e($tk['nome']) ?></p>
                <?php if ($tk['categoria_nome']): ?>
                  <span class="badge badge--accent" style="font-size:10px"><?= e($tk['categoria_nome']) ?></span>
                <?php endif; ?>
                <p style="font-size:12px; color:var(--muted); margin-top:4px;">
                  Enviado em <?= (new DateTimeImmutable($tk['criado_em']))->format('d/m/Y') ?>
                </p>
                <?php if ($tk['obs_admin']): ?>
                  <p style="font-size:12px; color:var(--muted); margin-top:6px; padding:6px 10px; background:var(--surface-2); border-radius:var(--radius);">
                    Admin: <?= e($tk['obs_admin']) ?>
                  </p>
                <?php endif; ?>
              </div>
              <span class="badge <?= $tk['status'] === 'aprovado' ? 'badge--ok' : ($tk['status'] === 'recusado' ? 'badge--err' : 'badge--warn') ?>">
                <?= ucfirst($tk['status']) ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card"><p class="muted">Nenhuma sugestão enviada ainda.</p></div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
