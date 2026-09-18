<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

exigir_perfil('admin');

$pdo    = db();
$erros  = [];
$testeMsg = null;

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    // salvar configurações
    if ($acao === 'salvar') {
        $campos = [
            'smtp_host'       => trim($_POST['smtp_host']       ?? ''),
            'smtp_port'       => trim($_POST['smtp_port']       ?? '587'),
            'smtp_user'       => trim($_POST['smtp_user']       ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name']  ?? 'Navalha Barbearia'),
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'smtp_ativo'      => isset($_POST['smtp_ativo']) ? '1' : '0',
        ];
        // senha: só atualiza se preenchida
        $novaSenha = $_POST['smtp_pass'] ?? '';

        if ($campos['smtp_ativo'] === '1') {
            if (empty($campos['smtp_host']))       $erros[] = 'Host SMTP obrigatório.';
            if (empty($campos['smtp_user']))       $erros[] = 'Usuário SMTP obrigatório.';
            if (empty($campos['smtp_from_email'])) $erros[] = 'E-mail remetente obrigatório.';
        }

        if (!$erros) {
            $upd = $pdo->prepare("INSERT INTO config(chave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=?");
            foreach ($campos as $k => $v) {
                $upd->execute([$k, $v, $v]);
            }
            if ($novaSenha !== '') {
                $upd->execute(['smtp_pass', $novaSenha, $novaSenha]);
            }
            flash('ok', 'Configurações salvas.');
            header('Location: /admin/configuracoes.php'); exit;
        }
    }

    // enviar e-mail de teste
    if ($acao === 'testar') {
        $destino = filter_var(trim($_POST['teste_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$destino) {
            $erros[] = 'Informe um e-mail válido para o teste.';
        } else {
            // força recarregar config do banco
            $ok = enviar_email(
                $destino,
                'Teste',
                'Teste de configuração SMTP — Navalha',
                '<p>Se você recebeu este e-mail, o SMTP está configurado corretamente.</p><p style="color:#B8922A;font-weight:600;">✓ Configuração funcionando.</p>'
            );
            $testeMsg = $ok
                ? ['tipo' => 'ok',  'txt' => "E-mail de teste enviado para {$destino}."]
                : ['tipo' => 'err', 'txt' => 'Falha ao enviar. Verifique os dados e o log do servidor.'];
        }
    }
}

// ── Dados atuais ──────────────────────────────────────────────
$rows = $pdo->query("SELECT chave, valor FROM config WHERE chave LIKE 'smtp_%'")->fetchAll();
$cfg  = [];
foreach ($rows as $r) { $cfg[$r['chave']] = $r['valor']; }

$titulo = 'Configurações';
require __DIR__ . '/../../includes/header.php';
?>

<div class="panel" style="max-width:720px;">

  <div class="page-head">
    <h1>Configurações</h1>
    <p class="sub">SMTP e envio de e-mails</p>
  </div>

  <?php foreach ($erros as $err): ?>
    <div class="flash flash--err"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if ($testeMsg): ?>
    <div class="flash flash--<?= $testeMsg['tipo'] ?>"><?= e($testeMsg['txt']) ?></div>
  <?php endif; ?>

  <!-- formulário SMTP -->
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="salvar">

    <div class="panel-form" style="margin-bottom:24px;">
      <p class="panel-form-title">Servidor SMTP</p>

      <!-- ativar/desativar -->
      <div style="display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--border);margin-bottom:20px;">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:var(--text);text-transform:none;letter-spacing:0;">
          <input type="checkbox" name="smtp_ativo" value="1" <?= ($cfg['smtp_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
          Envio de e-mails ativo
        </label>
        <span style="font-size:12px;color:var(--muted);">— desative para ambientes de desenvolvimento</span>
      </div>

      <div class="form-row">
        <div class="field">
          <label>Host SMTP</label>
          <input type="text" name="smtp_host" value="<?= e($cfg['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
        </div>
        <div class="field">
          <label>Porta</label>
          <input type="number" name="smtp_port" value="<?= e($cfg['smtp_port'] ?? '587') ?>" placeholder="587">
          <span class="form-hint">587 = TLS · 465 = SSL</span>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label>Usuário (e-mail da conta)</label>
          <input type="email" name="smtp_user" value="<?= e($cfg['smtp_user'] ?? '') ?>" placeholder="seu@email.com" autocomplete="off">
        </div>
        <div class="field">
          <label>Senha</label>
          <input type="password" name="smtp_pass" placeholder="deixe em branco para manter" autocomplete="new-password">
          <span class="form-hint">Só preencha para alterar.</span>
        </div>
      </div>

      <p style="font-size:11px;font-weight:500;letter-spacing:.08em;text-transform:uppercase;color:var(--gold-pale);margin:20px 0 16px;">
        Remetente
      </p>

      <div class="form-row">
        <div class="field">
          <label>Nome do remetente</label>
          <input type="text" name="smtp_from_name" value="<?= e($cfg['smtp_from_name'] ?? 'Navalha Barbearia') ?>">
        </div>
        <div class="field">
          <label>E-mail do remetente</label>
          <input type="email" name="smtp_from_email" value="<?= e($cfg['smtp_from_email'] ?? '') ?>" placeholder="noreply@suabarbearia.com">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Salvar configurações</button>
      </div>
    </div>
  </form>

  <!-- teste de envio -->
  <div class="panel-form" style="border-left:3px solid var(--accent-hover);">
    <p class="panel-form-title">Testar envio</p>
    <form method="post" style="display:flex;gap:12px;align-items:flex-end; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="testar">
      <div class="field" style="margin:0;flex:1; min-width:200px;">
        <label>Enviar e-mail de teste para</label>
        <input type="email" name="teste_email" placeholder="seu@email.com" required>
      </div>
      <button class="btn btn--ghost btn--sm" type="submit">Enviar teste</button>
    </form>
    <p style="font-size:12px;color:var(--muted);margin-top:12px;">
      Envia um e-mail simples para confirmar que o SMTP está funcionando.
      Certifique-se de salvar as configurações antes de testar.
    </p>
  </div>

  <!-- dicas -->
  <div style="margin-top:24px;padding:20px;background:var(--surface);border:1px solid var(--border);">
    <p style="font-size:12px;font-weight:500;letter-spacing:.07em;text-transform:uppercase;color:var(--gold-pale);margin-bottom:12px;">Dicas de configuração</p>
    <table style="width:100%;font-size:13px;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Provedor</th>
          <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Host</th>
          <th style="text-align:left;padding:6px 0;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Porta</th>
        </tr>
      </thead>
      <tbody>
        <tr><td style="padding:8px 0;border-bottom:1px solid var(--border);">Gmail</td><td style="padding:8px 0;border-bottom:1px solid var(--border);color:var(--muted);">smtp.gmail.com</td><td style="padding:8px 0;border-bottom:1px solid var(--border);color:var(--muted);">587</td></tr>
        <tr><td style="padding:8px 0;border-bottom:1px solid var(--border);">Outlook / Hotmail</td><td style="padding:8px 0;border-bottom:1px solid var(--border);color:var(--muted);">smtp.office365.com</td><td style="padding:8px 0;border-bottom:1px solid var(--border);color:var(--muted);">587</td></tr>
        <tr><td style="padding:8px 0;">Mailtrap (dev)</td><td style="padding:8px 0;color:var(--muted);">sandbox.smtp.mailtrap.io</td><td style="padding:8px 0;color:var(--muted);">587</td></tr>
      </tbody>
    </table>
    <p style="font-size:12px;color:var(--muted);margin-top:12px;">
      Para Gmail, use uma <strong style="color:var(--text)">senha de app</strong> (não a senha normal da conta).
      Ative a verificação em duas etapas e gere em: Conta Google → Segurança → Senhas de app.
    </p>
  </div>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
