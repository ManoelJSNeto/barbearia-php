<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

$servicos = $pdo->query(
    'SELECT s.id, s.nome, s.descricao, s.preco, s.duracao_min, c.nome AS categoria
     FROM servicos s
     JOIN categorias c ON c.id = s.categoria_id
     WHERE s.ativo = 1 AND c.ativo = 1
     ORDER BY c.ordem, s.nome'
)->fetchAll();

$combos = $pdo->query(
    'SELECT id, nome, descricao, preco, duracao_min FROM combos WHERE ativo = 1 ORDER BY nome'
)->fetchAll();

$barbeiros = $pdo->query(
    "SELECT u.id, u.nome,
            GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS especialidades,
            COUNT(DISTINCT bs.servico_id) AS total_servicos
     FROM usuarios u
     LEFT JOIN barbeiro_servicos bs ON bs.barbeiro_id = u.id
     LEFT JOIN servicos s ON s.id = bs.servico_id AND s.ativo = 1
     WHERE u.perfil = 'barbeiro' AND u.ativo = 1
     GROUP BY u.id, u.nome
     ORDER BY u.nome"
)->fetchAll();

$logado = usuario_logado();
$titulo = 'Navalha — Barbearia tradicional';
require __DIR__ . '/../includes/header.php';
?>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-content">
    <p class="eyebrow">Barbearia tradicional</p>
    <h1 class="hero-title">Um corte feito<br>do jeito certo.</h1>
    <p class="hero-lead">
      Escolha o serviço, veja os barbeiros disponíveis e marque seu horário em menos de um minuto.
      Sem fila, sem surpresa no preço.
    </p>
    <div class="hero-actions">
      <a class="btn" href="<?= $logado ? '/agendar.php' : '/cadastro.php' ?>">
        <?= $logado ? 'Agendar agora' : 'Criar conta e agendar' ?>
      </a>
      <a class="btn btn--ghost" href="#servicos">Ver serviços</a>
    </div>
  </div>

  <aside class="hero-card">
    <p class="hero-card-label">Profissionais</p>
    <?php if ($barbeiros): ?>
      <?php foreach (array_slice($barbeiros, 0, 4) as $b): ?>
        <div class="hero-barber">
          <div class="hero-barber-avatar">
            <?= e(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?>
          </div>
          <div class="hero-barber-info">
            <span class="hero-barber-name"><?= e(explode(' ', $b['nome'])[0]) ?></span>
            <span class="hero-barber-spec"><?= (int)$b['total_servicos'] ?> serviço<?= $b['total_servicos'] != 1 ? 's' : '' ?></span>
          </div>
          <a class="btn btn--outline btn--xs" href="/agendar.php?barbeiro=<?= (int)$b['id'] ?>">Agendar</a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="font-size:13px; color:var(--muted); padding:12px 0;">Em breve.</p>
    <?php endif; ?>
    <div style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border);">
      <a class="btn btn--full btn--sm" href="<?= $logado ? '/agendar.php' : '/cadastro.php' ?>">
        Ver todos os horários disponíveis
      </a>
    </div>
  </aside>
</section>

<!-- ── NÚMEROS ────────────────────────────────────────────────── -->
<section class="home-stats">
  <div class="home-stat">
    <span class="home-stat-n"><?= count($servicos) + count($combos) ?></span>
    <span class="home-stat-label">Serviços &amp; combos</span>
  </div>
  <div class="home-stat">
    <span class="home-stat-n"><?= count($barbeiros) ?></span>
    <span class="home-stat-label">Barbeiro<?= count($barbeiros) != 1 ? 's' : '' ?> profissional<?= count($barbeiros) != 1 ? 'is' : '' ?></span>
  </div>
  <div class="home-stat">
    <span class="home-stat-n">4</span>
    <span class="home-stat-label">Dias de agenda abertos</span>
  </div>
  <div class="home-stat">
    <span class="home-stat-n">2h</span>
    <span class="home-stat-label">Antecedência para cancelar</span>
  </div>
</section>

