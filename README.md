# Navalha — Sistema de Agendamento para Barbearia

> Projeto final da disciplina **Programação Web III (PW3)**  
> Curso Técnico em Desenvolvimento de Sistemas — **ETEC**

---

## Sobre o projeto

O **Navalha** é um sistema web completo de agendamento online para barbearias, desenvolvido como projeto de conclusão da disciplina de Programação Web III. O objetivo é demonstrar na prática os conhecimentos adquiridos ao longo do curso: desenvolvimento back-end com PHP, modelagem de banco de dados relacional, autenticação e controle de sessão, arquitetura MVC-like, segurança web e construção de interfaces responsivas com HTML e CSS puros.

A ideia nasceu da necessidade real de barbearias independentes que ainda dependem de ligações, mensagens no WhatsApp ou até anotações em papel para organizar a agenda. O sistema resolve isso de forma direta: o cliente entra no site, escolhe o serviço e o barbeiro, vê os horários realmente disponíveis e agenda em menos de um minuto — sem app, sem cadastro de cartão, sem complicação.

---

## Funcionalidades

**Para o cliente**
- Cadastro e login com senha criptografada (bcrypt)
- Visualização do catálogo de serviços, combos e barbeiros
- Agendamento em 3 passos: serviço → barbeiro/data → horário
- Confirmação de presença por link
- Cancelamento de agendamentos futuros
- Histórico completo de atendimentos

**Para o barbeiro**
- Painel com agenda dos próximos 4 dias agrupada por dia
- Configuração dos serviços e combos que oferece
- Bloqueio de períodos de indisponibilidade

**Para o administrador**
- Dashboard com visão geral da agenda do dia
- Cadastro e gerenciamento de barbeiros com carga horária semanal
- CRUD completo de categorias, serviços e combos
- Listagem de agendamentos com filtros por barbeiro, status e data
- Cancelamento de agendamentos pelo painel

---

## Tecnologias utilizadas

| Camada | Tecnologia |
|---|---|
| Back-end | PHP 8.2 |
| Banco de dados | MySQL 8 |
| Front-end | HTML5 + CSS3 (sem frameworks) |
| Servidor | Apache 2.4 (via Docker) |
| Containerização | Docker + Docker Compose |
| Segurança | PDO com prepared statements, bcrypt, CSRF tokens, sessão segura |
| Tipografia | Playfair Display + IBM Plex Sans (Google Fonts) |

---

## Como rodar localmente

**Pré-requisitos:** Docker e Docker Compose instalados.

```bash
# 1. Clone o repositório
git clone https://github.com/ManoelJSNeto/barbearia-php.git
cd barbearia-php

# 2. Crie o arquivo de ambiente
cp .env.example .env

# 3. Suba os containers
docker compose up -d

# 4. Acesse no navegador
http://localhost:8080
```

O banco de dados é inicializado automaticamente com as tabelas e dados de seed pelo arquivo `docker/mysql/init.sql`.

---

## Credenciais de acesso (ambiente de desenvolvimento)

> Todas as contas abaixo existem no seed do banco. A senha padrão de desenvolvimento é `12345678` exceto onde indicado.

### Administradores

| Nome | E-mail | Senha |
|---|---|---|
| Administrador | admin@navalha.com.br | `Navalha@2025` *(hash no seed)* |
| admTeste | teste@adm.com | `12345678` |

### Barbeiros

| Nome | E-mail | Senha |
|---|---|---|
| barbeiro teste | teste@barbeiro.com | `12345678` |

### Clientes

| Nome | E-mail | Senha |
|---|---|---|
| clienteTeste | teste@exemplo.com | `12345678` |

> **Atenção:** estas credenciais são exclusivas para ambiente de desenvolvimento/demonstração. Em produção, troque todas as senhas e remova os seeds de teste.

---

## Estrutura do projeto

```
barbearia-php/
├── docker/
│   ├── Dockerfile              # Imagem PHP 8.2 + Apache
│   └── mysql/
│       └── init.sql            # Schema + seeds do banco
├── docs/                       # Documentação e artefatos do projeto
├── includes/                   # Módulos PHP compartilhados
│   ├── auth.php                # Sessão, guards, CSRF, flash messages
│   ├── config.php              # Leitura de variáveis de ambiente
│   ├── db.php                  # Conexão PDO singleton
│   ├── slots.php               # Algoritmo de cálculo de horários disponíveis
│   ├── header.php              # Cabeçalho HTML + nav por perfil
│   └── footer.php              # Rodapé HTML
├── public/                     # Document root do Apache
│   ├── assets/css/             # Tokens de design e estilos globais
│   ├── admin/                  # Painel do administrador
│   ├── barbeiro/               # Painel do barbeiro
│   ├── cliente/                # Área do cliente
│   ├── index.php               # Página pública principal
│   ├── agendar.php             # Fluxo de agendamento (3 steps)
│   ├── confirmar.php           # Confirmação de presença por token
│   ├── login.php               # Autenticação
│   ├── cadastro.php            # Registro de clientes
│   └── ...
├── uploads/                    # Arquivos enviados (ignorado no git)
├── .env.example                # Modelo de variáveis de ambiente
├── composer.json               # Dependências PHP (PHPMailer, PHPUnit)
└── docker-compose.yml          # Orquestração dos serviços
```

---

## Segurança implementada

- Todas as queries usam **prepared statements** com PDO — sem SQL injection
- Senhas armazenadas com **bcrypt** (custo 12)
- **CSRF token** em 100% dos formulários POST
- Sessão com `httponly`, `samesite=Lax` e regeneração de ID no login
- Outputs escapados com `htmlspecialchars` em toda a camada de view
- Headers de segurança no Apache: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- Diretório `uploads/` com `.htaccess` bloqueando execução de scripts

---

## Disciplina e instituição

| | |
|---|---|
| **Disciplina** | Programação Web III (PW3) |
| **Instituição** | ETEC — Escola Técnica Estadual |
| **Curso** | Técnico em Desenvolvimento de Sistemas |
| **Tipo** | Projeto Final |

---

*Desenvolvido com dedicação como demonstração prática dos conceitos de desenvolvimento web full-stack com PHP.*
