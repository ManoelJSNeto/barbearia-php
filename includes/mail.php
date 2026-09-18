<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * Carrega as configurações SMTP salvas no banco.
 */
function smtp_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $rows = db()->query("SELECT chave, valor FROM config WHERE chave LIKE 'smtp_%'")->fetchAll();
    $cfg  = [];
    foreach ($rows as $r) {
        $cfg[$r['chave']] = $r['valor'];
    }
    return $cfg;
}

/**
 * Envia um e-mail usando PHPMailer com as configurações do painel.
 *
 * @param string       $para       E-mail do destinatário
 * @param string       $nomePara   Nome do destinatário
 * @param string       $assunto    Assunto
 * @param string       $corpo      Corpo HTML
 * @param string|null  $textoPlano Fallback texto puro (opcional)
 * @return bool  true em sucesso, false em falha (erro logado)
 */
function enviar_email(
    string $para,
    string $nomePara,
    string $assunto,
    string $corpo,
    ?string $textoPlano = null
): bool {
    $cfg = smtp_config();

    if (empty($cfg['smtp_ativo']) || $cfg['smtp_ativo'] !== '1') {
        error_log("[mail] SMTP desativado — e-mail para {$para} não enviado.");
        return false;
    }
    if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) {
        error_log("[mail] SMTP não configurado — e-mail para {$para} não enviado.");
        return false;
    }

    // Autoload do Composer
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log("[mail] vendor/autoload.php não encontrado. Rode 'composer install'.");
        return false;
    }
    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host        = $cfg['smtp_host'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $cfg['smtp_user'];
        $mail->Password    = $cfg['smtp_pass'];
        $mail->SMTPSecure  = (int)($cfg['smtp_port'] ?? 587) === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = (int)($cfg['smtp_port'] ?? 587);
        $mail->CharSet     = 'UTF-8';

        $fromEmail = $cfg['smtp_from_email'] ?: $cfg['smtp_user'];
        $fromName  = $cfg['smtp_from_name']  ?: 'Navalha Barbearia';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($para, $nomePara);
        $mail->addReplyTo($fromEmail, $fromName);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = _mail_layout($assunto, $corpo);
        $mail->AltBody = $textoPlano ?? strip_tags($corpo);

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[mail] Erro ao enviar para {$para}: " . $e->getMessage());
        return false;
    }
}

/**
 * Envolve o corpo do e-mail num layout HTML minimalista na identidade visual.
 */
function _mail_layout(string $titulo, string $corpo): string
{
    return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$titulo}</title>
</head>
<body style="margin:0;padding:0;background:#1A1208;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#1A1208;padding:40px 20px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">

        <!-- header -->
        <tr>
          <td style="padding:0 0 24px 0;border-bottom:1px solid #3D2B0F;">
            <span style="font-family:Georgia,serif;font-size:1.3rem;font-weight:700;color:#E8DCC8;letter-spacing:.01em;">
              Navalha<span style="display:inline-block;width:5px;height:5px;background:#B8922A;margin-left:4px;vertical-align:middle;"></span>
            </span>
          </td>
        </tr>

        <!-- body -->
        <tr>
          <td style="padding:32px 0;color:#E8DCC8;font-size:15px;line-height:1.7;">
            {$corpo}
          </td>
        </tr>

        <!-- footer -->
        <tr>
          <td style="padding:24px 0 0 0;border-top:1px solid #3D2B0F;font-size:12px;color:#9A8A72;">
            Navalha Barbearia &mdash; Atendimento com hora marcada.<br>
            Este e-mail foi gerado automaticamente, não responda.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Templates prontos
 */
