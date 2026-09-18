<?php
declare(strict_types=1);

// ── Carrega helpers do projeto ────────────────────────────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Inicializa sessão para os testes ──────────────────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ── Banco de testes: só configura quando DB_HOST está disponível ──
// Se não houver DB_HOST configurado, apenas os testes sem banco rodam.
if (env('DB_HOST')) {
    // Força uso do banco de teste (nunca toca no banco de produção)
    putenv('DB_NAME=barbearia_test');

    try {
        $host   = env('DB_HOST', 'db');
        $port   = env('DB_PORT', '3306');
        $user   = env('DB_USER', 'barbearia');
        $pass   = env('DB_PASS', 'secret');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Recria o banco de teste do zero
        $pdo->exec("DROP DATABASE IF EXISTS barbearia_test");
        $pdo->exec("CREATE DATABASE barbearia_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE barbearia_test");

        // Aplica o schema completo
        $sql = file_get_contents(__DIR__ . '/../docker/mysql/init.sql');
        // Executa statement a statement (PDO não suporta multi-query nativamente)
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if ($query !== '') {
                $pdo->exec($query);
            }
        }

        // ── Fixtures mínimas ─────────────────────────────────────
        $fixtures = [
            ['Admin Teste',    'admin@teste.com',    password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 4]), 'admin',    1, 0],
            ['Barbeiro Teste', 'barbeiro@teste.com', password_hash('barb123',  PASSWORD_BCRYPT, ['cost' => 4]), 'barbeiro', 1, 0],
            ['Cliente Teste',  'cliente@teste.com',  password_hash('cli123',   PASSWORD_BCRYPT, ['cost' => 4]), 'cliente',  1, 0],
            ['Cliente Inativo','inativo@teste.com',  password_hash('ini123',   PASSWORD_BCRYPT, ['cost' => 4]), 'cliente',  0, 0],
        ];

        $ins = $pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, force_reset) VALUES (?,?,?,?,?,?)"
        );
        foreach ($fixtures as $f) {
            $ins->execute($f);
        }

        // Carga horária para o barbeiro (id=2): seg–sex 9h–18h
        $barb_id = 2;
        $ch = $pdo->prepare(
            "INSERT INTO carga_horaria (barbeiro_id, dia_semana, hora_inicio, hora_fim) VALUES (?,?,?,?)"
        );
        foreach ([1, 2, 3, 4, 5] as $dia) { // seg=1 a sex=5
            $ch->execute([$barb_id, $dia, '09:00:00', '18:00:00']);
        }

        // Define a constante para os testes saberem que o banco está disponível
        define('DB_TEST_AVAILABLE', true);

        // Guarda o PDO de teste em variável global para reuso nos testes
        $GLOBALS['_pdo_test'] = $pdo;

    } catch (PDOException $e) {
        // Banco indisponível — testes que precisam do banco serão marcados como skipped
        define('DB_TEST_AVAILABLE', false);
    }
} else {
    define('DB_TEST_AVAILABLE', false);
}
