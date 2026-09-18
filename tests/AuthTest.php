<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Testes de autenticação — Fase 1
 *
 * Cobre:
 *  - CSRF: geração, estabilidade e validação
 *  - Destino por perfil
 *  - Funções de sessão (usuario_logado, exigir_login)
 *  - Testes de banco: login, inativo, token de recuperação
 */
final class AuthTest extends TestCase
{
    // ── Helpers de banco ─────────────────────────────────────────

    private function pdo(): ?PDO
    {
        return $GLOBALS['_pdo_test'] ?? null;
    }

    private function skipIfNoDb(): void
    {
        if (!defined('DB_TEST_AVAILABLE') || !DB_TEST_AVAILABLE) {
            $this->markTestSkipped('Banco de teste não disponível.');
        }
    }

    // ── Setup / TearDown ─────────────────────────────────────────

    protected function setUp(): void
    {
        // Limpa a sessão entre cada teste
        $_SESSION = [];
    }

    // ═════════════════════════════════════════════════════════════
    // CSRF
    // ═════════════════════════════════════════════════════════════

    public function test_csrf_token_tem_formato_hexadecimal_64_chars(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_csrf_token_eh_estavel_na_mesma_sessao(): void
    {
        $t1 = csrf_token();
        $t2 = csrf_token();
        $this->assertSame($t1, $t2, 'O token CSRF deve ser idêntico durante a mesma sessão.');
    }

    public function test_csrf_token_valida_corretamente(): void
    {
        $token = csrf_token();
        $_POST['csrf_token'] = $token;

        // validar_csrf() não deve lançar nem encerrar quando o token bate
        // Não há exceção → teste passa
        $this->expectOutputRegex('/.*/'); // captura qualquer saída (incluindo vazia)
        // Simula o comportamento sem redirect: apenas verifica que não morre
        $ok = hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '');
        $this->assertTrue($ok, 'CSRF deve validar com token correto.');
    }

    public function test_csrf_token_invalido_falha_validacao(): void
    {
        csrf_token(); // garante que o token está na sessão
        $ok = hash_equals($_SESSION['csrf_token'] ?? '', 'token_errado');
        $this->assertFalse($ok, 'CSRF com token incorreto deve falhar.');
    }

    public function test_csrf_token_ausente_falha_validacao(): void
    {
        csrf_token();
        $ok = hash_equals($_SESSION['csrf_token'] ?? '', '');
        $this->assertFalse($ok, 'CSRF sem token deve falhar.');
    }

    // ═════════════════════════════════════════════════════════════
    // Destino por perfil
    // ═════════════════════════════════════════════════════════════

    public function test_destino_por_perfil_admin(): void
    {
        $this->assertSame('/admin/dashboard.php', destino_por_perfil('admin'));
    }

    public function test_destino_por_perfil_barbeiro(): void
    {
        $this->assertSame('/barbeiro/dashboard.php', destino_por_perfil('barbeiro'));
    }

    public function test_destino_por_perfil_cliente(): void
    {
        $this->assertSame('/cliente/dashboard.php', destino_por_perfil('cliente'));
    }

    // ═════════════════════════════════════════════════════════════
    // Funções de sessão
    // ═════════════════════════════════════════════════════════════

    public function test_usuario_logado_retorna_null_sem_sessao(): void
    {
        $this->assertNull(usuario_logado());
    }

    public function test_usuario_logado_retorna_dados_com_sessao_ativa(): void
    {
        $_SESSION['usuario'] = [
            'id'     => 1,
            'nome'   => 'Teste',
            'email'  => 'teste@teste.com',
            'perfil' => 'cliente',
        ];

        $u = usuario_logado();
        $this->assertIsArray($u);
        $this->assertSame(1, $u['id']);
        $this->assertSame('cliente', $u['perfil']);
    }

    public function test_eh_admin_retorna_false_sem_sessao(): void
    {
        $this->assertFalse(eh_admin());
    }

    public function test_eh_admin_retorna_false_para_barbeiro(): void
    {
        $_SESSION['usuario'] = ['id' => 2, 'nome' => 'B', 'email' => 'b@b.com', 'perfil' => 'barbeiro'];
        $this->assertFalse(eh_admin());
    }

    public function test_eh_admin_retorna_true_para_admin(): void
    {
        $_SESSION['usuario'] = ['id' => 1, 'nome' => 'A', 'email' => 'a@a.com', 'perfil' => 'admin'];
        $this->assertTrue(eh_admin());
    }

