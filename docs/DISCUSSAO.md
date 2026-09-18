# Discussão de Planejamento — barbearia-php

> Transcrição completa da conversa de planejamento do projeto entre o desenvolvedor e o assistente de IA.
> Data: 2025 · Repositório: https://github.com/ManoelJSNeto/barbearia-php

---

## Índice

1. [Apresentação do projeto](#1-apresentação-do-projeto)
2. [Levantamento de regras de negócio — rodada 1](#2-levantamento-de-regras-de-negócio--rodada-1)
3. [Levantamento de regras de negócio — rodada 2](#3-levantamento-de-regras-de-negócio--rodada-2)
4. [Levantamento de regras de negócio — rodada 3](#4-levantamento-de-regras-de-negócio--rodada-3)
5. [Validação do brief](#5-validação-do-brief)
6. [Identidade visual e nome do projeto](#6-identidade-visual-e-nome-do-projeto)
7. [Documento técnico de implementação](#7-documento-técnico-de-implementação)
8. [Organização final do repositório](#8-organização-final-do-repositório)

---

## 1. Apresentação do projeto

**Desenvolvedor:**
> Me ajude a montar esse projeto, vamos conversar sobre as regras de negócio. Ele é uma página web com PHP puro e MySQL containerizado e versionado no GitHub. A ideia do projeto é uma página web de barbearia, onde seria acessado pelos clientes. Eles teriam acesso aos cortes/serviços (tanto cortes de cabelo e seus tipos quanto a barba, sobrancelha e tals — categorias), além de ver fotos de exemplos para ajudar na escolha do serviço, e sua descrição. Além do acesso do cliente, teremos o admin, que terá acesso ao fundo da aplicação, podendo cadastrar os barbeiros e seus serviços — colocando os preços e o tempo de cada serviço, também colocando o tempo que eles ficam lá na barbearia (a carga horária deles) para termos os horários que ele pode atender. O admin também pode mexer nos clientes (apenas excluir ou então resetar a senha, pode ver detalhes/informações do cliente), pode ver e mexer nos agendamentos dos clientes (cancelar, notificar). O acesso do barbeiro seria diferente do admin: ele seria para ele colocar/escolher os serviços que ele faz (já vai ter sido previamente cadastrado pelo admin, ou então ele pode gerar um ticket com o que ele quer colocar já com todas as informações necessárias para cadastrar um novo serviço, que seria aprovada pelo admin e assim que aprovado isso entra para os serviços disponíveis).

---

## 2. Levantamento de regras de negócio — rodada 1

**Assistente** levantou as seguintes perguntas:

**Sobre agendamento:**
1. O cliente escolhe o barbeiro específico, ou escolhe o serviço e o sistema sugere barbeiros disponíveis?
2. Pode agendar com mais de um serviço na mesma visita?
3. Qual o intervalo mínimo entre horários?
4. O cliente precisa de cadastro para agendar?

**Sobre notificação:**
5. As notificações seriam por email, WhatsApp ou só dentro do sistema?
6. Tem lembrete automático de agendamento?

**Sobre o barbeiro:**
7. O barbeiro tem acesso ao próprio calendário com os agendamentos dele?
8. Ele pode bloquear horários?

**Sobre cliente:**
9. Login via email/senha ou OAuth?
10. O cliente pode cancelar o próprio agendamento?

**Desenvolvedor:**
> 1. Sim, eles escolhem o barbeiro, o serviço e os horários que o barbeiro estiver disponível. O fluxo é primeiro o serviço, depois o horário e por fim o barbeiro que está disponível. Porém, caso o cliente queira um barbeiro específico, pode começar pelo barbeiro e ver seus horários.
> 2. Sim, porém para tentar evitar isso cadastrando combos (não sei se deixo isso como uma opção dentro da aplicação ou deixo pro admin cadastrar, sendo algo mais "manual").
> 3. O ideal seria de 45 em 45min, mas acredito que pode ser dinâmico como os combos, por exemplo.
> 4. Ele deve se cadastrar, mas nada muito complexo — somente nome e email. Não acredito que tenha mais alguma informação relevante para esse projeto.
> 5. Seria por email. Conseguir APIs para SMS e WhatsApp.
> 6. Sim, e além disso acredito que deveria ter alguma confirmação de horário.
> 7. Sim, podendo notificar o cancelamento ou pedir para o cliente se adiantar, ou avisar com mensagens rápidas já programadas/cadastradas.
> 8. Pode.
> 9. Vamos manter o básico, só email e senha.
> 10. Sim, ele pode. E não tem prazo.

---

## 3. Levantamento de regras de negócio — rodada 2

**Assistente** levantou novas perguntas:

**Sobre combos:**
1. Faz mais sentido o admin cadastrar os combos (com nome, serviços incluídos, preço especial e tempo total) ou o sistema somar automaticamente?

**Sobre agendamento:**
2. Quando o cliente escolhe serviço → horário → barbeiro, o sistema mostra só os barbeiros disponíveis naquele horário, certo? Se não tiver nenhum disponível, o que acontece?

**Sobre confirmação:**
3. A confirmação seria o cliente recebendo um email com link "Confirmar presença"? E se não confirmar, o que acontece?

**Sobre o barbeiro:**
4. As mensagens rápidas seriam pré-cadastradas pelo admin, pelo próprio barbeiro, ou pelos dois?
5. O barbeiro consegue ver os dados do cliente no agendamento?

**Sobre cliente:**
6. A senha é criada pelo próprio cliente no cadastro? Tem "esqueci minha senha"?

**Sobre fotos:**
7. Quem sobe as fotos — só o admin ou o barbeiro também pode?

**Desenvolvedor:**
> 1. Sim (admin cadastra os combos).
> 2. Traz uma mensagem dizendo que não temos horários disponíveis e pronto.
> 3. Sim, basta clicar no link que será fornecido e ele automaticamente confirma o agendamento, ou então direto pelo site. Tem até 2 horas antes do agendamento para confirmar.
> 4. Pelo sistema (admin).
> 5. Consegue ver o horário, serviço e o nome do cliente.
> 6. Sim, é criado por ele. E tem o recuperar senha. Porém, se precisar, o admin tem como resetar a senha para uma padrão.
> 7. Sobre isso, pensei que em um lugar onde o barbeiro pode expor fotos dos seus cortes como se fosse uma rede social (colocando o serviço que foi feito), deixando sugestões. Além disso, dar ao admin uma gama maior de imagens que ele pode usar em cada serviço.

---

## 4. Levantamento de regras de negócio — rodada 3

**Assistente** levantou as perguntas finais:

**Sobre o portfólio do barbeiro:**
1. As fotos precisam de aprovação do admin antes de aparecer publicamente?
2. O cliente consegue ver o portfólio antes de agendar?

**Sobre agendamento:**
3. Um barbeiro pode atender mais de um cliente ao mesmo tempo?
4. Quando o barbeiro bloqueia um horário, ele coloca um motivo?

**Sobre o admin:**
5. Vai ter só um admin ou podem existir múltiplos?

**Sobre cancelamento:**
6. Quando um agendamento é cancelado, o cliente recebe email?
7. Se o barbeiro bloquear um horário que já tem agendamento, o que acontece?

**Desenvolvedor:**
> 1. Não, vai direto.
> 2. Sim.
> 3. Não.
> 4. Só bloqueia.
> 5. Pode ter mais de um.
> 6. Sim.
> 7. O sistema só permite 4 dias de antecedência para marcar (o cliente), e ele bloqueia a folga do barbeiro. Porém, se ele realmente quiser, o admin pode desmarcar/remarcar permitindo o dia de folga.

---

## 5. Validação do brief

**Assistente** gerou o documento de regras de negócio consolidado. O desenvolvedor validou:

> "A princípio cobre sim."

### Regras de negócio consolidadas

#### Perfis de acesso

**Cliente**
- Cadastro simples: nome, email e senha (criada pelo próprio cliente)
- Recuperação de senha por email
- Visualiza serviços, categorias, descrições e fotos
- Visualiza perfil/portfólio dos barbeiros
- Realiza agendamentos
- Pode cancelar o próprio agendamento sem prazo mínimo
- Confirma presença via link no email ou diretamente no site (até 2h antes)
- Recebe notificações por email

**Barbeiro**
- Escolhe quais serviços do catálogo ele oferece
- Pode sugerir novo serviço via ticket → Admin aprova → entra no catálogo
- Tem agenda própria com seus agendamentos (vê horário, serviço e nome do cliente)
- Pode bloquear horários
- Pode enviar mensagens rápidas pré-cadastradas pelo sistema para clientes
- Posta fotos do portfólio (sem aprovação, vai direto) vinculadas a um serviço

**Admin**
- Pode ter múltiplos admins
- Cadastra/edita/remove barbeiros e carga horária
- Cadastra serviços (nome, categoria, descrição, preço, duração) e combos
- Gerencia clientes: ver detalhes, excluir, resetar senha para padrão
- Gerencia agendamentos: cancelar, notificar cliente com mensagens rápidas
- Aprova tickets de novos serviços sugeridos por barbeiros
- Pode desmarcar/remarcar agendamento mesmo em dia de folga do barbeiro
- Cadastra mensagens rápidas do sistema

#### Serviços e combos
- Organizados por categorias (cabelo, barba, sobrancelha, etc)
- Cada serviço tem: nome, categoria, descrição, preço, duração e fotos
- Combos cadastrados pelo admin: nome, serviços incluídos, preço especial e tempo total
- Novo serviço pode ser sugerido pelo barbeiro via ticket

#### Agendamento

Fluxo padrão: `Serviço → Horário disponível → Barbeiro`
Fluxo alternativo: `Barbeiro → Serviço → Horário disponível`

- Horários gerados dinamicamente pela carga horária do barbeiro + duração do serviço/combo
- Intervalo base de 45min, variável conforme duração do serviço/combo
- Cliente só pode agendar com até **4 dias de antecedência**
- Se não houver horário disponível: exibe mensagem, sem sugestão automática
- Um barbeiro atende um cliente por vez
- Confirmação de presença obrigatória via link ou pelo site até 2h antes
- Se não confirmado: fica como "pendente" (não cancela automaticamente)
- Cliente pode cancelar sem prazo mínimo
- Admin pode forçar bloqueio/desmarcação mesmo com agendamentos

#### Portfólio dos barbeiros
- Barbeiro posta fotos vinculadas a um serviço (sem aprovação prévia)
- Funciona como um feed/rede social do barbeiro
- Visível na página de perfil público do barbeiro
- Admin pode usar essas fotos nos serviços do catálogo
- Cliente pode ver o portfólio antes de agendar

#### Notificações por email

| Evento | Destinatário |
|---|---|
| Agendamento criado | Cliente |
| Lembrete de agendamento | Cliente |
| Confirmação de presença | Cliente |
| Agendamento cancelado | Cliente |
| Mensagem rápida do barbeiro/admin | Cliente |
| Ticket aprovado | Barbeiro |
| Novo ticket para avaliar | Admin |

---

## 6. Identidade visual e nome do projeto

**Desenvolvedor:**
> Vamos criar um nome para o projeto 'barbearia-php'. A identidade visual é algo mais quadrado, tentando evitar centralizar a div, trazendo as coisas para a esquerda, trazendo a ideia de menos cara de IA possível, usando cores neutras como o preto/marrom/dourado/madeira (textura).

**Decisões tomadas:**

- **Nome:** Navalha
- **Paleta:** mogno escuro `#1A1208` + dourado fosco `#B8922A` + off-white quente `#E8DCC8`
- **Tipografia:** Playfair Display (títulos) + IBM Plex Sans (corpo)
- **Layout:** tudo ancorado à esquerda, zero border-radius, borda esquerda dourada como elemento estrutural
- **Textura:** grain sutil via CSS (sem imagem)

---

## 7. Documento técnico de implementação

O assistente gerou o documento técnico completo cobrindo:

- Dockerfile (PHP 8.2 + Apache + extensões)
- Docker Compose (app + db com healthcheck)
- Variáveis de ambiente
- Schema SQL completo com todos os campos, tipos, ENUMs, FKs e índices
- Sistema de autenticação e sessão
- Algoritmo de geração de slots de horário
- Contrato de cada página (GET/POST, guards, queries, o que renderiza)
- Sistema de email com PHPMailer
- Templates de email e variáveis de cada um
- Tokens CSS globais
- Componentes reutilizáveis (header, footer, flash messages, padrão CSRF)
- Checklist de segurança

---

## 8. Organização final do repositório

**Desenvolvedor:**
> Quero uma pasta compactada com os artefatos dentro, além da nossa discussão inteira transcrita em um arquivo markdown, pois o projeto precisa ter um relatório. Ter ordens de como atualizar esse arquivo de discussões para o relatório iria ajudar, além de ordens/instruções para sempre criar testes automatizados para testar cada nova funcionalidade antes de commitar e validar isso, além de ter um arquivo com instruções para separar em etapas (com tasks) para cada coisa que será feita do projeto.

**Resultado:** geração do pacote inicial do repositório com todos os documentos de governança do projeto.

---

*Fim da transcrição de planejamento. A partir daqui o desenvolvimento segue o ROADMAP.md.*
