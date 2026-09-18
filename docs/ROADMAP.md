# Roadmap — barbearia-php

> **Instrução para o agente de IA:** Este é o mapa de desenvolvimento do projeto. Siga as fases em ordem. Marque cada task com `[x]` quando concluída. Não avance para a próxima fase sem todos os testes da fase atual passando.

---

## Legenda de status

| Símbolo | Significado |
|---------|-------------|
| `[ ]` | Não iniciada |
| `[~]` | Em andamento |
| `[x]` | Concluída e testada |
| `[!]` | Bloqueada (motivo nos comentários) |

---

## Fase 1 — Infraestrutura e autenticação

> **Objetivo:** Ter o ambiente rodando e o sistema de login/sessão funcionando para os 3 perfis.

### Infra
- [x] Criar `Dockerfile` com PHP 8.2 + Apache + extensões (pdo_mysql, gd, mbstring)
- [x] Criar `docker-compose.yml` com serviços `app` e `db` e healthcheck
- [x] Criar `docker/mysql/init.sql` com schema completo (tabelas, ENUMs e FKs)
- [x] Criar `.env.example` com todas as variáveis necessárias
- [x] Criar `.gitignore` (ignorar `.env`, `vendor/`, `uploads/`, `*.log`)
- [x] Criar `composer.json` com PHPMailer e PHPUnit
- [x] Executar `docker compose up` e confirmar que sobe sem erros
- [x] Criar `uploads/.htaccess` bloqueando execução de scripts

### Includes base
- [x] Criar `includes/config.php` (lê variáveis de ambiente)
- [x] Criar `includes/db.php` (conexão PDO singleton)
- [x] Criar `includes/auth.php` (sessão, guards, CSRF e flash messages)
- [x] Criar `includes/header.php` (nav responsivo por perfil)
- [x] Criar `includes/footer.php`
- [x] Criar `public/assets/css/tokens.css` (variáveis CSS completas)
- [x] Criar `public/assets/css/global.css` (estilos base responsivos)

### Autenticação
- [x] Criar `public/login.php` (GET: form | POST: valida, cria sessão, redireciona por perfil)
- [x] Criar `public/cadastro.php` (GET: form | POST: valida, hash senha, INSERT, login automático)
- [x] Criar `public/logout.php` (destroi sessão, redireciona para /)
- [~] Criar `public/recuperar-senha.php` (gera token; envio de e-mail depende da fase de notificações)
- [ ] Criar `public/resetar-senha.php` (GET: valida token | POST: atualiza senha, invalida token)
- [x] Implementar CSRF token nos formulários implementados
- [x] Implementar flash messages (setar em sessão, exibir no header, limpar após exibir)

### Testes da Fase 1
- [x] Criar `tests/bootstrap.php`
- [~] Criar `tests/AuthTest.php` (CSRF e destino por perfil; testes de banco pendentes)
- [~] Todos os testes passando antes de avançar (sintaxe PHP validada; PHPUnit requer instalação das dependências Composer)

---

## Fase 2 — Catálogo público

> **Objetivo:** Um visitante consegue ver os serviços, combos e o perfil dos barbeiros sem se cadastrar.

### Páginas
- [ ] Criar `public/index.php` — hero + grid de categorias/serviços + lista de barbeiros + como funciona
- [ ] Criar `public/barbeiro.php` — perfil público com serviços oferecidos + feed do portfólio
- [ ] Criar query de serviços ativos por categoria (com foto de capa)
- [ ] Criar query de barbeiros ativos com especialidades
- [ ] Criar query de portfólio do barbeiro (10 fotos mais recentes)
- [ ] Tratar 404 para barbeiro inexistente ou inativo

### UI
- [ ] Aplicar identidade visual (tokens CSS) em todas as páginas públicas
- [ ] Layout responsivo (mobile: navbar colapsável, grid de 1 coluna)
- [ ] Grid de serviços com card: nome, categoria, descrição resumida, preço, tempo
- [ ] Badge visual para combos (borda dourada diferenciada)
- [ ] Feed de portfólio em grid de 3 colunas com lightbox simples (CSS puro ou JS mínimo)

### Testes da Fase 2
- [ ] Criar `tests/CatalogoTest.php` (queries de serviços, categorias, barbeiros)
- [ ] Todos os testes passando antes de avançar