<!-- ── SERVIÇOS ──────────────────────────────────────────────── -->
<section class="section" id="servicos">
  <div class="section-head">
    <h2>Serviços</h2>
    <span class="section-head-line"></span>
    <?php if ($logado): ?>
      <a class="btn btn--sm" href="/agendar.php" style="margin-left:auto;">Agendar agora</a>
    <?php endif; ?>
  </div>

  <?php if ($servicos || $combos): ?>
    <div class="services-grid">
      <?php foreach ($servicos as $s): ?>
        <div class="service-card">
          <p class="service-cat"><?= e($s['categoria']) ?></p>
          <p class="service-name"><?= e($s['nome']) ?></p>
          <?php if ($s['descricao']): ?>
            <p class="service-desc"><?= e($s['descricao']) ?></p>
          <?php endif; ?>
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
          <?php if ($c['descricao']): ?>
            <p class="service-desc"><?= e($c['descricao']) ?></p>
          <?php endif; ?>
          <div class="service-meta">
            <span class="service-price">R$ <?= number_format((float)$c['preco'], 2, ',', '.') ?></span>
            <span class="service-time"><?= (int)$c['duracao_min'] ?> min</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <p style="margin-top:24px;">
      <a class="btn" href="<?= $logado ? '/agendar.php' : '/cadastro.php' ?>">
        <?= $logado ? 'Agendar um serviço' : 'Criar conta para agendar' ?>
      </a>
    </p>
  <?php else: ?>
    <div class="card">
      <p class="muted">Nenhum serviço cadastrado ainda. Volte em breve.</p>
    </div>
  <?php endif; ?>
</section>

<div class="divider"></div>

<!-- ── BARBEIROS ─────────────────────────────────────────────── -->
<section class="section" id="barbeiros">
  <div class="section-head">
    <h2>Nossa equipe</h2>
    <span class="section-head-line"></span>
  </div>

  <?php if ($barbeiros): ?>
    <div class="barbers-grid">
      <?php foreach ($barbeiros as $b): ?>
        <div class="barber-card">
          <div class="barber-card-avatar">
            <?= e(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?>
          </div>
          <div class="barber-card-body">
            <p class="barber-card-name"><?= e($b['nome']) ?></p>
            <p class="barber-card-spec">
              <?= $b['especialidades']
                ? e(mb_strimwidth($b['especialidades'], 0, 60, '…'))
                : 'Especialidades em breve' ?>
            </p>
          </div>
          <div class="barber-card-actions">
            <a class="btn btn--ghost btn--sm" href="/barbeiro.php?id=<?= (int)$b['id'] ?>">Ver perfil</a>
            <a class="btn btn--sm" href="/agendar.php?barbeiro=<?= (int)$b['id'] ?>">Agendar</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted">Nossa equipe será divulgada em breve.</p>
  <?php endif; ?>
</section>

<div class="divider"></div>

<!-- ── COMO FUNCIONA ─────────────────────────────────────────── -->
<section class="section" id="como-funciona">
  <div class="section-head">
    <h2>Como funciona</h2>
    <span class="section-head-line"></span>
  </div>

  <div class="steps-grid">
    <div class="step">
      <p class="step-n">1</p>
      <p class="step-title">Escolha o serviço</p>
      <p class="step-desc">Veja os serviços disponíveis com preço e duração. Combos oferecem mais por menos.</p>
    </div>
    <div class="step">
      <p class="step-n">2</p>
      <p class="step-title">Escolha o barbeiro</p>
      <p class="step-desc">Pode começar pelo barbeiro preferido ou pelo serviço — do seu jeito.</p>
    </div>
    <div class="step">
      <p class="step-n">3</p>
      <p class="step-title">Veja os horários livres</p>
      <p class="step-desc">Só aparecem horários realmente disponíveis. Sem surpresas na hora de chegar.</p>
    </div>
    <div class="step">
      <p class="step-n">4</p>
      <p class="step-title">Confirme presença</p>
      <p class="step-desc">Confirme na hora ou pelo link no e-mail. Pode cancelar até 2h antes.</p>
    </div>
  </div>
</section>

<!-- ── CTA FINAL ──────────────────────────────────────────────── -->
<?php if (!$logado): ?>
<section class="home-cta">
  <p class="eyebrow" style="color:var(--accent-light)">Pronto para começar?</p>
  <h2 class="home-cta-title">Reserve seu horário agora.</h2>
  <p class="home-cta-sub">Crie sua conta em segundos e agende sem precisar ligar.</p>
  <div class="hero-actions" style="justify-content:center;">
    <a class="btn" href="/cadastro.php" style="background:#fff; color:var(--accent); border-color:#fff;">Criar conta grátis</a>
    <a class="btn btn--ghost" href="/login.php" style="color:#fff; border-color:rgba(255,255,255,.4);">Já tenho conta</a>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
