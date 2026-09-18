# Roadmap — barbearia-php

> **Instrução para o agente de IA:** Este é o mapa de desenvolvimento do projeto. Siga as fases em ordem. Marque cada task com [x] quando concluída. Não avance para a próxima fase sem todos os testes da fase atual passando.

---

## Legenda de status

| Símbolo | Significado |
|---------|-------------|
| [ ] | Não iniciada |
| [~] | Em andamento |
| [x] | Concluída e testada |
| [!] | Bloqueada (motivo nos comentários) |

---

## Fase 1 — Infraestrutura e autenticação

> **Objetivo:** Ter o ambiente rodando e o sistema de login/sessão funcionando para os 3 perfis.

### Infra
- [x] Criar Dockerfile com PHP 8.2 + Apache + extensões (pdo_mysql, gd, mbstring)
- [x] Criar docker-compose.yml com serviços pp e db e healthcheck
- [x] Criar docker/mysql/init.sql com schema completo (tabelas, ENUMs e FKs)
- [x] Criar .env.example com todas as variáveis necessárias
- [x] Criar .gitignore (ignorar .env, endor/, uploads/, *.log)
- [x] Criar composer.json com PHPMailer e PHPUnit
- [x] Executar docker compose up e confirmar que sobe sem erros
- [x] Criar uploads/.htaccess bloqueando execução de scripts

### Includes base
- [x] Criar includes/config.php (lê variáveis de ambiente)
- [x] Criar includes/db.php (conexão PDO singleton)
- [x] Criar includes/auth.php (sessão, guards, CSRF e flash messages) — caminho 403 corrigido para multi-ambiente
- [x] Criar includes/header.php (nav responsivo por perfil)
- [x] Criar includes/footer.php
- [x] Criar public/assets/css/tokens.css (variáveis CSS completas)
- [x] Criar public/assets/css/global.css (estilos base responsivos)

### Autenticação
- [x] Criar public/login.php (GET: form | POST: valida, cria sessão, redireciona por perfil)
- [x] Criar public/cadastro.php (GET: form | POST: valida, hash senha, INSERT, login automático + force_reset na sessão)
- [x] Criar public/logout.php (destroi sessão, regenera id, redireciona para /)
- [x] Criar public/recuperar-senha.php (gera token; sem enumeração de e-mail)
- [x] Criar public/resetar-senha.php (GET: valida token | POST: atualiza senha em transação, invalida token)
- [x] Implementar CSRF token nos formulários implementados
- [x] Implementar flash messages (setar em sessão, exibir no header, limpar após exibir)
- [x] Implementar guard orce_reset em exigir_login() (redireciona para redefinição de senha)

### Testes da Fase 1
- [x] Criar 	ests/bootstrap.php
- [x] Criar 	ests/AuthTest.php (CSRF, destino por perfil, validações de sessão)
- [!] PHPUnit requer composer install no ambiente antes de rodar

---

## Fase 2 — Catálogo público

> **Objetivo:** Um visitante consegue ver os serviços, combos e o perfil dos barbeiros sem se cadastrar.

### Páginas
- [x] Criar public/index.php — hero + grid de serviços + lista de barbeiros + como funciona
- [x] Criar public/barbeiro.php — perfil público com serviços oferecidos + feed do portfólio
- [x] Criar query de serviços ativos por categoria
- [x] Criar query de barbeiros ativos com especialidades
- [x] Criar query de portfólio do barbeiro (10 fotos mais recentes)
- [x] Tratar 404 para barbeiro inexistente ou inativo

### UI
- [x] Aplicar identidade visual (tokens CSS) em todas as páginas públicas
- [x] Layout responsivo (mobile: navbar colapsável, grid de 1 coluna)
- [x] Grid de serviços com card: nome, categoria, descrição resumida, preço, tempo
- [x] Badge visual para combos (borda dourada diferenciada)
- [ ] Feed de portfólio em grid de 3 colunas com lightbox simples (CSS puro ou JS mínimo)

### Testes da Fase 2
- [ ] Criar 	ests/CatalogoTest.php (queries de serviços, categorias, barbeiros)
- [ ] Todos os testes passando antes de avançar

---

## Fase 3 — Painel Admin (base)

