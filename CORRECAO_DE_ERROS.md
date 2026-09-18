# Plano de Correção e Guia de Resolução de Erros — Barbearia Navalha

Este documento categoriza todos os erros, inconsistências, bugs pontuais e pendências arquiteturais identificados no projeto **barbearia-php**, organizando-os em tarefas (*tasks*) com checklist de implementação e validação.

---

## Índice

1. [Diagnóstico Geral](#1-diagnóstico-geral)
2. [Fase 1: Correção de Bugs Críticos no Código Existente](#fase-1-correção-de-bugs-críticos-no-código-existente)
3. [Fase 2: Dependências, Ambiente e Infraestrutura](#fase-2-dependências-ambiente-e-infraestrutura)
4. [Fase 3: Implementação dos Módulos Faltantes](#fase-3-implementação-dos-módulos-faltantes)
5. [Fase 4: Segurança, Uploads e Tratamento de Exceções](#fase-4-segurança-uploads-e-tratamento-de-exceções)
6. [Fase 5: Suíte de Testes Automatizados (PHPUnit)](#fase-5-suíte-de-testes-automatizados-phpunit)
7. [Checklist Geral de Execução](#checklist-geral-de-execução)

---

## 1. Diagnóstico Geral

Ao inspecionar a base de código, foram encontrados quatro tipos principais de inconsistências:

1. **Bugs Sintáticos e de Consulta SQL:** Trechos duplicados com tabelas inexistentes e interpolações inadequadas de SQL em queries de agendamento e confirmação.
2. **Problemas de Resolução de Caminhos:** Funções que buscam arquivos usando convenções fixas de caminhos (ex: `/html/` ao invés de `/public/`), causando falhas em ambientes Windows ou servidores locais sem VirtualHost específico.
3. **Páginas e Módulos Ausentes (Previstos na Especificação e Roadmap):** Módulos como administração de clientes, tickets de barbeiros, portfólio de cortes, mensagens rápidas e rotinas cron de lembrete ainda não foram implementados.
4. **Dependências e Ausência de Testes:** Dependências do Composer (`PHPMailer`, `PHPUnit`) não compiladas/instaladas localmente e testes unitários ausentes para regras críticas (`SlotsTest`, `AgendamentoTest`, etc.).

---

## Fase 1: Correção de Bugs Críticos no Código Existente

### Task 1.1 — Corrigir query duplicada e tabela inválida em `public/confirmar.php`
- **Arquivo:** `public/confirmar.php` (linhas 11 a 35)
- **Problema:** O arquivo possui duas chamadas consecutivas de `$pdo->prepare(...)`. A primeira executa `JOIN u ON u.id = a.barbeiro_id`, gerando erro caso seja executada, pois a tabela chama-se `usuarios`. A segunda query sobrescreve a primeira sem necessidade.
- **Ação:**
  - Remover a primeira declaração redundante (`$stmt` com `JOIN u ON`).
  - Manter apenas a query formatada com `JOIN usuarios bar ON bar.id = a.barbeiro_id`.
  - Tratar adequadamente o estado de erro/expiração com redirecionamento claro.

### Task 1.2 — Corrigir resolução de caminho do 403 em `includes/auth.php`
- **Arquivo:** `includes/auth.php` (linhas 16 a 21)
- **Problema:** `$p403` é montado como `dirname(__DIR__) . '/html/403.php'`. No ambiente local e em várias configurações de servidor, o diretório é `public/403.php`.
- **Ação:**
  - Adicionar a verificação de `dirname(__DIR__) . '/public/403.php'` na cadeia de fallbacks antes do fallback HTML puro.

### Task 1.3 — Sanitizar concatenação SQL e implementar transação em `public/agendar.php`
- **Arquivo:** `public/agendar.php` (linhas 147 a 165)
- **Problema:** A query faz interpolação de string direta: `" ... VALUES (..., " . ($confirmarJa ? 'NOW()' : 'NULL') . ")"`. Além disso, a inserção não está envelopada em uma transação com verificação de lock para prevenir agendamentos duplicados (*race condition*).
- **Ação:**
  - Iniciar transação via `$pdo->beginTransaction()`.
  - Revalidar a disponibilidade do slot dentro da transação.
  - Usar query parametrizada limpa: passar a data de confirmação como parâmetro ou utilizar expressão SQL unificada `IF(? = 1, NOW(), NULL)`.
  - Executar `$pdo->commit()` ou `$pdo->rollBack()` em caso de erro.

### Task 1.4 — Ajustar verificação de sobreposição em `public/barbeiro/bloquear.php`
- **Arquivo:** `public/barbeiro/bloquear.php` (linhas 36 a 44)
- **Problema:** O cálculo de sobreposição com `ADDTIME(?, ?)` e `SEC_TO_TIME(duracao_min*60)` pode gerar divergências de formatação de data/hora dependendo da versão do MySQL e timezone.
- **Ação:**
  - Padronizar o cálculo de intervalo usando `TIMESTAMP(CONCAT(?, ' ', ?))` ou campos datetime unificados.
  - Exibir detalhes claros dos agendamentos em conflito (nome do cliente e horário) ao criar o bloqueio.

---

## Fase 2: Dependências, Ambiente e Infraestrutura

### Task 2.1 — Subir ambiente via Docker ou configurar PHP CLI no PATH
- **Problema:** O comando `php` não está disponível diretamente no PATH do sistema operacional Windows, impedindo a execução de linters e testes locais fora do contêiner.
- **Ação:**
  - Opção A (Docker): Iniciar o Docker Desktop e rodar:
    ```bash
    docker compose up -d --build
    docker compose exec app composer install
    ```
  - Opção B (Local): Adicionar o binário do PHP ao `PATH` do Windows e rodar `composer install` na raiz do projeto.

### Task 2.2 — Instalar e validar Composer (`PHPMailer` e `PHPUnit`)
- **Arquivos afetados:** `composer.json`, `includes/mail.php`
- **Ação:**
  - Executar `composer install` para gerar a pasta `vendor/` e `vendor/autoload.php`.
  - Garantir que `includes/mail.php` consiga instanciar `\PHPMailer\PHPMailer\PHPMailer(true)`.

---

## Fase 3: Implementação dos Módulos Faltantes

De acordo com o `ROADMAP.md` e o `03-documento-tecnico.html`, os seguintes módulos devem ser criados:

### Task 3.1 — Painel Admin: Gestão de Clientes (`public/admin/clientes.php`)
- **Objetivo:** Permitir ao administrador gerenciar a carteira de clientes.
- **Ações:**
  - Listagem com filtro por nome e e-mail.
  - Exibição de histórico de agendamentos por cliente.
  - Ação de desativação (soft delete: `ativo = 0`) e cancelamento automático de agendamentos futuros.
  - Ação de reset de senha para senha temporária padrão (`force_reset = 1`).

### Task 3.2 — Painel Admin: Mensagens Rápidas (`public/admin/mensagens.php`)
- **Objetivo:** Gerenciar modelos de mensagens padronizadas.
- **Ações:**
  - CRUD na tabela `mensagens_rapidas` (`titulo`, `corpo`, `ativo`).
  - Suporte a tags dinâmicas: `{nome_cliente}`, `{data_hora}`, `{barbeiro}`, `{servico}`.

### Task 3.3 — Painel Admin: Moderação de Tickets (`public/admin/tickets.php`)
- **Objetivo:** Avaliar pedidos de inclusão de novos serviços feitos pelos barbeiros.
- **Ações:**
  - Listar tickets pendentes com detalhes sugeridos (`nome`, `descricao`, `preco`, `duracao_min`, `categoria_id`).
  - Ação de Aprovação: Insere registro em `servicos`, associa ao barbeiro em `barbeiro_servicos` e atualiza ticket para `status='aprovado'`.
  - Ação de Recusa: Preenche `obs_admin` e marca ticket como `status='recusado'`.
  - Disparar e-mail informando o barbeiro sobre a decisão.

### Task 3.4 — Painel do Barbeiro: Portfólio de Cortes (`public/barbeiro/portfolio.php`)
- **Objetivo:** Permitir aos barbeiros exibirem fotos de trabalhos realizados.
- **Ações:**
  - Formulário de upload de fotos (formatos JPG, PNG, WEBP; tamanho máximo 5MB).
  - Redimensionamento e otimização da imagem para no máximo 1200px de largura usando a biblioteca GD do PHP.
  - Salvar em `uploads/portfolio/` e registrar na tabela `portfolio` com legenda e serviço opcional.
  - Excluir fotos existentes com remoção do arquivo físico.

### Task 3.5 — Painel do Barbeiro: Solicitação de Serviços via Tickets (`public/barbeiro/ticket.php`)
- **Objetivo:** Barbeiro sugere novos serviços para a administração.
- **Ações:**
  - Formulário de cadastro de ticket (`nome`, `categoria_id`, `descricao`, `preco`, `duracao_min`).
  - Listagem dos tickets criados pelo barbeiro com status e observação do admin.
  - Envio de e-mail de alerta para administradores ao abrir ticket.

### Task 3.6 — Página Pública do Barbeiro: Feed de Portfólio (`public/barbeiro.php`)
- **Arquivo:** `public/barbeiro.php`
- **Ações:**
  - Adicionar query na tabela `portfolio` buscando as últimas 10 fotos ativas do barbeiro.
  - Exibir grid de fotos com legenda e lightbox simples em CSS/JS.

---

## Fase 4: Segurança, Uploads e Tratamento de Exceções

### Task 4.1 — Validação real de MIME types nos uploads
- **Ação:**
  - Criar função utilitária em `includes/upload.php` utilizando `finfo_open(FILEINFO_MIME_TYPE)` para garantir que arquivos enviados são imagens reais e não scripts renomeados com extensão `.jpg`.
  - Bloquear nomes de arquivos perigosos gerando nomes aleatórios via hash MD5/SHA256 (`bin2hex(random_bytes(16)) . '.webp'`).

### Task 4.2 — Rotina de Lembretes Automáticos (`includes/cron_lembrete.php`)
- **Ação:**
  - Criar script executável via CLI para buscar agendamentos confirmados das próximas 24h que ainda não receberam lembrete.
  - Disparar e-mail de lembrete com resumo e orientações de cancelamento prévio.

---

## Fase 5: Suíte de Testes Automatizados (PHPUnit)

Conforme as regras de governança estipuladas em `docs/TESTES.md`, toda regra de negócio precisa de cobertura de testes.

### Task 5.1 — Criar `tests/SlotsTest.php`
- [ ] Testar dia sem carga horária retornando array vazio.
- [ ] Testar remoção de slots já ocupados por agendamentos confirmados ou pendentes.
- [ ] Testar remoção de horários conflitantes com bloqueios manuais.
- [ ] Testar rejeição de datas superiores a hoje + 4 dias.
- [ ] Testar remoção de horários que já passaram no dia corrente.
- [ ] Testar cálculo para serviços/combos com durações longas (ex: 90min).

### Task 5.2 — Criar `tests/AgendamentoTest.php`
- [ ] Testar criação com geração correta do `token_confirm`.
- [ ] Testar confirmação dentro do prazo limite (até 2h antes).
- [ ] Testar expiração da confirmação após o prazo limite.
- [ ] Testar cancelamento por parte do cliente e do admin.

### Task 5.3 — Criar `tests/CsrfTest.php` e `tests/UploadTest.php`
- [ ] Testar geração, consistência e validação de token CSRF.
- [ ] Testar rejeição de arquivos não permitidos e validação de tamanho de upload.

---

## Checklist Geral de Execução

| Status | ID | Descrição | Prioridade |
| :---: | :---: | :--- | :---: |
| [ ] | **T1.1** | Corrigir query duplicada e tabela inválida em `public/confirmar.php` | Alta |
| [ ] | **T1.2** | Corrigir resolução de caminho de erro 403 em `includes/auth.php` | Média |
| [ ] | **T1.3** | Envolver agendamento em transação PDO e sanitizar query em `public/agendar.php` | Alta |
| [ ] | **T1.4** | Refinar verificação de sobreposição de bloqueios em `public/barbeiro/bloquear.php` | Média |
| [ ] | **T2.1** | Inicializar Docker e instalar dependências do Composer (`PHPMailer`, `PHPUnit`) | Alta |
| [ ] | **T3.1** | Criar tela de gestão de clientes `public/admin/clientes.php` | Alta |
| [ ] | **T3.2** | Criar tela de mensagens rápidas `public/admin/mensagens.php` | Média |
| [ ] | **T3.3** | Criar tela de aprovação de tickets `public/admin/tickets.php` | Alta |
| [ ] | **T3.4** | Criar tela de portfólio do barbeiro `public/barbeiro/portfolio.php` com redimensionamento GD | Alta |
| [ ] | **T3.5** | Criar tela de solicitação de tickets `public/barbeiro/ticket.php` | Média |
| [ ] | **T3.6** | Integrar exibição do portfólio em `public/barbeiro.php` | Média |
| [ ] | **T4.1** | Implementar função centralizada de upload seguro com validação MIME real | Alta |
| [ ] | **T4.2** | Criar rotina de lembretes automáticos `includes/cron_lembrete.php` | Baixa |
| [ ] | **T5.1** | Implementar `tests/SlotsTest.php` | Alta |
| [ ] | **T5.2** | Implementar `tests/AgendamentoTest.php` | Alta |
| [ ] | **T5.3** | Implementar `tests/CsrfTest.php` e `tests/UploadTest.php` | Média |