function mail_agendamento_criado(array $ag, string $tokenConfirm): bool
{
    $data    = (new DateTimeImmutable($ag['data_hora']))->format('d/m/Y \à\s H:i');
    $url     = (env('APP_URL') ?: 'http://localhost:8080');
    $linkConf = $url . '/confirmar.php?token=' . urlencode($tokenConfirm);
    $linkCanc = $url . '/cliente/cancelar.php?token=' . urlencode($tokenConfirm);

    $corpo = "
        <p>Olá, <strong style='color:#E8DCC8'>{$ag['cliente_nome']}</strong>.</p>
        <p>Seu agendamento foi criado com sucesso.</p>
        <table cellpadding='0' cellspacing='0' style='width:100%;margin:24px 0;border:1px solid #3D2B0F;'>
          <tr><td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;color:#9A8A72;font-size:13px;width:120px'>Serviço</td>
              <td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;'>{$ag['item_nome']}</td></tr>
          <tr><td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;color:#9A8A72;font-size:13px;'>Barbeiro</td>
              <td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;'>{$ag['barbeiro_nome']}</td></tr>
          <tr><td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;color:#9A8A72;font-size:13px;'>Data</td>
              <td style='padding:12px 16px;border-bottom:1px solid #3D2B0F;'>{$data}</td></tr>
          <tr><td style='padding:12px 16px;color:#9A8A72;font-size:13px;'>Valor</td>
              <td style='padding:12px 16px;color:#B8922A;font-weight:600;'>R\$ " . number_format((float)$ag['preco'], 2, ',', '.') . "</td></tr>
        </table>
        <p style='margin-bottom:8px;'>Confirme sua presença clicando no botão abaixo (até 2h antes do horário):</p>
        <p>
          <a href='{$linkConf}' style='display:inline-block;background:#B8922A;color:#1A1208;padding:12px 24px;font-weight:600;text-decoration:none;font-size:14px;'>Confirmar presença</a>
        </p>
        <p style='margin-top:16px;font-size:13px;color:#9A8A72;'>
          Precisa cancelar? <a href='{$linkCanc}' style='color:#9A8A72;'>Clique aqui</a>.
        </p>
    ";

    return enviar_email(
        $ag['cliente_email'],
        $ag['cliente_nome'],
        'Agendamento criado — ' . $data,
        $corpo
    );
}

function mail_confirmacao(array $ag): bool
{
    $data  = (new DateTimeImmutable($ag['data_hora']))->format('d/m/Y \à\s H:i');
    $corpo = "
        <p>Olá, <strong style='color:#E8DCC8'>{$ag['cliente_nome']}</strong>.</p>
        <p>Sua presença foi <strong style='color:#4A7C59'>confirmada</strong>.</p>
        <p style='margin:16px 0;'>
          <strong>{$ag['item_nome']}</strong> com {$ag['barbeiro_nome']}<br>
          <span style='color:#B8922A;font-size:1.1rem;'>{$data}</span>
        </p>
        <p style='color:#9A8A72;font-size:13px;'>Te esperamos no horário marcado. Até lá!</p>
    ";
    return enviar_email($ag['cliente_email'], $ag['cliente_nome'], 'Presença confirmada — ' . $data, $corpo);
}

function mail_cancelamento(array $ag, string $canceladoPor): bool
{
    $data  = (new DateTimeImmutable($ag['data_hora']))->format('d/m/Y \à\s H:i');
    $quem  = match($canceladoPor) { 'admin' => 'pela barbearia', 'barbeiro' => 'pelo barbeiro', default => 'por você' };
    $url   = (env('APP_URL') ?: 'http://localhost:8080');
    $corpo = "
        <p>Olá, <strong style='color:#E8DCC8'>{$ag['cliente_nome']}</strong>.</p>
        <p>Seu agendamento foi <strong style='color:#7C3A3A'>cancelado</strong> {$quem}.</p>
        <p style='margin:16px 0;color:#9A8A72;'>
          {$ag['item_nome']} com {$ag['barbeiro_nome']} — {$data}
        </p>
        <p><a href='{$url}/agendar.php' style='display:inline-block;background:#B8922A;color:#1A1208;padding:12px 24px;font-weight:600;text-decoration:none;font-size:14px;'>Fazer novo agendamento</a></p>
    ";
    return enviar_email($ag['cliente_email'], $ag['cliente_nome'], 'Agendamento cancelado', $corpo);
}

function mail_recuperar_senha(string $email, string $nome, string $token): bool
{
    $url  = (env('APP_URL') ?: 'http://localhost:8080');
    $link = $url . '/resetar-senha.php?token=' . urlencode($token);
    $corpo = "
        <p>Olá, <strong style='color:#E8DCC8'>{$nome}</strong>.</p>
        <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
        <p style='margin:24px 0;'>
          <a href='{$link}' style='display:inline-block;background:#B8922A;color:#1A1208;padding:12px 24px;font-weight:600;text-decoration:none;font-size:14px;'>Redefinir senha</a>
        </p>
        <p style='color:#9A8A72;font-size:13px;'>
          Este link expira em 1 hora. Se você não solicitou a redefinição, ignore este e-mail.
        </p>
    ";
    return enviar_email($email, $nome, 'Redefinição de senha', $corpo);
}