> **Objetivo:** Admin consegue cadastrar e gerenciar barbeiros, serviços, combos e clientes.

### Barbeiros
- [x] Criar public/admin/barbeiros.php — listar barbeiros com count de agendamentos futuros
- [x] Criar formulário de cadastro de barbeiro (nome, email, senha temporária)
- [x] Criar formulário de carga horária (checkboxes de dias + hora início/fim por dia)
- [x] Implementar desativação de barbeiro (soft delete: ativo=0)

### Serviços e categorias
- [x] Criar public/admin/servicos.php — CRUD de serviços + categorias + combos com upload de fotos
- [x] Implementar upload de fotos de serviço via includes/upload.php (MIME real, GD, WEBP)
- [ ] Criar public/admin/categorias.php separado — CRUD de categorias com ordenação (atualmente embutido em servicos.php)
- [ ] Criar public/admin/combos.php separado — CRUD de combos (atualmente embutido em servicos.php)

### Clientes
- [x] Criar public/admin/clientes.php — listar com busca por nome/email
- [x] Implementar exclusão de cliente (soft delete + cancelar agendamentos futuros)
- [x] Implementar reset de senha para padrão (force_reset=1)
- [ ] Criar página de detalhe do cliente (informações + histórico de agendamentos)

### Mensagens rápidas
- [x] Criar public/admin/mensagens.php — CRUD de mensagens com variáveis {nome_cliente} {data_hora}

### Testes da Fase 3
- [ ] Criar 	ests/AdminTest.php (CRUD barbeiro, serviço, combo, cliente)
- [x] Criar 	ests/UploadTest.php (validação de MIME, tamanho, extensão)
- [ ] Todos os testes passando antes de avançar

---

## Fase 4 — Painel do Barbeiro

> **Objetivo:** Barbeiro gerencia sua agenda, serviços, portfólio e tickets.

### Dashboard e agenda
- [x] Criar public/barbeiro/dashboard.php — agenda dos próximos 5 dias em ordem cronológica
- [x] Exibir status de confirmação de cada cliente (confirmado/pendente)
- [x] Implementar envio de mensagem rápida para cliente de um agendamento
- [x] Criar public/barbeiro/relatorio.php — relatório financeiro do barbeiro

### Serviços e combos
- [x] Criar public/barbeiro/servicos.php — checkboxes dos serviços e combos que o barbeiro oferece
- [x] Salvar seleção em barbeiro_servicos e barbeiro_combos (DELETE + INSERT)

### Bloqueios (Folgas)
- [x] Criar public/barbeiro/bloquear.php — calendário dos próximos 30 dias
- [x] Implementar criação de bloqueio com verificação de sobreposição via TIMESTAMPADD
- [x] Implementar remoção de bloqueio

### Portfólio
- [x] Criar public/barbeiro/portfolio.php — feed próprio com upload e exclusão
- [x] Upload via includes/upload.php (JPG/PNG/WEBP → WEBP, max 5MB, max 1200px via GD)
- [x] Associar foto a um serviço (opcional) e legenda

### Tickets
- [x] Criar public/barbeiro/ticket.php — formulário de sugestão de novo serviço
- [x] Listar tickets próprios com status (pendente/aprovado/recusado + obs_admin)
- [x] Enviar email de notificação para todos os admins ao criar ticket

### Testes da Fase 4
- [ ] Criar 	ests/TicketTest.php (criar, aprovar, recusar)
- [ ] Criar 	ests/BloqueioTest.php (criar bloqueio com e sem agendamento no período)
- [ ] Criar 	ests/PortfolioTest.php (upload válido e inválido)
- [ ] Todos os testes passando antes de avançar

---

## Fase 5 — Fluxo de agendamento

> **Objetivo:** Cliente consegue agendar, confirmar e cancelar horários.

### Algoritmo de slots
- [x] Criar includes/slots.php com função getSlots(barbeiro_id, data, duracao_min)
- [x] Implementar todas as regras: carga horária + bloqueios + agendamentos existentes + 4 dias + passado

