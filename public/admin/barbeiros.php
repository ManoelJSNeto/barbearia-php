<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

exigir_perfil('admin');

$pdo = db();
$erros = [];

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    // cadastrar barbeiro
    if ($acao === 'criar') {
        $nome  = trim($_POST['nome']  ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = $_POST['senha'] ?? '';

        if (mb_strlen($nome) < 2)       $erros[] = 'Nome deve ter pelo menos 2 caracteres.';
        if (!$email)                     $erros[] = 'E-mail inválido.';
        if (strlen($senha) < 6)          $erros[] = 'Senha deve ter pelo menos 6 caracteres.';

        if (!$erros) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios(nome, email, senha_hash, perfil) VALUES (?, ?, ?, 'barbeiro')"
                );
                $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12])]);
                flash('ok', "Barbeiro {$nome} cadastrado.");
                header('Location: /admin/barbeiros.php'); exit;
            } catch (PDOException) {
                $erros[] = 'Este e-mail já está cadastrado.';
            }
        }
    }

    // desativar/reativar
    if ($acao === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id=? AND perfil='barbeiro'")->execute([$id]);
        flash('ok', 'Status atualizado.');
        header('Location: /admin/barbeiros.php'); exit;
    }

    // salvar carga horária
    if ($acao === 'carga_horaria') {
        $barbeiro_id = (int)($_POST['barbeiro_id'] ?? 0);
        $dias = $_POST['dia'] ?? [];

        $pdo->prepare("DELETE FROM carga_horaria WHERE barbeiro_id=?")->execute([$barbeiro_id]);

        foreach ($dias as $dia => $on) {
            $ini = trim($_POST["inicio_{$dia}"] ?? '');
            $fim = trim($_POST["fim_{$dia}"]    ?? '');
            if (!$ini || !$fim) continue;
            $pdo->prepare(
                "INSERT INTO carga_horaria(barbeiro_id, dia_semana, hora_inicio, hora_fim) VALUES(?,?,?,?)"
            )->execute([$barbeiro_id, (int)$dia, $ini, $fim]);
        }

        flash('ok', 'Carga horária salva.');
        header("Location: /admin/barbeiros.php?carga={$barbeiro_id}"); exit;
    }
}

// ── Dados ────────────────────────────────────────────────────
$barbeiros = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.ativo,
            COUNT(DISTINCT a.id) AS ag_futuros
     FROM usuarios u
     LEFT JOIN agendamentos a ON a.barbeiro_id = u.id
       AND a.data_hora >= NOW() AND a.status IN ('pendente','confirmado')
     WHERE u.perfil = 'barbeiro'
     GROUP BY u.id, u.nome, u.email, u.ativo
     ORDER BY u.nome"
)->fetchAll();

// carga horária aberta
$cargaAberta = isset($_GET['carga']) ? (int)$_GET['carga'] : 0;
$cargaHoraria = [];
if ($cargaAberta) {
    $stmt = $pdo->prepare('SELECT dia_semana, hora_inicio, hora_fim FROM carga_horaria WHERE barbeiro_id=?');
    $stmt->execute([$cargaAberta]);
    foreach ($stmt->fetchAll() as $row) {
        $cargaHoraria[(int)$row['dia_semana']] = $row;
    }
    $bNome = '';
    foreach ($barbeiros as $b) {
        if ($b['id'] === $cargaAberta) { $bNome = $b['nome']; break; }
    }
}

$diasSemana = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

