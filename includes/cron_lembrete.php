<?php
/**
 * cron_lembrete.php — Envia lembretes para agendamentos confirmados nas próximas 24h.
 *
 * Rodar via cron (exemplo, todo dia às 08h):
 *   0 8 * * * php /var/www/includes/cron_lembrete.php >> /var/log/lembrete.log 2>&1
 *
 * Ou manualmente dentro do container:
 *   docker compose exec app php /var/www/includes/cron_lembrete.php
 */
declare(strict_types=1);

// Só pode ser executado via CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

$pdo = db();

// Busca agendamentos confirmados nas próximas 24h que ainda não receberam lembrete
// Estratégia: agendamentos cujo data_hora está entre agora+1h e agora+25h
// (janela de 1h de margem para não enviar para quem está quase começando)
$stmt = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.token_confirm,
            cli.nome  AS cliente_nome,
            cli.email AS cliente_email,
            bar.nome  AS barbeiro_nome,
            COALESCE(s.nome, c.nome) AS item_nome,
            a.preco
     FROM agendamentos a
     JOIN usuarios cli ON cli.id = a.cliente_id
     JOIN usuarios bar ON bar.id = a.barbeiro_id
     LEFT JOIN servicos s ON s.id = a.servico_id
     LEFT JOIN combos   c ON c.id = a.combo_id
     WHERE a.status = 'confirmado'
       AND a.data_hora BETWEEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                           AND DATE_ADD(NOW(), INTERVAL 25 HOUR)"
);
$stmt->execute();
$agendamentos = $stmt->fetchAll();

if (!$agendamentos) {
    echo '[' . date('Y-m-d H:i:s') . '] Nenhum lembrete para enviar.' . PHP_EOL;
    exit(0);
}

$enviados = 0;
$falhas   = 0;
$url      = env('APP_URL') ?: 'http://localhost:8080';

foreach ($agendamentos as $ag) {
    $dt       = new DateTimeImmutable($ag['data_hora']);
    $linkCanc = $url . '/cliente/cancelar.php?token=' . urlencode($ag['token_confirm']);

    $corpo = "
        <p>Olá, <strong style='color:#E8DCC8'>{$ag['cliente_nome']}</strong>.</p>
        <p>Este é um lembrete do seu agendamento de <strong>{$ag['item_nome']}</strong>
           com <strong>{$ag['barbeiro_nome']}</strong>.</p>
        <p style='margin:20px 0; font-size:1.2rem; color:#B8922A; font-weight:600;'>
          {$dt->format('d/m/Y')} às {$dt->format('H:i')}
        </p>
        <p style='color:#9A8A72; font-size:13px;'>
          Precisa cancelar? <a href='{$linkCanc}' style='color:#9A8A72;'>Clique aqui</a>
          (cancele com pelo menos 2h de antecedência).
        </p>
    ";

    $ok = enviar_email(
        $ag['cliente_email'],
        $ag['cliente_nome'],
        'Lembrete: ' . $ag['item_nome'] . ' amanhã às ' . $dt->format('H:i'),
        $corpo
    );

    if ($ok) {
        $enviados++;
        echo '[' . date('Y-m-d H:i:s') . '] Lembrete enviado para ' . $ag['cliente_email']
             . ' — agendamento #' . $ag['id'] . PHP_EOL;
    } else {
        $falhas++;
        echo '[' . date('Y-m-d H:i:s') . '] FALHA ao enviar para ' . $ag['cliente_email']
             . ' — agendamento #' . $ag['id'] . PHP_EOL;
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Concluído: {$enviados} enviados, {$falhas} falhas." . PHP_EOL;
exit($falhas > 0 ? 1 : 0);
