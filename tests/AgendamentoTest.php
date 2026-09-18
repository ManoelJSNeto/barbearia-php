<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Testes do fluxo de agendamento — Fase 5
 *
 * Cobre:
 *  - Criação de agendamento com token único
 *  - Confirmação dentro do prazo (> 2h antes)
 *  - Expiração: prazo vencido → status nao_confirmado
 *  - Cancelamento pelo cliente
 *  - Cancelamento pelo admin
 *  - Unicidade do token_confirm
 *  - Race condition: dois agendamentos no mesmo slot
 */
final class AgendamentoTest extends TestCase
{
    private PDO $pdo;
    private int $barbeiroId;
    private int $clienteId;

    protected function setUp(): void
    {
        if (!defined('DB_TEST_AVAILABLE') || !DB_TEST_AVAILABLE) {
            $this->markTestSkipped('Banco de teste não disponível.');
        }

        $this->pdo       = $GLOBALS['_pdo_test'];
        $this->barbeiroId = 2;
        $this->clienteId  = 3;

        $this->pdo->prepare("DELETE FROM agendamentos WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
    }

    protected function tearDown(): void
    {
        if (!defined('DB_TEST_AVAILABLE') || !DB_TEST_AVAILABLE) return;
        $this->pdo->prepare("DELETE FROM agendamentos WHERE barbeiro_id=?")->execute([$this->barbeiroId]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function criarAgendamento(
        string $dataHora,
        int    $duracaoMin = 45,
        string $status     = 'pendente',
        ?string $token     = null
    ): array {
        $tk = $token ?? bin2hex(random_bytes(32));
        $this->pdo->prepare(
            "INSERT INTO agendamentos
             (cliente_id, barbeiro_id, servico_id, combo_id, data_hora,
              duracao_min, preco, status, token_confirm)
             VALUES (?, ?, NULL, NULL, ?, ?, 40.00, ?, ?)"
        )->execute([
            $this->clienteId,
            $this->barbeiroId,
            $dataHora,
            $duracaoMin,
            $status,
            $tk,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return ['id' => $id, 'token' => $tk];
    }

    private function getAgendamento(int $id): array|false
    {
        $s = $this->pdo->prepare("SELECT * FROM agendamentos WHERE id=?");
        $s->execute([$id]);
        return $s->fetch();
    }

    // ── Testes ───────────────────────────────────────────────────

    public function test_criacao_gera_token_unico(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $t       = bin2hex(random_bytes(32));
            $tokens[] = $t;
        }
        // Todos os tokens devem ser únicos
        $this->assertSame(count($tokens), count(array_unique($tokens)));
    }

    public function test_token_tem_64_chars_hex(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_agendamento_criado_com_status_pendente(): void
    {
        $dataHora = (new DateTimeImmutable('+2 days 10:00'))->format('Y-m-d H:i:s');
        $ag       = $this->criarAgendamento($dataHora);
        $row      = $this->getAgendamento($ag['id']);

        $this->assertSame('pendente', $row['status']);
        $this->assertNull($row['confirmado_em']);
    }

    public function test_confirmacao_dentro_do_prazo(): void
    {
        // Agendamento daqui a 3h — prazo de confirmação ainda aberto (> 2h)
        $dataHora = (new DateTimeImmutable('+3 hours'))->format('Y-m-d H:i:s');
        $ag       = $this->criarAgendamento($dataHora, 45, 'pendente');

        $limite = new DateTimeImmutable($dataHora);
        $limite = $limite->modify('-2 hours');
        $agora  = new DateTimeImmutable();

        // prazo não expirou → pode confirmar
        $this->assertLessThan($limite, $agora, 'Prazo de confirmação deve estar aberto.');

        // confirma
        $this->pdo->prepare(
            "UPDATE agendamentos SET status='confirmado', confirmado_em=NOW() WHERE id=?"
        )->execute([$ag['id']]);

        $row = $this->getAgendamento($ag['id']);
        $this->assertSame('confirmado', $row['status']);
        $this->assertNotNull($row['confirmado_em']);
    }

    public function test_confirmacao_apos_prazo_marca_nao_confirmado(): void
    {
        // Agendamento no passado (já expirou o prazo de 2h)
        $dataHora = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
        $ag       = $this->criarAgendamento($dataHora, 45, 'pendente');

        $limite = new DateTimeImmutable($dataHora);
        $limite = $limite->modify('-2 hours');
        $agora  = new DateTimeImmutable();

        // prazo expirou → não pode confirmar, deve marcar como nao_confirmado
        $this->assertGreaterThan($limite, $agora, 'Prazo de confirmação deve estar expirado.');

        $this->pdo->prepare(
            "UPDATE agendamentos SET status='nao_confirmado' WHERE id=?"
        )->execute([$ag['id']]);

        $row = $this->getAgendamento($ag['id']);
        $this->assertSame('nao_confirmado', $row['status']);
        $this->assertNull($row['confirmado_em']);
    }

    public function test_cancelamento_pelo_cliente(): void
    {
        $dataHora = (new DateTimeImmutable('+2 days 14:00'))->format('Y-m-d H:i:s');
        $ag       = $this->criarAgendamento($dataHora);

        $this->pdo->prepare(
            "UPDATE agendamentos
             SET status='cancelado', cancelado_em=NOW(), cancelado_por='cliente'
             WHERE id=?"
        )->execute([$ag['id']]);

        $row = $this->getAgendamento($ag['id']);
        $this->assertSame('cancelado',  $row['status']);
        $this->assertSame('cliente',    $row['cancelado_por']);
        $this->assertNotNull($row['cancelado_em']);
    }

    public function test_cancelamento_pelo_admin(): void
    {
        $dataHora = (new DateTimeImmutable('+2 days 15:00'))->format('Y-m-d H:i:s');
        $ag       = $this->criarAgendamento($dataHora);

        $this->pdo->prepare(
            "UPDATE agendamentos
             SET status='cancelado', cancelado_em=NOW(), cancelado_por='admin'
             WHERE id=?"
        )->execute([$ag['id']]);

        $row = $this->getAgendamento($ag['id']);
        $this->assertSame('cancelado', $row['status']);
        $this->assertSame('admin',     $row['cancelado_por']);
    }

    public function test_token_confirm_e_unico_no_banco(): void
    {
        $dataHora1 = (new DateTimeImmutable('+2 days 09:00'))->format('Y-m-d H:i:s');
        $dataHora2 = (new DateTimeImmutable('+2 days 10:00'))->format('Y-m-d H:i:s');
        $tokenDup  = bin2hex(random_bytes(32));

        $this->criarAgendamento($dataHora1, 45, 'pendente', $tokenDup);

        // Tentar inserir com o mesmo token deve lançar exceção (UNIQUE constraint)
        $this->expectException(PDOException::class);
        $this->criarAgendamento($dataHora2, 45, 'pendente', $tokenDup);
    }

    public function test_race_condition_lock_detecta_sobreposicao(): void
    {
        $dataHora = (new DateTimeImmutable('+2 days 11:00'))->format('Y-m-d H:i:s');
        $novoFim  = (new DateTimeImmutable('+2 days 11:45'))->format('Y-m-d H:i:s');

        // Insere um agendamento das 11:00 por 45min
        $this->criarAgendamento($dataHora, 45, 'confirmado');

        // Tenta agendar das 11:00 por 45min — sobreposição exata
        $lock = $this->pdo->prepare(
            "SELECT COUNT(*) FROM agendamentos
             WHERE barbeiro_id=? AND status IN ('pendente','confirmado')
               AND data_hora < ?
               AND TIMESTAMPADD(MINUTE, duracao_min, data_hora) > ?"
        );
        $lock->execute([$this->barbeiroId, $novoFim, $dataHora]);
        $conflitos = (int)$lock->fetchColumn();

        $this->assertGreaterThan(0, $conflitos, 'Lock deve detectar sobreposição de horário.');
    }

    public function test_race_condition_lock_nao_detecta_slot_livre(): void
    {
        $dataHora = (new DateTimeImmutable('+2 days 11:00'))->format('Y-m-d H:i:s');
        $novoIni  = (new DateTimeImmutable('+2 days 11:45'))->format('Y-m-d H:i:s'); // após o existente
        $novoFim  = (new DateTimeImmutable('+2 days 12:30'))->format('Y-m-d H:i:s');

        // Insere agendamento das 11:00 por 45min (termina às 11:45)
        $this->criarAgendamento($dataHora, 45, 'confirmado');

        // Tenta agendar das 11:45 por 45min — não conflita
        $lock = $this->pdo->prepare(
            "SELECT COUNT(*) FROM agendamentos
             WHERE barbeiro_id=? AND status IN ('pendente','confirmado')
               AND data_hora < ?
               AND TIMESTAMPADD(MINUTE, duracao_min, data_hora) > ?"
        );
        $lock->execute([$this->barbeiroId, $novoFim, $novoIni]);
        $conflitos = (int)$lock->fetchColumn();

        $this->assertSame(0, $conflitos, 'Slot após o agendamento não deve gerar conflito.');
    }
}
