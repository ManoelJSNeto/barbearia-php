<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

if ($id < 1) {
    header('Location: /'); exit;
}

$stmt = $pdo->prepare(
    "SELECT id, nome FROM usuarios WHERE id=? AND perfil='barbeiro' AND ativo=1 LIMIT 1"
);
$stmt->execute([$id]);
$barbeiro = $stmt->fetch();

if (!$barbeiro) {
    http_response_code(404);
    $titulo = 'Barbeiro não encontrado';
    require __DIR__ . '/../includes/header.php';
    echo '<div style="padding:80px 0"><p class="eyebrow">404</p><h1 style="font-size:2rem;margin-bottom:12px">Barbeiro não encontrado</h1><p class="muted">Este barbeiro não existe ou não está disponível.</p><p style="margin-top:20px"><a class="btn btn--ghost" href="/">Voltar</a></p></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// serviços que ele oferece
$servicos = $pdo->prepare(
    "SELECT s.id, s.nome, s.descricao, s.preco, s.duracao_min, c.nome AS categoria
     FROM barbeiro_servicos bs
     JOIN servicos s ON s.id = bs.servico_id AND s.ativo = 1
     JOIN categorias c ON c.id = s.categoria_id AND c.ativo = 1
     WHERE bs.barbeiro_id = ?
     ORDER BY c.ordem, s.nome"
);
$servicos->execute([$id]);
$servicos = $servicos->fetchAll();

// combos
$combos = $pdo->prepare(
    "SELECT c.nome, c.descricao, c.preco, c.duracao_min
     FROM barbeiro_combos bc
     JOIN combos c ON c.id = bc.combo_id AND c.ativo = 1
     WHERE bc.barbeiro_id = ?
     ORDER BY c.nome"
);
$combos->execute([$id]);
$combos = $combos->fetchAll();

// capa dos serviços
$srvIds = array_column($servicos, 'id');
$fotosCapas = [];
if ($srvIds) {
    $placeholders = implode(',', array_fill(0, count($srvIds), '?'));
    $fstmt = $pdo->prepare(
        "SELECT servico_id, MIN(foto_path) AS foto_path
         FROM servico_fotos WHERE servico_id IN ($placeholders)
         GROUP BY servico_id"
    );
    $fstmt->execute($srvIds);
    foreach ($fstmt->fetchAll() as $f) {
        $fotosCapas[$f['servico_id']] = $f['foto_path'];
    }
}

// portfólio (últimas 10 fotos)
$portfolio = $pdo->prepare(
    "SELECT p.foto_path, p.legenda, s.nome AS servico_nome
     FROM portfolio p
     LEFT JOIN servicos s ON s.id = p.servico_id
     WHERE p.barbeiro_id = ?
     ORDER BY p.criado_em DESC
     LIMIT 10"
);
$portfolio->execute([$id]);
$portfolio = $portfolio->fetchAll();

$titulo = $barbeiro['nome'];
require __DIR__ . '/../includes/header.php';
?>

<div style="padding-top:48px; padding-bottom:80px;">

  <!-- cabeçalho do barbeiro -->
  <div style="display:flex; align-items:center; gap:24px; padding-bottom:32px; border-bottom:1px solid var(--border); margin-bottom:40px;">
    <div style="width:72px; height:72px; background:var(--surface-2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:2rem; color:var(--gold-dim); flex-shrink:0;">
      <?= e(mb_strtoupper(mb_substr($barbeiro['nome'], 0, 1))) ?>
    </div>
    <div>
      <h1 style="font-size:2rem; margin-bottom:4px;"><?= e($barbeiro['nome']) ?></h1>
      <p class="muted" style="font-size:13px;">Barbeiro profissional</p>
    </div>
    <div style="margin-left:auto;">
      <a class="btn" href="/agendar.php?barbeiro=<?= (int)$barbeiro['id'] ?>">Agendar com <?= e(explode(' ', $barbeiro['nome'])[0]) ?></a>
    </div>
  </div>

  <!-- serviços -->
  <?php if ($servicos || $combos): ?>
    <div class="section-head">
      <h2 style="font-size:1.4rem">Serviços oferecidos</h2>
      <span class="section-head-line"></span>
    </div>

    <div class="services-grid" style="margin-bottom:36px;">
      <?php foreach ($servicos as $s):
        $capa = $fotosCapas[$s['id']] ?? null; ?>
        <div class="service-card">
          <?php if ($capa): ?>
            <div class="service-card-img">
              <img src="/uploads/<?= e($capa) ?>" alt="<?= e($s['nome']) ?>" loading="lazy">
            </div>
          <?php endif; ?>
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
        <div class="service-card service-card--combo">
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

    <a class="btn" href="/agendar.php?barbeiro=<?= (int)$barbeiro['id'] ?>">Agendar horário</a>

  <?php else: ?>
    <div class="card">
      <p class="muted">Este barbeiro ainda não tem serviços configurados.</p>
    </div>
  <?php endif; ?>

  <!-- portfólio -->
  <?php if ($portfolio): ?>
  <div style="margin-top:52px;">
    <div class="section-head">
      <h2 style="font-size:1.4rem">Portfólio</h2>
      <span class="section-head-line"></span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:20px;">
      <?php foreach ($portfolio as $i => $foto): ?>
        <a href="<?= e($foto['foto_path']) ?>" target="_blank" rel="noopener"
           style="display:block; aspect-ratio:1; overflow:hidden; background:var(--surface-2); border:1px solid var(--border);"
           title="<?= e($foto['legenda'] ?: $foto['servico_nome'] ?: '') ?>">
          <img src="<?= e($foto['foto_path']) ?>"
               alt="<?= e($foto['legenda'] ?: ($foto['servico_nome'] ? 'Foto de ' . $foto['servico_nome'] : 'Foto do portfólio')) ?>"
               style="width:100%; height:100%; object-fit:cover;"
               loading="<?= $i < 3 ? 'eager' : 'lazy' ?>">
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