---

## Fase 3 — Painel Admin (base)

> **Objetivo:** Admin consegue cadastrar e gerenciar barbeiros, serviços, combos e clientes.

### Barbeiros
- [ ] Criar `public/admin/barbeiros.php` — listar barbeiros com count de agendamentos futuros
- [ ] Criar formulário de cadastro de barbeiro (nome, email, senha temporária)
- [ ] Criar formulário de carga horária (checkboxes de dias + hora início/fim por dia)
- [ ] Implementar desativação de barbeiro (soft delete: ativo=0)

### Serviços e categorias
- [ ] Criar `public/admin/categorias.php` — CRUD de categorias com ordenação
- [ ] Criar `public/admin/servicos.php` — CRUD de serviços com upload de fotos
- [ ] Implementar upload de fotos de serviço (validar MIME, redimensionar via GD, salvar em /uploads/servicos/)
- [ ] Criar `public/admin/combos.php` — CRUD de combos com seleção de serviços incluídos

### Clientes
- [ ] Criar `public/admin/clientes.php` — listar com busca por nome/email
- [ ] Criar página de detalhe do cliente (informações + histórico de agendamentos)
- [ ] Implementar exclusão de cliente (soft delete + cancelar agendamentos futuros)
- [ ] Implementar reset de senha para padrão (force_reset=1)

### Mensagens rápidas
- [ ] Criar `public/admin/mensagens.php` — CRUD de mensagens com variáveis {nome_cliente} {data_hora}

### Testes da Fase 3
- [ ] Criar `tests/AdminTest.php` (CRUD barbeiro, serviço, combo, cliente)
- [ ] Criar `tests/UploadTest.php` (validação de MIME, tamanho, extensão)
- [ ] Todos os testes passando antes de avançar

---

## Fase 4 — Painel do Barbeiro

> **Objetivo:** Barbeiro gerencia sua agenda, serviços, portfólio e tickets.

### Dashboard e agenda
- [ ] Criar `public/barbeiro/dashboard.php` — agenda dos próximos 4 dias em ordem cronológica
- [ ] Exibir status de confirmação de cada cliente (confirmado/pendente)
- [ ] Implementar envio de mensagem rápida para cliente de um agendamento

### Serviços e combos
- [ ] Criar `public/barbeiro/servicos.php` — checkboxes dos serviços e combos que o barbeiro oferece
- [ ] Salvar seleção em barbeiro_servicos e barbeiro_combos (DELETE + INSERT)

### Bloqueios
- [ ] Criar `public/barbeiro/bloquear.php` — calendário dos próximos 30 dias
- [ ] Implementar criação de bloqueio (verificar se há agendamentos no período)
- [ ] Implementar remoção de bloqueio

### Portfólio
- [ ] Criar `public/barbeiro/portfolio.php` — feed próprio com opção de upload e exclusão
- [ ] Upload de foto (JPG/PNG/WEBP, max 5MB, redimensionar para max 1200px)
- [ ] Associar foto a um serviço (opcional) e legenda

### Tickets
- [ ] Criar `public/barbeiro/ticket.php` — formulário de sugestão de novo serviço
- [ ] Listar tickets próprios com status (pendente/aprovado/recusado + obs_admin)
- [ ] Enviar email de notificação para todos os admins ao criar ticket

### Testes da Fase 4
- [ ] Criar `tests/TicketTest.php` (criar, aprovar, recusar)
- [ ] Criar `tests/BloqueioTest.php` (criar bloqueio com e sem agendamento no período)
- [ ] Criar `tests/PortfolioTest.php` (upload válido e inválido)
- [ ] Todos os testes passando antes de avançar

---

## Fase 5 — Fluxo de agendamento

> **Objetivo:** Cliente consegue agendar, confirmar e cancelar horários.

### Algoritmo de slots
- [ ] Criar `includes/slots.php` com função `getSlots(barbeiro_id, data, duracao_min)`
- [ ] Implementar todas as regras: carga horária + bloqueios + agendamentos existentes + 4 dias + passado