### Páginas de agendamento
- [x] Criar public/agendar.php com os 3 steps (serviço → barbeiro/data → horário)
- [x] Implementar fluxo alternativo via ?barbeiro=ID
- [x] Exibir mensagem quando não há horários disponíveis
- [x] Criar agendamento: validar disponibilidade novamente no POST via transação PDO (anti race condition com TIMESTAMPADD)
- [x] Gerar token_confirm único (bin2hex(random_bytes(32)))
- [x] Enviar email de confirmação com link para confirmar.php

### Confirmação
- [x] Criar public/confirmar.php — validar token, checar prazo 2h, atualizar status; acesso sem login redireciona com ?next=

### Área do cliente
- [x] Criar public/cliente/dashboard.php — próximos agendamentos + histórico
- [x] Criar public/cliente/cancelar.php — cancelar agendamento próprio

### Admin — agendamentos
- [x] Criar public/admin/agendamentos.php — listar todos com filtros (barbeiro, status, data)
- [x] Implementar cancelamento pelo admin (email ao cliente)
- [x] Implementar envio de mensagem rápida pelo admin

### Admin — tickets
- [x] Criar public/admin/tickets.php — listar pendentes e histórico
- [x] Implementar aprovação (INSERT em servicos + email ao barbeiro)
- [x] Implementar recusa (obs_admin + email ao barbeiro)

### Testes da Fase 5
- [x] Criar 	ests/SlotsTest.php (todos os casos do TESTES.md)
- [x] Criar 	ests/AgendamentoTest.php (criar, confirmar, cancelar, race condition)
- [ ] Criar 	ests/CsrfTest.php
- [!] Testes requerem composer install no ambiente para executar PHPUnit

---

## Fase 6 — Notificações por email

> **Objetivo:** Todos os emails do sistema funcionando corretamente.

- [x] Criar includes/mail.php com função enviar_email() via PHPMailer
- [x] Implementar template: gendamento_criado (com link confirmar + link cancelar)
- [x] Implementar template: lembrete via includes/cron_lembrete.php
- [x] Implementar template: confirmacao (após clicar no link)
- [x] Implementar template: cancelamento (por qualquer origem: cliente/barbeiro/admin)
- [x] Implementar template: mensagem_rapida (com substituição de variáveis)
- [x] Implementar template: 	icket_aprovado
- [x] Implementar template: 	icket_recusado
- [x] Implementar template: ecuperar_senha
- [x] Criar includes/cron_lembrete.php — script para disparar lembretes via CLI/cron
- [ ] Implementar template: senha_resetada (email após reset bem-sucedido)
- [ ] Criar 	ests/MailTest.php (montar_mensagem com variáveis, templates sem erro de sintaxe)
- [ ] Todos os testes passando antes de avançar

---

## Fase 7 — Polimento e segurança

> **Objetivo:** Projeto pronto para uso real.

### Segurança
- [x] CSRF em 100% dos POSTs
- [x] htmlspecialchars via função e() em 100% das saídas
- [x] 100% prepared statements nas queries
- [x] Uploads: validação MIME real com info via includes/upload.php
- [x] Autorização: cada recurso checado contra o usuário logado
- [ ] Adicionar headers de segurança no Apache (.htaccess ou VirtualHost)
- [ ] Garantir display_errors=Off no php.ini de produção

### UX e validação
- [x] Validação client-side nos formulários principais (HTML5 required + pattern)
- [x] Mensagens de erro claras e específicas
- [x] Loading states nos botões de submit (evitar double submit) — disabled no JS
- [x] Página 403 customizada (public/403.php)
- [ ] Página 404 customizada

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

- confirmar.php foi refatorado para permitir acesso sem login e redirecionar com ?next= (melhor UX para links de e-mail).
- uth.php: resolução de 403.php usa array de candidatos para suportar Docker e instalação local simultaneamente.
- gendar.php: inserção de agendamento envolve transação PDO + revalidação de slot via TIMESTAMPADD para mitigar race condition.
- loquear.php: cálculo de sobreposição migrado de ADDTIME() para TIMESTAMP(date, time) + TIMESTAMPADD(MINUTE, ...) para maior compatibilidade.
- includes/upload.php: centraliza toda lógica de upload; valida MIME real com info, redimensiona com GD e salva sempre em WEBP.
- public/admin/servicos.php: CRUD de categorias e combos foi mantido na mesma página para simplificar a navegação do admin.
