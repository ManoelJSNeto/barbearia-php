<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * Retorna os horários disponíveis para um barbeiro em uma data.
 *
 * Regras:
 * - Só datas de hoje até hoje+4 dias úteis
 * - Respeita carga horária semanal do barbeiro
 * - Remove slots cobertos por bloqueios
 * - Remove slots cobertos por agendamentos pendentes/confirmados
 * - Remove slots que já passaram (horário passado hoje)
 * - Intervalo fixo de 15 minutos entre slots
 */
function getSlots(int $barbeiroId, string $data, int $duracaoMin): array
{
    if ($duracaoMin < 1) return [];

    $hoje = new DateTimeImmutable('today');
    $dia  = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    if (!$dia) return [];
    if ($dia < $hoje || $dia > $hoje->modify('+4 days')) return [];

    $pdo = db();

    // carga horária do barbeiro para o dia da semana (0=dom … 6=sáb)
    $stmt = $pdo->prepare(
        'SELECT hora_inicio, hora_fim FROM carga_horaria
         WHERE barbeiro_id = ? AND dia_semana = ?'
    );
    $stmt->execute([$barbeiroId, (int)$dia->format('w')]);
    $jornada = $stmt->fetch();

    if (!$jornada) return [];

    // bloqueios do dia
    $stmt = $pdo->prepare(
        'SELECT hora_inicio, hora_fim FROM bloqueios
         WHERE barbeiro_id = ? AND data = ?'
    );
    $stmt->execute([$barbeiroId, $data]);
    $bloqueios = $stmt->fetchAll();

    // agendamentos confirmados/pendentes do dia
    $stmt = $pdo->prepare(
        "SELECT data_hora, duracao_min FROM agendamentos
         WHERE barbeiro_id = ? AND DATE(data_hora) = ?
           AND status IN ('pendente','confirmado')"
    );
    $stmt->execute([$barbeiroId, $data]);
    $ocupados = $stmt->fetchAll();

    $agora   = new DateTimeImmutable();
    $inicio  = new DateTimeImmutable($data . ' ' . $jornada['hora_inicio']);
    $fim     = new DateTimeImmutable($data . ' ' . $jornada['hora_fim']);
    $passo   = 15; // intervalo em minutos entre slots

    $resultado = [];

    for ($slot = $inicio; $slot->modify("+{$duracaoMin} minutes") <= $fim; $slot = $slot->modify("+{$passo} minutes")) {

        // descarta horários já passados
        if ($slot <= $agora) continue;

        $slotFim  = $slot->modify("+{$duracaoMin} minutes");
        $conflito = false;

        // verifica bloqueios
        foreach ($bloqueios as $b) {
            $bIni = new DateTimeImmutable($data . ' ' . $b['hora_inicio']);
            $bFim = new DateTimeImmutable($data . ' ' . $b['hora_fim']);
            if ($slot < $bFim && $slotFim > $bIni) { $conflito = true; break; }
        }

        if ($conflito) continue;

        // verifica agendamentos existentes
        foreach ($ocupados as $o) {
            $oIni = new DateTimeImmutable($o['data_hora']);
            $oFim = $oIni->modify('+' . (int)$o['duracao_min'] . ' minutes');
            if ($slot < $oFim && $slotFim > $oIni) { $conflito = true; break; }
        }

        if (!$conflito) {
            $resultado[] = $slot->format('H:i');
        }
    }

    return $resultado;
}
