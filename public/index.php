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
            GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ' · ') AS especialidades
     FROM usuarios u
     LEFT JOIN barbeiro_servicos bs ON bs.barbeiro_id = u.id
     LEFT JOIN servicos s ON s.id = bs.servico_id AND s.ativo = 1
     WHERE u.perfil = 'barbeiro' AND u.ativo = 1
     GROUP BY u.id, u.nome
     ORDER BY u.nome"
)->fetchAll();

$titulo = 'Barbearia tradicional';
require __DIR__ . '/../includes/header.php';
?>

<!-- ── HERO ─────────────────────────────────────────────── -->
<section style="padding: 80px 0 72px; border-bottom: 1px solid var(--border);">
  <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 64px; align-items: end;">

    <div>
      <p class="eyebrow">Barbearia tradicional</p>
      <h1 style="margin-bottom: 20px; max-width: 13ch;">Um corte feito do jeito certo.</h1>
      <p class="lead" style="max-width: 42ch; margin-bottom: 32px;">
        Escolha o serviço, veja os barbeiros disponíveis e marque seu horário sem complicação.
        Sem fila, sem surpresa no preço.
      </p>
      <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
        <a class="btn" href="<?= usuario_logado() ? '/agendar.php' : '/cadastro.php' ?>">Agendar agora</a>
        <a class="btn btn--ghost" href="#servicos">Ver serviços</a>
      </div>
    </div>

    <aside style="background: var(--surface-2); border: 1px solid var(--border); border-left: 3px solid var(--gold); padding: 26px 22px;">
      <p style="font-size: 11px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--gold-pale); margin-bottom: 18px;">Horários disponíveis hoje</p>
      <?php if ($barbeiros): ?>
        <?php foreach (array_slice($barbeiros, 0, 3) as $b): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 11px 0; border-bottom: 1px solid var(--border);">
            <div>
              <span style="font-size: 13px; color: var(--text);"><?= e($b['nome']) ?></span>
              <span style="display:block; font-size: 11px; color: var(--muted);">ver disponibilidade</span>
            </div>
            <a href="/agendar.php?barbeiro=<?= (int)$b['id'] ?>" style="font-size: 12px; color: var(--gold); border-bottom: 1px solid var(--faint); padding-bottom: 1px;">Agendar</a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="font-size: 13px; color: var(--muted);">Em breve.</p>
      <?php endif; ?>
      <div style="margin-top: 18px;">
        <a class="btn btn--full" href="<?= usuario_logado() ? '/agendar.php' : '/cadastro.php' ?>" style="font-size: 13px; padding: 11px 16px;">
          Ver todos os horários
        </a>
      </div>
    </aside>

  </div>
</section>

<?php // media query inline para o hero grid ?>
<style>
@media (max-width: 720px) {
  section:first-of-type > div { grid-template-columns: 1fr !important; }
  section:first-of-type aside { display: none; }
}
</style>

<!-- ── SERVIÇOS ──────────────────────────────────────────── -->
<section class="section" id="servicos">
  <div class="section-head">
    <h2>Serviços</h2>
    <span class="section-head-line"></span>
  </div>

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

    <?php if (!$servicos && !$combos): ?>
      <div class="service-card" style="grid-column: 1/-1;">
        <p class="service-desc">Nenhum serviço cadastrado ainda.</p>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($servicos || $combos): ?>
    <p style="margin-top: 20px;">
      <a class="btn" href="<?= usuario_logado() ? '/agendar.php' : '/cadastro.php' ?>">Agendar um serviço</a>
    </p>
  <?php endif; ?>
</section>

<div class="divider"></div>

<!-- ── BARBEIROS ─────────────────────────────────────────── -->
<section class="section" id="barbeiros">
  <div class="section-head">
    <h2>Barbeiros</h2>
    <span class="section-head-line"></span>
  </div>

  <?php if ($barbeiros): ?>
    <div class="barbers-list">
      <?php foreach ($barbeiros as $b): ?>
        <a class="barber-row" href="/agendar.php?barbeiro=<?= (int)$b['id'] ?>">
          <div class="barber-avatar"><?= e(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?></div>
          <div>
            <p class="barber-name"><?= e($b['nome']) ?></p>
            <p class="barber-spec"><?= e($b['especialidades'] ?: 'Especialidades em breve') ?></p>
          </div>
          <span class="barber-arrow">›</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted">Nossa equipe será divulgada em breve.</p>
  <?php endif; ?>
</section>

<div class="divider"></div>

<!-- ── COMO FUNCIONA ─────────────────────────────────────── -->
<section class="section" id="como-funciona">
  <div class="section-head">
    <h2>Como funciona</h2>
    <span class="section-head-line"></span>
  </div>

  <div class="steps-grid">
    <div class="step">
      <p class="step-n">1</p>
      <p class="step-title">Escolha o serviço</p>
      <p class="step-desc">Veja os serviços disponíveis com preço e duração. Combos têm desconto automático.</p>
    </div>
    <div class="step">
      <p class="step-n">2</p>
      <p class="step-title">Veja os horários</p>
      <p class="step-desc">Só aparecem os horários realmente livres, baseados na agenda dos barbeiros.</p>
    </div>
    <div class="step">
      <p class="step-n">3</p>
      <p class="step-title">Escolha o barbeiro</p>
      <p class="step-desc">Ou comece pelo barbeiro se preferir alguém específico. Sem surpresas.</p>
    </div>
    <div class="step">
      <p class="step-n">4</p>
      <p class="step-title">Confirme presença</p>
      <p class="step-desc">Você recebe um link por e-mail. Confirme até 2h antes e apareça no horário.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