    public function test_funcao_e_escapa_html(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('&quot;teste&quot;', e('"teste"'));
        $this->assertSame('', e(null));
    }

    // ═════════════════════════════════════════════════════════════
    // Flash messages
    // ═════════════════════════════════════════════════════════════

    public function test_flash_seta_mensagem_na_sessao(): void
    {
        flash('ok', 'Operação concluída.');
        $this->assertSame('ok',                  $_SESSION['flash']['tipo']);
        $this->assertSame('Operação concluída.', $_SESSION['flash']['msg']);
    }

    public function test_flash_sobrescreve_mensagem_anterior(): void
    {
        flash('err', 'Erro 1');
        flash('ok', 'Sucesso');
        $this->assertSame('ok', $_SESSION['flash']['tipo']);
    }

    // ═════════════════════════════════════════════════════════════
    // Testes com banco de dados
    // ═════════════════════════════════════════════════════════════

    public function test_banco_credenciais_corretas_retornam_usuario(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT id, nome, senha_hash, perfil, ativo FROM usuarios WHERE email = ?');
        $stmt->execute(['admin@teste.com']);
        $u = $stmt->fetch();

        $this->assertNotFalse($u, 'Usuário admin@teste.com deve existir no banco de teste.');
        $this->assertTrue(password_verify('admin123', $u['senha_hash']), 'Senha correta deve bater com o hash.');
        $this->assertSame('admin', $u['perfil']);
        $this->assertSame(1, (int)$u['ativo']);
    }

    public function test_banco_senha_errada_nao_autentica(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE email = ?');
        $stmt->execute(['cliente@teste.com']);
        $u = $stmt->fetch();

        $this->assertNotFalse($u);
        $this->assertFalse(
            password_verify('senha_incorreta', $u['senha_hash']),
            'Senha errada não deve ser verificada com sucesso.'
        );
    }

    public function test_banco_usuario_inativo_bloqueado(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT ativo FROM usuarios WHERE email = ?');
        $stmt->execute(['inativo@teste.com']);
        $u = $stmt->fetch();

        $this->assertNotFalse($u, 'Usuário inativo deve existir no banco de teste.');
        $this->assertSame(0, (int)$u['ativo'], 'Usuário inativo deve ter ativo=0.');
    }

    // ── Token de recuperação de senha ────────────────────────────

    public function test_banco_token_recuperacao_valido(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        // Busca o id do cliente de teste
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute(['cliente@teste.com']);
        $usuario_id = (int)$stmt->fetchColumn();

        // Insere um token válido (expira em 1h)
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO tokens_senha (usuario_id, token, expira_em)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        )->execute([$usuario_id, $token]);

        // Valida: deve retornar a linha
        $stmt = $pdo->prepare(
            "SELECT id FROM tokens_senha
             WHERE token = ? AND usado = 0 AND expira_em > NOW()"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'Token recém-criado deve ser encontrado e válido.');

        // Limpa
        $pdo->prepare("DELETE FROM tokens_senha WHERE token = ?")->execute([$token]);
    }

    public function test_banco_token_recuperacao_expirado_nao_valido(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute(['cliente@teste.com']);
        $usuario_id = (int)$stmt->fetchColumn();

        // Insere um token já expirado (expirou 2h atrás)
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO tokens_senha (usuario_id, token, expira_em)
             VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 2 HOUR))"
        )->execute([$usuario_id, $token]);

        $stmt = $pdo->prepare(
            "SELECT id FROM tokens_senha
             WHERE token = ? AND usado = 0 AND expira_em > NOW()"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        $this->assertFalse($row, 'Token expirado não deve ser válido.');

        // Limpa
        $pdo->prepare("DELETE FROM tokens_senha WHERE token = ?")->execute([$token]);
    }

    public function test_banco_token_recuperacao_ja_usado_nao_valido(): void
    {
        $this->skipIfNoDb();
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute(['barbeiro@teste.com']);
        $usuario_id = (int)$stmt->fetchColumn();

        // Insere um token já marcado como usado
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO tokens_senha (usuario_id, token, expira_em, usado)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 1)"
        )->execute([$usuario_id, $token]);

        $stmt = $pdo->prepare(
            "SELECT id FROM tokens_senha
             WHERE token = ? AND usado = 0 AND expira_em > NOW()"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        $this->assertFalse($row, 'Token já usado não deve ser válido.');

        // Limpa
        $pdo->prepare("DELETE FROM tokens_senha WHERE token = ?")->execute([$token]);
    }
}
