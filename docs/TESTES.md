# Testes Automatizados — barbearia-php

> **Instrução para o agente de IA:** Nenhuma funcionalidade deve ser commitada sem um teste correspondente. Este documento define o padrão de testes do projeto.

---

## Regra principal

> **Toda nova funcionalidade ou função PHP de lógica de negócio deve ter pelo menos um teste antes do commit.**

Isso inclui:
- Funções em `includes/` (auth, db, mail, slots)
- Qualquer lógica de validação ou cálculo
- Qualquer query crítica (agendamento, disponibilidade, cancelamento)

Não precisa de teste:
- HTML puro / templates
- CSS
- Arquivos de configuração

---

## Stack de testes

O projeto usa **PHPUnit** (instalado via Composer junto com PHPMailer).

```bash
# instalar dependências (já inclui PHPUnit)
composer install

# rodar todos os testes
./vendor/bin/phpunit tests/

# rodar um arquivo específico
./vendor/bin/phpunit tests/SlotsTest.php

# rodar com saída detalhada
./vendor/bin/phpunit tests/ --testdox
```

---

## Estrutura de pastas de testes

```
tests/
├── bootstrap.php          # setup global (conexão com banco de teste)
├── AuthTest.php           # testa auth.php (sessão, perfis, guards)
├── SlotsTest.php          # testa o algoritmo de geração de horários
├── AgendamentoTest.php    # testa criação, cancelamento, confirmação
├── TicketTest.php         # testa fluxo de tickets (criar, aprovar, recusar)
├── MailTest.php           # testa montagem dos templates de email
├── UploadTest.php         # testa validação de arquivos de upload
└── CsrfTest.php           # testa geração e validação de token CSRF
```

---

## Padrão de um arquivo de teste

```php
<?php
// tests/ExemploTest.php

use PHPUnit\Framework\TestCase;

class ExemploTest extends TestCase
{
    protected function setUp(): void
    {
        // executado antes de cada test
        // limpar estado, resetar dados de teste
    }

    protected function tearDown(): void
    {
        // executado após cada test
        // limpar registros criados no banco de teste
    }

    public function test_descricao_clara_do_que_esta_sendo_testado(): void
    {
        // Arrange — montar o cenário
        $entrada = [...];

        // Act — executar a função
        $resultado = minhaFuncao($entrada);

        // Assert — verificar o resultado
        $this->assertEquals($esperado, $resultado);
    }

    public function test_caso_de_erro_tambem_deve_ser_testado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        minhaFuncao(entradaInvalida());
    }
}
```

---

## Banco de dados de teste

- Usar um banco separado: `barbearia_test`
- Configurar no `docker-compose.yml` como segundo banco ou via variável de ambiente `DB_NAME_TEST=barbearia_test`
- O `tests/bootstrap.php` deve:
  1. Conectar ao banco de teste
  2. Rodar o `init.sql` para recriar as tabelas
  3. Inserir fixtures mínimas (1 admin, 1 barbeiro, 1 cliente)

```php
<?php
// tests/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

// usar banco de teste, nunca o de produção
$_ENV['DB_NAME'] = 'barbearia_test';

// recriar schema
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);
$pdo->exec("DROP DATABASE IF EXISTS barbearia_test");
$pdo->exec("CREATE DATABASE barbearia_test");
$pdo->exec("USE barbearia_test");

$sql = file_get_contents(__DIR__ . '/../docker/mysql/init.sql');
foreach (explode(';', $sql) as $query) {
    $q = trim($query);
    if ($q) $pdo->exec($q);
}

// fixtures mínimas
$pdo->exec("INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES
  ('Admin Teste',    'admin@teste.com',    '" . password_hash('admin123', PASSWORD_BCRYPT) . "', 'admin'),
  ('Barbeiro Teste', 'barbeiro@teste.com', '" . password_hash('barb123',  PASSWORD_BCRYPT) . "', 'barbeiro'),
  ('Cliente Teste',  'cliente@teste.com',  '" . password_hash('cli123',   PASSWORD_BCRYPT) . "', 'cliente')
");
```

---

## Testes obrigatórios por funcionalidade

### Fase 1 — Auth
| Função | O que testar |
|--------|-------------|
| login | credenciais corretas → sessão criada |
| login | senha errada → sessão não criada |
| login | usuário inativo → bloqueado |
| exigir_perfil() | perfil correto → passa |
| exigir_perfil() | perfil errado → redireciona |
| token de recuperação | gerado, válido, expirado, já usado |

### Fase 2 — Slots
| Função | O que testar |
|--------|-------------|
| getSlots() | dia sem carga horária → array vazio |
| getSlots() | dia com agendamento → slot bloqueado |
| getSlots() | bloqueio manual → slot bloqueado |
| getSlots() | data além de 4 dias → array vazio |
| getSlots() | horário passado no mesmo dia → não retorna |
| getSlots() | combo de 90min → slots de 45min que caberiam são removidos |

### Fase 3 — Agendamento
| Ação | O que testar |
|------|-------------|
| criar agendamento | slot válido → inserido, token gerado, status=pendente |
| criar agendamento | slot ocupado (race condition) → erro |
| confirmar | token válido + dentro do prazo → status=confirmado |
| confirmar | token expirado (< 2h) → erro |
| cancelar | pelo cliente → status=cancelado, cancelado_por=cliente |
| cancelar | agendamento de outro cliente → negado |

### Fase 4 — Upload (portfólio)
| Caso | O que testar |
|------|-------------|
| upload válido | JPG/PNG/WEBP < 5MB → salvo |
| upload inválido | PHP disfarçado de JPG → rejeitado |
| upload inválido | arquivo > 5MB → rejeitado |

---

## Checklist pré-commit

Antes de todo `git commit`, o agente deve executar e confirmar:

```bash
# 1. rodar todos os testes
./vendor/bin/phpunit tests/ --testdox

# 2. verificar se todos passaram (zero failures, zero errors)

# 3. só então commitar
git add .
git commit -m "feat: descrição do que foi feito"
```

Se qualquer teste falhar: **não commitar**. Corrigir primeiro.

---

## Nomenclatura de commits

Seguir o padrão **Conventional Commits**:

| Prefixo | Quando usar |
|---------|-------------|
| `feat:` | nova funcionalidade |
| `fix:` | correção de bug |
| `test:` | adição ou correção de testes |
| `chore:` | configuração, infra, dependências |
| `docs:` | atualização de documentação |
| `refactor:` | refatoração sem mudança de comportamento |

Exemplos:
```
feat: adicionar geração de slots de horário
test: adicionar testes para getSlots()
fix: corrigir validação de token de confirmação expirado
docs: atualizar RELATORIO.md com sessão 2024-01-15
```