### Páginas de agendamento
- [ ] Criar `public/agendar.php` com os 3 steps (serviço → horário → barbeiro)
- [ ] Implementar fluxo alternativo via `?barbeiro=ID` (barbeiro → serviço → horário)
- [ ] Exibir mensagem quando não há horários disponíveis
- [ ] Criar agendamento: validar disponibilidade novamente no POST (evitar race condition)
- [ ] Gerar token_confirm único (bin2hex(random_bytes(32)))
- [ ] Enviar email de confirmação com link para confirmar.php

### Confirmação
- [ ] Criar `public/confirmar.php` — validar token, checar prazo 2h, atualizar status

### Área do cliente
- [ ] Criar `public/cliente/dashboard.php` — próximos agendamentos + histórico
- [ ] Criar `public/cliente/cancelar.php` — cancelar agendamento próprio

### Admin — agendamentos
- [ ] Criar `public/admin/agendamentos.php` — listar todos com filtros (barbeiro, status, data)
- [ ] Implementar cancelamento pelo admin (email ao cliente)
- [ ] Implementar envio de mensagem rápida pelo admin

### Admin — tickets
- [ ] Criar `public/admin/tickets.php` — listar pendentes
- [ ] Implementar aprovação (INSERT em servicos + email ao barbeiro)
- [ ] Implementar recusa (obs_admin + email ao barbeiro)

### Testes da Fase 5
- [ ] Criar `tests/SlotsTest.php` (todos os casos do TESTES.md)
- [ ] Criar `tests/AgendamentoTest.php` (criar, confirmar, cancelar, race condition)
- [ ] Criar `tests/CsrfTest.php`
- [ ] Todos os testes passando antes de avançar

---

## Fase 6 — Notificações por email

> **Objetivo:** Todos os emails do sistema funcionando corretamente.

- [ ] Criar `includes/mail.php` com função `enviar_email()` via PHPMailer
- [ ] Criar `includes/emails/` com todos os templates HTML
- [ ] Implementar template: `agendamento_criado` (com link confirmar + link cancelar)
- [ ] Implementar template: `lembrete` (disparado manualmente ou via cron)
- [ ] Implementar template: `confirmacao` (após clicar no link)
- [ ] Implementar template: `cancelamento` (por qualquer origem)
- [ ] Implementar template: `mensagem_rapida` (com substituição de variáveis)
- [ ] Implementar template: `ticket_aprovado`
- [ ] Implementar template: `ticket_recusado`
- [ ] Implementar template: `recuperar_senha`
- [ ] Implementar template: `senha_resetada`
- [ ] Criar `includes/cron_lembrete.php` — script para disparar lembretes (rodar via cron ou manualmente)

### Testes da Fase 6
- [ ] Criar `tests/MailTest.php` (montar_mensagem com variáveis, templates sem erro de sintaxe)
- [ ] Todos os testes passando antes de avançar

---

## Fase 7 — Polimento e segurança

> **Objetivo:** Projeto pronto para uso real.

### Segurança
- [ ] Revisar todos os formulários: CSRF em 100% dos POSTs
- [ ] Revisar todos os outputs: htmlspecialchars em 100% das variáveis exibidas
- [ ] Revisar todas as queries: 100% prepared statements
- [ ] Revisar uploads: validação MIME real com finfo em todos os pontos de upload
- [ ] Revisar autorização: garantir que cada recurso é checado contra o usuário logado
- [ ] Adicionar headers de segurança no Apache (.htaccess ou VirtualHost)
- [ ] Garantir display_errors=Off no php.ini de produção

### UX e validação
- [ ] Validação client-side nos formulários principais (HTML5 required + pattern)
- [ ] Mensagens de erro claras e específicas (não só "erro ao processar")
- [ ] Loading states nos botões de submit (evitar double submit)
- [ ] Página 404 customizada
- [ ] Página 403 customizada

### Testes finais
- [ ] Rodar suite completa de testes
- [ ] Testar fluxo completo end-to-end manualmente:
  - [ ] Cadastro de cliente → agendamento → confirmação → cancelamento
  - [ ] Barbeiro → portfólio → bloqueio → ticket
  - [ ] Admin → cadastro de barbeiro → serviço → aprovação de ticket
- [ ] Verificar responsividade em mobile

---

## Anotações do agente

> **Instrução:** Use este espaço para registrar decisões técnicas tomadas durante o desenvolvimento que não estavam previstas no planejamento original.

*(anotações serão adicionadas aqui conforme o desenvolvimento avança)*
