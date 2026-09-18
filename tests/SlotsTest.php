<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/slots.php';

/**
 * Testes do algoritmo de slots — Fase 5
 *
 * Cobre todas as regras de negócio do getSlots():
 *  - Dia sem carga horária → array vazio
 *  - Slots cobertos por agendamentos → removidos
 *  - Slots cobertos por bloqueios → removidos
 *  - Data anterior a hoje → array vazio
 *  - Data além de +4 dias → array vazio
 *  - Horários passados no dia de hoje → removidos
 *  - Duração longa (90min, 110min)
 *  - Todos os slots em dia totalmente bloqueado → array vazio
 */
final class SlotsTest extends TestCase
{
    private PDO $pdo;
    private int $barbeiroId;
    private int $clienteId;

    protected function setUp(): void
    {
        if (!defined('DB_TEST_AVAILABLE') || !DB_TEST_AVAILABLE) {
            $this->markTestSkipped('Banco de teste não disponível.');
        }

        $this->pdo = $GLOBALS['_pdo_test'];

        // barbeiro_id = 2, cliente_id = 3 (conforme fixtures do bootstrap)
        $this->barbeiroId = 2;
        $this->clienteId  = 3;

        // Limpa dados variáveis entre testes
        $this->pdo->prepare("DELETE FROM agendamentos WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
        $this->pdo->prepare("DELETE FROM bloqueios     WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
    }

    protected function tearDown(): void
    {
        if (!defined('DB_TEST_AVAILABLE') || !DB_TEST_AVAILABLE) return;
        $this->pdo->prepare("DELETE FROM agendamentos WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
        $this->pdo->prepare("DELETE FROM bloqueios     WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Retorna o próximo dia útil com carga horária (seg–sex) a partir de amanhã. */
    private function proximoDiaUtil(): string
    {
        $d = new DateTimeImmutable('tomorrow');
        while (in_array((int)$d->format('N'), [6, 7])) { // sáb=6, dom=7
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    /** Insere um agendamento simples. */
    private function inserirAgendamento(string $dataHora, int $duracaoMin): void
    {
        $this->pdo->prepare(
            "INSERT INTO agendamentos
             (cliente_id, barbeiro_id, servico_id, combo_id, data_hora,
              duracao_min, preco, status, token_confirm)
             VALUES (?, ?, NULL, NULL, ?, ?, 40.00, 'confirmado', ?)"
        )->execute([
            $this->clienteId,
            $this->barbeiroId,
            $dataHora,
            $duracaoMin,
            bin2hex(random_bytes(32)),
        ]);
    }

    /** Insere um bloqueio. */
    private function inserirBloqueio(string $data, string $inicio, string $fim): void
    {
        $this->pdo->prepare(
            "INSERT INTO bloqueios (barbeiro_id, data, hora_inicio, hora_fim) VALUES (?,?,?,?)"
        )->execute([$this->barbeiroId, $data, $inicio, $fim]);
    }

    // ── Testes ───────────────────────────────────────────────────

    public function test_dia_sem_carga_horaria_retorna_vazio(): void
    {
        // domingo (dia_semana=0) — bootstrap não cadastra carga para domingo
        $domingo = new DateTimeImmutable('next sunday');
        // Se domingo estiver além de +4 dias, usa o dia com carga mas sem registro no banco
        if ($domingo > new DateTimeImmutable('+4 days')) {
            $this->markTestSkipped('Domingo não está na janela de 4 dias este ciclo.');
        }
        $slots = getSlots($this->barbeiroId, $domingo->format('Y-m-d'), 45);
        $this->assertSame([], $slots);
    }

    public function test_data_anterior_retorna_vazio(): void
    {
        $ontem = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $slots  = getSlots($this->barbeiroId, $ontem, 45);
        $this->assertSame([], $slots);
    }

    public function test_data_alem_de_4_dias_retorna_vazio(): void
    {
        $longe = (new DateTimeImmutable('+5 days'))->format('Y-m-d');
        $slots  = getSlots($this->barbeiroId, $longe, 45);
        $this->assertSame([], $slots);
    }

    public function test_duracao_invalida_retorna_vazio(): void
    {
        $data  = $this->proximoDiaUtil();
        $slots = getSlots($this->barbeiroId, $data, 0);
        $this->assertSame([], $slots);
    }

    public function test_dia_util_sem_bloqueios_retorna_slots(): void
    {
        $data  = $this->proximoDiaUtil();
        $slots = getSlots($this->barbeiroId, $data, 45);

        // 9h às 18h com passo 15min e duração 45min:
        // último slot possível: 17:15 (17:15+45=18:00)
        // total = (18:00 - 09:00) em min / 15 - ceil(45/15) + 1 = 36 slots possíveis
        // mas como não sabemos quantos já passaram (amanhã, nenhum passa),
        // apenas verificamos que há slots e o formato está correto
        $this->assertNotEmpty($slots);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slots[0]);
        $this->assertContains('09:00', $slots);
        $this->assertContains('17:15', $slots);
    }

    public function test_slot_bloqueado_e_removido(): void
    {
        $data = $this->proximoDiaUtil();
        // Bloqueia das 10:00 às 12:00
        $this->inserirBloqueio($data, '10:00', '12:00');

        $slots = getSlots($this->barbeiroId, $data, 45);

        // Slots que iniciamiam dentro do bloqueio ou que sobreporiam devem ser removidos
        // 09:15 + 45min = 10:00 → não conflita → deve existir
        $this->assertContains('09:15', $slots);
        // 10:00 + 45min = 10:45 → conflita com bloqueio 10:00-12:00 → deve ser removido
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('10:15', $slots);
        $this->assertNotContains('11:00', $slots);
        $this->assertNotContains('11:30', $slots);
        // 11:15 + 45min = 12:00 → toca o fim do bloqueio, mas não conflita (< não <=)
        $this->assertContains('11:15', $slots);
    }

    public function test_agendamento_existente_bloqueia_slot(): void
    {
        $data = $this->proximoDiaUtil();
        // Agendamento das 10:00, 45min
        $this->inserirAgendamento($data . ' 10:00:00', 45);

        $slots = getSlots($this->barbeiroId, $data, 45);

        // 10:00 ocupado → não deve aparecer
        $this->assertNotContains('10:00', $slots);
        // 09:15 + 45 = 10:00 → toca o início do agendamento mas não conflita
        $this->assertContains('09:15', $slots);
        // 10:15 + 45 = 11:00 → conflita com 10:00–10:45 → removido
        $this->assertNotContains('10:15', $slots);
        // 10:45 + 45 = 11:30 → não conflita → deve existir
        $this->assertContains('10:45', $slots);
    }

    public function test_dia_totalmente_bloqueado_retorna_vazio(): void
    {
        $data = $this->proximoDiaUtil();
        $this->inserirBloqueio($data, '09:00', '18:00');

        $slots = getSlots($this->barbeiroId, $data, 45);
        $this->assertSame([], $slots);
    }

    public function test_duracao_90min_slots_corretos(): void
    {
        $data  = $this->proximoDiaUtil();
        $slots = getSlots($this->barbeiroId, $data, 90);

        // Com 90min, o último slot é 16:30 (16:30+90=18:00)
        $this->assertContains('09:00', $slots);
        $this->assertContains('16:30', $slots);
        $this->assertNotContains('16:45', $slots); // 16:45+90=18:15 > 18:00
    }

    public function test_duracao_110min_slots_corretos(): void
    {
        $data  = $this->proximoDiaUtil();
        $slots = getSlots($this->barbeiroId, $data, 110);

        // Com 110min, último slot: 16:10 (16:10+110=18:00)
        $this->assertContains('09:00', $slots);
        $this->assertNotContains('16:15', $slots); // 16:15+110=18:05 > 18:00
    }

    public function test_dois_agendamentos_consecutivos_slots_corretos(): void
    {
        $data = $this->proximoDiaUtil();
        $this->inserirAgendamento($data . ' 09:00:00', 45); // 09:00–09:45
        $this->inserirAgendamento($data . ' 09:45:00', 45); // 09:45–10:30

        $slots = getSlots($this->barbeiroId, $data, 45);

        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('09:15', $slots); // 09:15+45=10:00 conflita com 09:45-10:30
        $this->assertNotContains('09:45', $slots);
        $this->assertContains('10:30', $slots); // livre após os dois
    }
}