$titulo = 'Barbeiros';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel">

  <div class="page-head">
    <h1>Barbeiros</h1>
    <p class="sub">Gerencie a equipe</p>
  </div>

  <?php foreach ($erros as $e): ?>
    <div class="flash flash--err"><?= e($e) ?></div>
  <?php endforeach; ?>

  <div class="panel-cols">

    <!-- lista -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.3rem">Equipe</h2>
        <span class="section-head-line"></span>
      </div>

      <?php if ($barbeiros): ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Nome</th><th>Agenda</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($barbeiros as $b): ?>
              <tr>
                <td>
                  <strong><?= e($b['nome']) ?></strong><br>
                  <span style="font-size:12px; color:var(--muted)"><?= e($b['email']) ?></span>
                </td>
                <td style="font-size:13px; color:var(--muted)"><?= (int)$b['ag_futuros'] ?> futuros</td>
                <td>
                  <span class="badge <?= $b['ativo'] ? 'badge--ok' : 'badge--muted' ?>">
                    <?= $b['ativo'] ? 'Ativo' : 'Inativo' ?>
                  </span>
                </td>
                <td style="white-space:nowrap">
                  <a href="?carga=<?= (int)$b['id'] ?>" style="font-size:12px; color:var(--muted)">Carga horária</a>
                  <form method="post" style="display:inline; margin-left:8px">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="acao" value="toggle_ativo">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button type="submit" style="background:none;border:none;color:var(--muted);font-size:12px;cursor:pointer;padding:0">
                      <?= $b['ativo'] ? 'Desativar' : 'Reativar' ?>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Nenhum barbeiro cadastrado.</p>
      <?php endif; ?>

      <!-- carga horária inline -->
      <?php if ($cargaAberta && isset($bNome)): ?>
        <div class="panel-form" style="margin-top:24px; border-top:3px solid var(--accent);">
          <p class="panel-form-title">Carga horária — <?= e($bNome) ?></p>
          <form method="post">
            <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
            <input type="hidden" name="acao"          value="carga_horaria">
            <input type="hidden" name="barbeiro_id"   value="<?= $cargaAberta ?>">

            <?php foreach ($diasSemana as $num => $nome): ?>
              <?php $row = $cargaHoraria[$num] ?? null; ?>
              <div class="ch-row">
                <input type="checkbox" name="dia[<?= $num ?>]" value="1" id="dia_<?= $num ?>"
                  <?= $row ? 'checked' : '' ?>
                  onchange="toggleDia(<?= $num ?>, this.checked)">
                <label for="dia_<?= $num ?>" style="font-size:13px; color:var(--text); cursor:pointer; font-weight:400; letter-spacing:0; text-transform:none;"><?= $nome ?></label>
                <input type="time" name="inicio_<?= $num ?>" id="ini_<?= $num ?>"
                  value="<?= e($row['hora_inicio'] ?? '09:00') ?>"
                  <?= !$row ? 'disabled' : '' ?>>
                <input type="time" name="fim_<?= $num ?>" id="fim_<?= $num ?>"
                  value="<?= e($row['hora_fim'] ?? '18:00') ?>"
                  <?= !$row ? 'disabled' : '' ?>>
              </div>
            <?php endforeach; ?>

            <div class="form-actions" style="margin-top:16px">
              <button class="btn btn--sm" type="submit">Salvar carga horária</button>
              <a class="btn btn--ghost btn--sm" href="/admin/barbeiros.php">Cancelar</a>
            </div>
          </form>
          <script>
          function toggleDia(n, on) {
            ['ini_','fim_'].forEach(p => {
              const el = document.getElementById(p + n);
              if (el) el.disabled = !on;
            });
          }
          </script>
        </div>
      <?php endif; ?>
    </div>

    <!-- formulário cadastro -->
    <div>
      <div class="section-head">
        <h2 style="font-size:1.3rem">Cadastrar barbeiro</h2>
        <span class="section-head-line"></span>
      </div>
      <div class="panel-form">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="criar">
          <div class="field">
            <label>Nome</label>
            <input type="text" name="nome" required maxlength="100" autocomplete="off">
          </div>
          <div class="field">
            <label>E-mail</label>
            <input type="email" name="email" required maxlength="150" autocomplete="off">
          </div>
          <div class="field">
            <label>Senha inicial</label>
            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
            <span class="form-hint">O barbeiro deverá trocar no primeiro acesso.</span>
          </div>
          <button class="btn btn--sm" type="submit">Cadastrar</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
