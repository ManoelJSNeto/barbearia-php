# Relatório do Projeto — barbearia-php

> **Instruções para o agente de IA:** Este arquivo deve ser atualizado a cada sessão de desenvolvimento. Siga o padrão abaixo rigorosamente. Nunca apague entradas anteriores — apenas adicione novas.

---

## Como atualizar este relatório

Ao final de **cada sessão de desenvolvimento**, adicione uma nova entrada na seção **Histórico de sessões** seguindo este modelo exato:

```markdown
### Sessão YYYY-MM-DD — [título curto descrevendo o que foi feito]

**Fase:** [número e nome da fase do ROADMAP.md]
**Status das tasks:**
- [x] Task concluída
- [x] Task concluída
- [ ] Task iniciada mas não finalizada (motivo: ...)

**O que foi feito:**
Descrição em prosa do que foi implementado nesta sessão.

**Arquivos criados ou modificados:**
- `caminho/arquivo.php` — descrição do que faz
- `caminho/outro.php` — descrição do que faz

**Testes executados:**
- `tests/NomeDoTest.php` — passou / falhou (detalhe se falhou)

**Decisões tomadas:**
- Decisão X foi tomada porque Y
- Alternativa Z foi descartada porque W

**Pendências / próxima sessão:**
- O que ficou para fazer
- Dúvidas que precisam ser resolvidas
```

---

## Regras de atualização

1. **Sempre atualizar antes de commitar.** O relatório faz parte do commit, junto com o código.
2. **Nunca resumir demais.** Se uma função foi criada, diga qual é e o que ela faz.
3. **Registrar decisões.** Se você escolheu uma abordagem em vez de outra, escreva o motivo.
4. **Registrar problemas.** Se algo não funcionou ou precisou ser refeito, documente.
5. **Manter o índice atualizado.** Adicione cada sessão no índice abaixo.

---

## Índice de sessões

| Data | Fase | Resumo |
|------|------|--------|
| 2026-09-18 | Fase 1 | Infraestrutura, autenticação e base visual implementadas; execução bloqueada pelo Docker Desktop desligado. |

---

## Histórico de sessões

*(As entradas de desenvolvimento serão adicionadas aqui a partir da primeira sessão de código.)*

### Sessão 2026-09-18 — fundação da aplicação

**Fase:** 1 — Infraestrutura e autenticação
**Status das tasks:**
- [x] Infraestrutura Docker, schema MySQL, variáveis de ambiente e proteção de uploads
- [x] Configuração PDO, sessão, autenticação, CSRF e mensagens flash
- [x] Login, cadastro, logout, recuperação de senha baseada em token e páginas-base dos perfis
- [~] Recuperação de senha sem envio SMTP até a fase de notificações
- [x] Contêineres construídos e iniciados; MySQL com healthcheck saudável e home respondendo 200
- [~] PHPUnit pendente da instalação das dependências Composer

**O que foi feito:**
Foi criada a estrutura inicial executável da aplicação Navalha. O banco contém as entidades previstas para usuários, serviços, combos, disponibilidade, agendamentos, portfólio, tickets e mensagens. A autenticação usa hash bcrypt, sessão regenerada no login, consultas preparadas, tokens CSRF e mensagens de retorno. A home pública aplica os tokens visuais definidos e consulta serviços, combos e barbeiros ativos no banco.

**Arquivos criados ou modificados:**
- `docker/` e `docker-compose.yml` — ambiente PHP 8.2/Apache e MySQL 8
- `includes/` — configuração, conexão, autenticação e layout compartilhado
- `public/` — home, autenticação, painéis iniciais e estilos
- `tests/AuthTest.php` — verificações iniciais da sessão

**Testes executados:**
- Validação de sintaxe com `php -l` para todos os arquivos PHP — passou.
- `GET http://localhost:8080/` — respondeu `200 OK`.

**Decisões tomadas:**
- O arquivo `.env` não é versionado; `config.php` aceita variáveis do contêiner e carrega `.env` apenas para desenvolvimento local.
- A recuperação não informa se o e-mail existe, reduzindo enumeração de contas.

**Pendências / próxima sessão:**
- Iniciar Docker Desktop e executar `docker compose up --build -d`.
- Instalar dependências Composer no contêiner e rodar PHPUnit.
- Concluir reset de senha e os fluxos de catálogo, administração e agendamento.

---

## Estado atual do projeto

> **Instrução para o agente:** Mantenha esta seção sempre atualizada com o estado real do projeto.

- **Fase atual:** Fase 1 — Infraestrutura e autenticação
- **Próxima ação:** Instalar dependências Composer no contêiner e concluir testes de autenticação.
- **Bloqueios:** nenhum para executar a aplicação; PHPUnit ainda depende do Composer.
- **Ambiente:** Aplicação disponível em `http://localhost:8080`; MySQL saudável.
