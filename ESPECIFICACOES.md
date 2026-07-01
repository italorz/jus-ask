# JusAsk — Especificações Completas do Sistema

> Documento de referência para recriação do sistema em **Fastify (API) + Next.js (frontend)**.
> Descreve o **que** o sistema faz, não como está implementado hoje em Laravel/Livewire.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Modelo de Dados](#2-modelo-de-dados)
3. [Multi-tenancy](#3-multi-tenancy)
4. [Autenticação e Autorização](#4-autenticação-e-autorização)
5. [Módulo: Clientes](#5-módulo-clientes)
6. [Módulo: Processos](#6-módulo-processos)
7. [Módulo: CRM / Kanban](#7-módulo-crm--kanban)
8. [Módulo: Notificações](#8-módulo-notificações)
9. [Módulo: Agente IA (Chat)](#9-módulo-agente-ia-chat)
10. [Módulo: WhatsApp](#10-módulo-whatsapp)
11. [Módulo: Site / Blog](#11-módulo-site--blog)
12. [Módulo: Chaves Gemini](#12-módulo-chaves-gemini)
13. [Módulo: Token CNJ](#13-módulo-token-cnj)
14. [Módulo: MCP (API externa)](#14-módulo-mcp-api-externa)
15. [Módulo: Gráficos](#15-módulo-gráficos)
16. [Módulo: Admin](#16-módulo-admin)
17. [Integrações Externas](#17-integrações-externas)
18. [Jobs e Cron](#18-jobs-e-cron)
19. [Variáveis de Ambiente](#19-variáveis-de-ambiente)
20. [Fluxos Completos](#20-fluxos-completos)

---

## 1. Visão Geral

JusAsk é um sistema de gestão de processos jurídicos multi-tenant com:

- **Gestão de clientes e processos** com sincronização automática com a API do PDPJ (Conselho Nacional de Justiça)
- **Agente IA** (Google Gemini) para responder perguntas dos clientes sobre seus processos, via chat web e WhatsApp
- **CRM Kanban** com tarefas, agenda e prazos
- **Notificações** de movimentação processual via sistema interno e WhatsApp
- **Blog/site** para o escritório
- **API MCP** para consultas externas por CNPJ

### Papéis de usuário

| Papel | Acesso |
|---|---|
| `super-admin` | Tudo + painel admin global (empresas, banco de dados) |
| `dono` | Controle total do tenant |
| `advogado` | Acesso completo dentro do tenant |
| `assistente` | Acesso limitado dentro do tenant |

---

## 2. Modelo de Dados

### users
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| name | string | |
| email | string unique | |
| password | string hashed | |
| cpf | string nullable | |
| oab | string nullable | |
| is_super_admin | boolean | default false |

### empresas (tenants)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| nome | string | |
| cnpj | string nullable unique | |
| oab | string nullable | |
| whatsapp | string nullable | número WhatsApp do escritório |
| tenant | string unique | identificador canônico (CNPJ ou OAB) |
| is_pessoa_fisica | boolean | default false |

### membros (pivot users ↔ empresas)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| user_id | FK users | |
| empresa_id | FK empresas | |
| tenant | string indexed | |
| papel | string | `dono` \| `advogado` \| `assistente` |
| ativo | boolean | default true |
| UNIQUE | (user_id, empresa_id) | |

### clientes
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| empresa_id | FK empresas | |
| tenant | string indexed | |
| nome | string | |
| telefone | string nullable | unique por tenant |
| email | string nullable | unique por tenant |
| cpf | string nullable | unique por tenant |
| cnpj | string nullable | unique por tenant |
| tipo | string | `cliente` \| `prospectado` \| `prospeccao` |
| endereco | string nullable | |
| numero | string nullable | |
| bairro | string nullable | |
| cidade | string nullable | |
| estado | string(2) nullable | UF |
| cep | string nullable | |
| chave_gemini_id | FK chaves_gemini nullable | chave específica p/ este cliente |

### processos
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| cliente_id | FK clientes nullable | pode ser null (processos sem cliente fixo) |
| empresa_id | FK empresas | |
| tenant | string indexed | |
| numero | string | número CNJ do processo |
| ativo | boolean | true = monitorando |
| situacao | string nullable | `em_andamento` \| `concluido` |
| tribunal | string nullable | nome do tribunal |
| classe | string nullable | classe processual |
| assunto | string(500) nullable | |
| valor_acao | decimal(15,2) nullable | |
| data_hora_ajuizamento | datetime nullable | |
| data_hora_ultima_distribuicao | datetime nullable | |
| ultimo_movimento | string nullable | descrição |
| ultimo_movimento_codigo | int nullable | código CNJ |
| ultimo_movimento_em | datetime nullable | |
| ultima_atualizacao | datetime nullable | última sincronização com PDPJ |

### processo_cliente (pivot processos ↔ clientes — vínculo extra)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| processo_id | FK processos | |
| cliente_id | FK clientes | |
| empresa_id | FK empresas | |
| tenant | string | |

> Processos podem ter `cliente_id` direto **ou** vínculos via pivot. O agente IA usa ambos.

### processos_conteudos (snapshots da API PDPJ)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| processo_id | FK processos | |
| empresa_id | FK empresas | |
| tenant | string | |
| numero_processo | string nullable | |
| data_hora_ajuizamento | datetime nullable | |
| valor_acao | decimal(15,2) nullable | |
| assunto | string(500) nullable | |
| conteudo_json | longtext | JSON completo da API PDPJ |

### conteudo_processos (anotações manuais do advogado)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| processo_id | FK processos | |
| empresa_id | FK empresas | |
| tenant | string | |
| numero_processo | string | |
| conteudo | text | texto livre do advogado |

### processo_contatos (contatos para notificação)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| processo_id | FK processos | |
| empresa_id | FK empresas | |
| tenant | string | |
| tipo | enum | `email` \| `telefone` |
| valor | string | e-mail ou número |

### notificacoes
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| processo_id | FK processos | |
| empresa_id | FK empresas | |
| tenant | string | |
| titulo | string | |
| mensagem | text | |
| lida | boolean | default false |

### tarefas (CRM Kanban)
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| empresa_id | FK empresas | |
| tenant | string | |
| titulo | string | |
| descricao | text nullable | |
| status | string(20) | `a_fazer` \| `fazendo` \| `concluido` |
| cliente_id | FK clientes nullable | |
| processo_id | FK processos nullable | |
| prazo | date nullable | |
| hora | string(5) nullable | formato HH:MM |
| ordem | int | para drag-and-drop |
| INDEX | (tenant, status) | |

### chaves_gemini
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| empresa_id | FK empresas | |
| tenant | string | |
| apelido | string | nome amigável |
| chave | string | chave da API Gemini (salvar criptografada) |

### token_cnj
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| token | text | JWT do portal PDPJ |
| tenant | string nullable | |

### mcp_tokens
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| user_id | FK users | |
| tenant | string | |
| token_hash | string(64) unique | SHA-256 do token |
| token_preview | string(24) | primeiros 10 + últimos 6 chars |
| last_used_at | datetime nullable | |

### sites
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| empresa_id | FK empresas | |
| tenant | string | |
| titulo | string | |
| slug | string unique | URL do blog |
| descricao | text nullable | |
| publicado | boolean | |

### posts
| Campo | Tipo | Observação |
|---|---|---|
| id | int PK | |
| site_id | FK sites | |
| empresa_id | FK empresas | |
| tenant | string | |
| titulo | string | |
| slug | string | UNIQUE com site_id |
| conteudo | text | |
| publicado | boolean | |
| publicado_em | datetime nullable | |

---

## 3. Multi-tenancy

Cada **Empresa** tem um `tenant` (string única — CNPJ ou OAB sem formatação).

**Isolamento:**
- Todos os dados têm `empresa_id` + `tenant`
- Toda query de dados deve filtrar por `empresa_id` do tenant ativo
- Um usuário pode pertencer a múltiplas empresas (tabela `membros`)
- A empresa ativa na sessão determina quais dados são visíveis

**Troca de empresa:**
- `POST /empresa/trocar` com `empresa_id` no body
- Valida se o usuário é membro ativo da empresa
- Salva `empresa_id` ativa na sessão do usuário

**Rotas tenant-scoped:**
- Prefixo `/{tenant}/` em todas as rotas do painel
- O parâmetro `{tenant}` é resolvido para `Empresa` via lookup no banco
- Middleware valida que o usuário autenticado é membro do tenant

---

## 4. Autenticação e Autorização

### Autenticação Web
- Formulários de login/registro padrão
- Sessão server-side (cookie)
- Sem verificação de e-mail

### Autenticação API (MCP)
- Bearer token: `Authorization: Bearer mcp_<64chars>`
- Ou query param: `?token=mcp_<64chars>`
- Validado por SHA-256 hash contra tabela `mcp_tokens`
- Atualiza `last_used_at` em cada uso

### Autenticação Webhook (WhatsApp)
- Query param: `?token=<EVOLUTION_WEBHOOK_TOKEN>`
- Se `EVOLUTION_WEBHOOK_TOKEN` estiver vazio, webhook é público

### Autorização
- `super-admin`: campo `is_super_admin=true` no user
- Verificação de `papel` para operações sensíveis dentro do tenant
- Rotas admin (`/admin/*`) exigem `super-admin`

---

## 5. Módulo: Clientes

### Listagem (`GET /{tenant}/clientes`)
- Paginação (20 por página)
- Busca em: nome, telefone, email, CPF, CNPJ
- Filtro por tipo: `cliente` | `prospectado` | `prospeccao`
- Colunas: Nome, Tipo (badge colorido), Telefone, E-mail, Ações
- Badge de tipo:
  - `prospeccao` → cinza "Prospecção"
  - `prospectado` → amarelo "Prospectado"
  - `cliente` → verde "Cliente"
- Ação: Editar (vai para tela dedicada) | Excluir

### Exclusão de cliente
- Antes de excluir: mostrar modal de confirmação
- Modal informa quantos processos estão vinculados
- Checkbox: "Excluir também todos os processos vinculados?"
  - Se marcado: deleta processos + registros pivot
  - Se desmarcado: remove `cliente_id` dos processos (desvincula sem apagar)
- Sempre exclui os registros da tabela pivot `processo_cliente`

### Criação e edição (`GET /{tenant}/clientes/novo` e `/{tenant}/clientes/{id}/editar`)

Tela com 3 abas:

#### Aba "Dados"
Campos:
- Tipo (select): `prospeccao` | `prospectado` | `cliente`
- Nome (obrigatório)
- CNPJ
- CPF
- Telefone (único por tenant — usado para identificar no WhatsApp)
- E-mail (único por tenant)
- Endereço, Número, Bairro, Cidade, UF, CEP

Comportamento:
- Ao criar novo cliente → redireciona automaticamente para aba "Processos"
- Validação: telefone, email, cpf únicos por tenant (excluindo o próprio registro em edição)

#### Aba "Processos" (só aparece se cliente já foi salvo)
- Botão "Buscar processos no CNJ" (habilitado só se tiver CPF ou CNPJ)
  - Chama integração com PDPJ via CPF ou CNPJ
  - Processos entram inativos (não monitorados até ativar)
  - Se < 300 processos: síncrono com status de progresso
  - Se >= 300 processos: roda em background com polling a cada 3s
- Tabela de processos do cliente:
  - Colunas: Número, Tribunal, Classe, Situação, Monitorar (toggle), Ações
  - Toggle "Monitorar": ao ativar, busca dados mais recentes no PDPJ
  - Ação: Excluir processo

#### Aba "Agente IA" (só aparece se cliente já foi salvo)
- Select de chave Gemini específica para este cliente
- Opção "Usar padrão do escritório" (sem chave específica)
- Prioridade ao responder no WhatsApp: chave do cliente → chave da empresa → `.env`
- Link para gerenciar chaves

---

## 6. Módulo: Processos

### Listagem (`GET /{tenant}/processos`)
- Paginação (20 por página)
- Busca full-text: número (normaliza dígitos), assunto, tribunal, classe, nome do cliente
- Filtros: Situação (`em_andamento` | `concluido`), Ativo (`sim` | `não`)
- Colunas: Número, Tribunal, Classe, Situação, Última atualização, Ativo, Ações
- Ação: Ver detalhes | Sincronizar | Excluir

### Cadastro de processo
- Campo: Número do processo (formato CNJ)
- Ao salvar: consulta PDPJ, preenche campos automaticamente
- Se processo já existe: atualiza dados

### Sincronização manual
- Botão "Sincronizar" na listagem e na tela de detalhe
- Chama PDPJ API e salva snapshot
- Compara tamanho do JSON: se cresceu → cria notificação
- Pode falhar graciosamente (proxy offline, token expirado)

### Tela de detalhe (`GET /{tenant}/processos/{id}`)

Seções:

#### Dados do processo
- Número, Tribunal, Classe, Situação, Valor da ação
- Data de ajuizamento, Última atualização
- Último movimento (código + descrição + data)
- Cliente vinculado

#### Botão "Sincronizar"
- Força sincronização com PDPJ
- Exibe resultado (atualizado / sem alterações / erro)

#### Snapshots da API (histórico de sincronizações)
- Lista de todos os `processos_conteudos`
- Cada snapshot: data de captura, assunto, valor, movimentos extraídos do JSON

#### Anotações (CRUD)
- Advogado pode criar anotações de texto livre
- Editar / excluir cada anotação

#### Contatos para notificação (CRUD)
- Adicionar contato: tipo (`email` | `telefone`) + valor
- Remover contato
- Estes contatos recebem notificações quando há movimentação

#### Histórico de notificações
- Lista as últimas 20 notificações geradas para este processo
- Marcar como lida

### Lógica de situação processual
Código de movimento determina situação:
- Códigos `[22, 246, 848, 849]` → `concluido`
- Outros → `em_andamento`
- Null → `em_andamento`

---

## 7. Módulo: CRM / Kanban

### Tela (`GET /{tenant}/crm`)

Dois modos de visualização (toggle no topo):

#### Modo Kanban
- 3 colunas: "A fazer" | "Fazendo" | "Concluído"
- Cards podem ser reordenados por drag-and-drop (salva ordem no banco)
- Cards podem ser movidos entre colunas (salva status)
- Card exibe: título, prazo + hora (badge colorido), cliente/processo vinculado
- Cores do badge de prazo:
  - Vermelho: prazo vencido
  - Amarelo: prazo hoje
  - Cinza: prazo futuro

#### Modo Agenda
- Badge no botão Agenda mostra quantidade de tarefas para hoje
- Filtros: Todos | Hoje | Esta semana | Este mês
- Lista agrupada por data (labels: "Hoje", "Amanhã", "Ontem", nome do dia, data formatada)
- "Sem data" fica no final
- Cada item: hora à esquerda, título, badges de status e prazo
- Borda esquerda colorida conforme urgência

### Formulário de tarefa (modal)
Campos:
- Título (obrigatório)
- Descrição
- Status (select)
- Data (date picker)
- Hora (time picker, formato HH:MM)
- Cliente (select opcional)
- Processo (select opcional)

### Ações
- Criar, editar, excluir tarefa
- Mover entre status (drag-and-drop ou clique)
- Reordenar dentro da coluna

---

## 8. Módulo: Notificações

### Tela (`GET /{tenant}/notificacoes`)
- Lista todas as notificações do tenant (paginada, 20 por página)
- Filtro: "Apenas não lidas"
- Badge na interface com total de não lidas
- Colunas: Processo, Título, Data, Status (lida/não lida)

### Ações
- Abrir notificação → exibe título + mensagem em modal, marca como lida automaticamente
- "Marcar todas como lidas"

### Criação de notificações (automático)
Notificação é criada quando:
- Sincronização detecta que JSON do processo cresceu (novos dados)
- Título: "Atualização no processo {número}"
- Mensagem: detalhes da atualização (tamanho anterior vs novo)

---

## 9. Módulo: Agente IA (Chat)

### Chat público (`GET /{tenant}/chat`)

Fluxo:
1. Usuário acessa a URL pública do tenant
2. Tela de identificação: digita número de telefone
3. Sistema busca cliente pelo telefone (matching fuzzy — ignora DDI 55, compara só dígitos)
4. Se encontrado: carrega processos do cliente, inicializa conversa com system prompt
5. Se não encontrado: informa que não encontrou cadastro
6. Interface de chat em tempo real (polling ou websocket)

Comportamento do chat:
- Mensagens convertidas de markdown para HTML (suporte a negrito, listas, etc.)
- Histórico mantido durante a sessão (7 horas)
- Sistema responde em português brasileiro
- IA conhece todos os processos do cliente (nome, número, situação, movimentos, valor, partes)
- Explica termos jurídicos de forma simples para leigos
- Sessão expira após 7 horas sem atividade

### System prompt (contexto da IA)
Construído automaticamente com:
- Dados do cliente (nome, empresa)
- Para cada processo vinculado:
  - Número, tribunal, classe, assunto
  - Situação (em andamento / concluído)
  - Valor da causa
  - Data de ajuizamento
  - Partes e advogados (do JSON PDPJ, se disponível)
  - Todas as movimentações (do snapshot mais recente)
  - Anotações manuais do advogado

### Prioridade de chave Gemini
1. `chave_gemini_id` do cliente específico
2. Qualquer `chave_gemini` da empresa
3. `GEMINI_API_KEY` do `.env`

### Retry automático
- 3 tentativas em erros 429 (quota) e 503 (indisponível)
- Aguarda `Retry-After` do header ou 3s
- Se delay > 60s (cota diária esgotada): desiste imediatamente
- Mensagem de erro diferenciada para cota vs erro técnico

---

## 10. Módulo: WhatsApp

### Webhook recebimento (`POST /{tenant}/webhooks/whatsapp` ou `POST /webhooks/whatsapp`)

Parâmetros recebidos (Evolution API):
```json
{
  "event": "messages.upsert",
  "data": {
    "key": {
      "remoteJid": "5514999999999@s.whatsapp.net",
      "fromMe": false
    },
    "message": {
      "conversation": "texto da mensagem"
    }
  }
}
```

Filtragem:
- Ignora eventos diferentes de `messages.upsert` e `MESSAGES_UPSERT`
- Ignora mensagens `fromMe: true` (enviadas pelo bot)
- Ignora grupos (`@g.us` no remoteJid)
- Ignora mensagens sem texto (áudio, imagem sem legenda, etc.)
- Aceita texto de: `conversation`, `extendedTextMessage.text`, `imageMessage.caption`, `videoMessage.caption`

### Fluxo de resposta automática

**Identificação do cliente:**
1. Extrai número do JID: `5514996440809@s.whatsapp.net` → `14996440809`
   - Remove sufixo `@s.whatsapp.net`
   - Remove DDI `55` se o número tiver 12+ dígitos
2. Busca `Cliente` com este telefone (normaliza dígitos nos dois lados)
3. Se encontrado: **Modo Cliente** — usa processos como contexto

**Modo Cliente (cliente identificado pelo telefone):**
- Carrega/cria sessão Redis com:
  - `cliente_id`, `empresa_id`
  - `system_prompt` (gerado com todos os processos do cliente)
  - `history` (últimos 20 pares de mensagens)
  - `prompt_em` (timestamp de quando o prompt foi gerado)
- Regenera system prompt automaticamente após 1 hora (capta novos processos)
- Chama `GeminiService::chat()` com o histórico completo
- Salva a nova mensagem no histórico

**Modo Fallback (cliente não identificado):**
- Tenta extrair número CNJ do texto da mensagem
- Se encontrado: consulta PDPJ API diretamente e responde com dados do processo
- Se não encontrado: pede que o usuário informe o número do processo

**Envio da resposta:**
1. Envia indicador "digitando..." (`composing`) via Evolution API
2. Envia texto da resposta via Evolution API

### Sessão Redis
- Chave: `whatsapp_atendimento:{sha1(tenant|remoteJid)}`
- TTL: 7 horas
- Conteúdo: `{ cliente_id, empresa_id, system_prompt, history[], prompt_em }`
- Máximo 20 pares de mensagens no histórico (20 user + 20 model = 40 itens)

### Envio de notificações pelo escritório
Quando uma nova movimentação é detectada na sincronização:
- Verifica se o processo tem contatos cadastrados com `tipo='telefone'`
- Para cada contato: envia mensagem via Evolution API
- Mensagem inclui: número do processo, descrição da atualização

---

## 11. Módulo: Site / Blog

### Gerenciar site (`GET /{tenant}/site`)

Cada empresa pode ter **um site/blog** associado.

**Configurações do site:**
- Título
- Slug (URL pública: `/blog/{slug}`)
- Descrição
- Publicado (on/off)

**CRUD de Posts:**
- Título, Slug (único por site), Conteúdo (texto rico), Publicado, Data de publicação

### Blog público
- `GET /blog/{site:slug}` → lista posts publicados do site
- `GET /blog/{site:slug}/{post:slug}` → post individual
- Sem autenticação, acesso público

---

## 12. Módulo: Chaves Gemini

### Tela (`GET /{tenant}/chaves-gemini`)

CRUD de chaves de API do Google Gemini para o tenant.

Campos por chave:
- Apelido (nome amigável, ex: "Principal", "Projeto X")
- Chave da API (salvar de forma segura — nunca retornar em claro via API)

Exibição:
- Chave mascarada: `AIzaSy***...***xQbT` (4 primeiros + asteriscos + 4 últimos)

Uso:
- Chave pode ser vinculada a clientes específicos (via `chave_gemini_id` no cliente)
- Se nenhuma chave vinculada: usa a primeira chave do tenant
- Se nenhuma no tenant: usa `GEMINI_API_KEY` do `.env`

---

## 13. Módulo: Token CNJ

### Tela de visualização (`GET /{tenant}/token-cnj-atual`)
- Exibe o token CNJ ativo (JWT, mascarado)
- Data de criação e expiração (decodifica claim `exp` do JWT)
- Status: válido / expirado

### Cadastro via link público (`GET /{tenant}/token-cnj`)
- URL enviada ao usuário para que ele cole o token do portal PDPJ
- Valida que o token não está vazio
- Salva na tabela `token_cnj` com o tenant

**Por que é público:** o token é gerado pelo portal do CNJ e precisa ser colado pelo usuário. O link pode ser enviado via e-mail ou WhatsApp para que o usuário não precise fazer login no sistema.

### Resolução do token
Prioridade:
1. Registro mais recente na tabela `token_cnj` para o tenant
2. Variável de ambiente `TOKEN_API_PROCESSO`

---

## 14. Módulo: MCP (API externa)

### Tela de gestão (`GET /{tenant}/mcp`)
- Exibe token atual (preview: primeiros 10 + últimos 6 chars)
- Data de último uso
- Botão "Regenerar token"
- Ao regenerar: mostra o token completo **uma única vez** (não fica salvo em claro)
- Exibe URL do endpoint para integração externa

### Endpoint de consulta (`POST /mcp/processos`)
Autenticação: Bearer token ou `?token=`

Request body:
```json
{
  "cnpj": "12345678000199",
  "pagina": 1,
  "por_pagina": 50,
  "atualizar": false
}
```

Response:
```json
{
  "status": "done",
  "dados": [...],
  "total": 150,
  "pagina": 1,
  "por_pagina": 50
}
```

Comportamento:
- Consulta PDPJ pelo CNPJ informado
- Se ≤ 300 resultados: retorna síncrono
- Se > 300: agenda job em background, retorna `status: "processing"`
- Resultados ficam no banco, consultas subsequentes leem do banco

---

## 15. Módulo: Gráficos

### Tela (`GET /{tenant}/graficos`)

Gráfico de barras: processos abertos por mês.

Filtros:
- Período: últimos 6, 12, 24 meses
- Situação: Todos | Em andamento | Concluídos
- Ativo: Todos | Monitorando | Não monitorando
- Tribunal: select com todos os tribunais do tenant

Dados retornados:
- Labels (nomes dos meses)
- Valores (quantidade por mês)
- Total geral

---

## 16. Módulo: Admin

### Lista de empresas (`GET /admin/empresas`) — super-admin only
- Lista todas as empresas cadastradas no sistema
- Dados: nome, CNPJ, tenant, membros

### Explorador de banco (`GET /admin/db`) — super-admin only
- Painel esquerdo: lista de todas as tabelas do banco
- Clicar na tabela: mostra estrutura (colunas, tipos, nullable, default) + pré-preenche SELECT
- Editor SQL: executa queries `SELECT` only
- Bloqueios: `INSERT`, `UPDATE`, `DELETE`, `DROP`, `TRUNCATE`, `ALTER`, `CREATE`, `EXEC`, `GRANT`, `REVOKE`
- Resultado em tabela com truncamento de valores > 120 chars

---

## 17. Integrações Externas

### PDPJ API (CNJ)
Base URL: `https://portaldeservicos.pdpj.jus.br/api/v2/processos`

**Autenticação:** Bearer token (JWT do portal CNJ, salvo no banco)

**Endpoints usados:**
- `GET /processos?numero={numero_cnj}` → dados de um processo específico
- `GET /processos?documento={cnpj}&searchAfter={cursor}&size=50` → busca paginada por CNPJ

**Retry:** 3 tentativas com backoff em erro 429

**Proxy:** `PDPJ_PROXY_URL` (opcional, para ambientes com restrição de rede)

**Estrutura do JSON de retorno (relevante):**
```json
{
  "hits": {
    "hits": [{
      "_source": {
        "numeroProcesso": "...",
        "dataHoraAjuizamento": "...",
        "valorTotal": 10000.00,
        "assuntos": [{"nome": "..."}],
        "movimentos": [{
          "codigo": 848,
          "nome": "...",
          "dataHora": "..."
        }],
        "orgaoJulgador": {"nome": "...", "tribunal": "..."},
        "partes": [{
          "nome": "...",
          "polo": "ATIVO|PASSIVO",
          "advogados": [{"nome": "...", "numeroOAB": "..."}]
        }]
      }
    }],
    "total": {"value": 150},
    "sort": [...]
  }
}
```

### Google Gemini AI
Base URL: `https://generativelanguage.googleapis.com/v1beta/models/`

**Modelo:** `gemini-2.5-flash` (ou conforme `GEMINI_MODEL`)

**Endpoint:** `POST /models/{model}:generateContent?key={api_key}`

**Payload:**
```json
{
  "system_instruction": {
    "parts": [{"text": "system prompt aqui"}]
  },
  "contents": [
    {"role": "user", "parts": [{"text": "..."}]},
    {"role": "model", "parts": [{"text": "..."}]},
    {"role": "user", "parts": [{"text": "mensagem atual"}]}
  ],
  "generationConfig": {
    "thinkingConfig": {"thinkingBudget": 0}
  }
}
```

**Retry:** 3 tentativas em 429/503, respeita `Retry-After`, desiste se delay > 60s

### Evolution API (WhatsApp)
Base URL: `EVOLUTION_API_URL` (ex: `http://evolution-api:8080`)

**Autenticação:** Header `apikey: {EVOLUTION_API_KEY}`

**Instância:** `EVOLUTION_INSTANCE`

**Endpoints usados:**

`POST /message/sendText/{instance}`
```json
{
  "number": "5514999999999",
  "text": "mensagem aqui"
}
```

`POST /chat/sendPresence/{instance}`
```json
{
  "number": "5514999999999@s.whatsapp.net",
  "options": {"presence": "composing", "delay": 3000}
}
```

---

## 18. Jobs e Cron

### Job: ConsultarProcessosCnpjJob
- **Trigger:** quando busca PDPJ retorna > 300 processos (fila `default`)
- **Timeout:** 40 minutos
- **Tentativas:** 1 (sem retry automático)
- **Parâmetros:** `cnpj`, `tenant`, `clienteId` (opcional)
- **Comportamento:** coleta todos os processos página a página com throttling

**Throttling configurável:**
| Total de resultados | Delay entre páginas |
|---|---|
| 0–500 | sem delay |
| 501–2000 | 3000ms |
| 2001–5000 | 4000ms |
| 5000+ | 5000ms |

**Status salvo no cache (Redis):**
- `processos_coleta:{cnpj}:{tenant}` → `{status, coletados, total}`
- Estados: `processing` | `done` | `cancelado` | `error`

### Cron: Verificar Processos
- **Endpoint:** `GET /processos/verificar?token={PROCESSOS_VERIFICACAO_TOKEN}`
- **Frequência sugerida:** a cada 1–4 horas
- **Comportamento:**
  1. Busca todos os processos com `ativo=true`
  2. Pula processos verificados há menos de `PDPJ_VERIFY_COOLDOWN_HORAS` horas
  3. Para cada processo:
     - Chama PDPJ API
     - Compara tamanho do JSON (novo vs salvo)
     - Se cresceu: salva snapshot, cria notificação, envia WhatsApp
  4. Throttling entre processos:
     - Delay de `PDPJ_VERIFY_DELAY_MS` entre cada um
     - A cada `PDPJ_VERIFY_PAUSA_APOS` processos: pausa de `PDPJ_VERIFY_PAUSA_MS`

---

## 19. Variáveis de Ambiente

```env
# Banco de dados
DATABASE_URL=postgresql://user:pass@host:5432/dbname

# Redis (sessões, cache, filas)
REDIS_URL=redis://host:6379

# PDPJ API
TOKEN_API_PROCESSO=eyJhbGc...          # fallback se não houver no banco
PDPJ_PROXY_URL=http://proxy:8080       # opcional
PDPJ_VERIFY_DELAY_MS=3000
PDPJ_VERIFY_PAUSA_APOS=10
PDPJ_VERIFY_PAUSA_MS=20000
PDPJ_VERIFY_COOLDOWN_HORAS=4

# Cron de verificação
PROCESSOS_VERIFICACAO_TOKEN=secret123

# Gemini AI
GEMINI_API_KEY=AIzaSy...               # fallback se não houver no banco
GEMINI_MODEL=gemini-2.5-flash          # modelo padrão

# WhatsApp (Evolution API)
EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=4F5DC6A6-...
EVOLUTION_INSTANCE=notificacoes
EVOLUTION_WEBHOOK_TOKEN=secret123      # proteção do webhook

# App
APP_URL=https://app.exemplo.com
JWT_SECRET=...                         # para sessões
SESSION_SECRET=...
```

---

## 20. Fluxos Completos

### Fluxo A: Cadastro de novo cliente + busca de processos no CNJ

```
1. Advogado acessa /{tenant}/clientes/novo
2. Preenche dados (nome, tipo, CPF/CNPJ, telefone)
3. Salva → cliente criado → redireciona para aba "Processos"
4. Clica "Buscar processos no CNJ"
5. Sistema chama PDPJ com o CPF ou CNPJ
6. Se ≤ 300 resultados:
   - Salva cada processo com ativo=false
   - Retorna lista imediatamente
7. Se > 300 resultados:
   - Despacha job em background
   - UI faz polling a cada 3s exibindo progresso
   - Job conclui → UI mostra "X processos salvos"
8. Advogado ativa os processos que quer monitorar (toggle)
9. Ao ativar: busca dados mais recentes do processo no PDPJ
```

### Fluxo B: Sincronização automática de processos (cron)

```
1. GET /processos/verificar é chamado (cron externo, ex: crontab, Coolify cron)
2. Sistema busca todos os processos com ativo=true
3. Para cada processo:
   a. Verifica cooldown (pula se verificado há menos de N horas)
   b. Chama PDPJ API com o número do processo
   c. Se erro de conexão: pula graciosamente, loga
   d. Se sucesso: compara tamanho do JSON novo vs salvo
   e. Se cresceu (novos dados):
      - Salva novo snapshot em processos_conteudos
      - Atualiza campos em processos (ultimo_movimento, situacao, etc.)
      - Cria registro em notificacoes
      - Para cada contato do processo com tipo=telefone:
        → Envia mensagem WhatsApp via Evolution API
   f. Aguarda PDPJ_VERIFY_DELAY_MS antes do próximo
   g. A cada N processos: pausa longa
```

### Fluxo C: Cliente envia mensagem no WhatsApp

```
1. Evolution API recebe mensagem do cliente
2. Evolution faz POST no webhook configurado
3. Sistema valida token do webhook
4. Filtra: ignora fromMe, grupos, mensagens sem texto
5. Extrai remoteJid → normaliza telefone → ex: "14996440809"
6. Busca cliente pelo telefone no banco (por tenant ou global)
7. Se cliente encontrado:
   a. Verifica sessão Redis
   b. Se sessão nova ou expirada:
      - Carrega processos do cliente (direto + via pivot)
      - Gera system prompt com todos os processos
      - Salva sessão no Redis (TTL 7h)
   c. Se system prompt tem > 1 hora: regenera automaticamente
   d. Envia "digitando..." para o WhatsApp
   e. Chama Gemini API com histórico completo
   f. Salva resposta no histórico da sessão
   g. Envia resposta via Evolution API
8. Se cliente NÃO encontrado:
   a. Tenta extrair número CNJ do texto
   b. Se encontrou número: consulta PDPJ diretamente
   c. Se não: responde pedindo o número do processo
```

### Fluxo D: Geração e uso do token MCP

```
1. Advogado acessa /{tenant}/mcp
2. Clica "Gerar novo token"
3. Sistema cria token: "mcp_" + 64 chars aleatórios
4. Calcula SHA-256 do token completo → salva no banco
5. Exibe token completo UMA vez na tela (não fica salvo em claro)
6. Advogado copia e configura em ferramenta externa (n8n, Make, etc.)

--- Uso externo ---
7. Ferramenta chama POST /mcp/processos
   Authorization: Bearer mcp_abc123...
   { "cnpj": "12345678000199", "pagina": 1 }
8. Sistema calcula SHA-256 do token recebido
9. Busca no banco o hash correspondente
10. Obtém tenant do token → aplica filtros de isolamento
11. Consulta PDPJ ou lê do banco
12. Retorna JSON paginado com processos
```

### Fluxo E: Chat público no site do escritório

```
1. Cliente acessa /{tenant}/chat (URL pública)
2. Vê tela de identificação — digita seu telefone
3. Sistema normaliza dígitos, busca cliente no banco do tenant
4. Se encontrado:
   - Exibe nome do cliente
   - Inicializa sessão com system prompt (todos os processos)
   - Envia mensagem oculta de trigger para "aquecer" a conversa
   - AI responde com saudação personalizada
5. Cliente digita perguntas → chat em tempo real
6. Sistema mantém histórico no estado da sessão (7 horas)
7. AI responde sempre no contexto dos processos do cliente
```

---

## Regras de negócio importantes

### Normalização de telefone
- Comparação sempre por dígitos apenas (remove tudo que não é número)
- Remove DDI `55` se o número tiver 12+ dígitos
- Exemplo: `(14) 99644-0809` → `14996440809`
- JID WhatsApp `5514996440809@s.whatsapp.net` → `14996440809`

### Vínculos de processo com cliente
Um processo pode estar vinculado a um cliente de duas formas:
1. Campo direto `processos.cliente_id`
2. Registro na tabela pivot `processo_cliente`

O sistema IA deve considerar **ambos** ao construir o contexto.

### Mascaramento da chave Gemini
- Nunca retornar a chave completa em APIs ou interfaces
- Exibir: `ABCD***...***WXYZ` (4 primeiros + asteriscos + 4 últimos)

### Snapshots de processo
- Cada sincronização bem-sucedida salva o JSON completo em `processos_conteudos`
- O sistema detecta mudança pelo **tamanho** do JSON (bytes), não por diff de conteúdo
- Se novo JSON é maior → considera que há novidades → notifica

### Cooldown de verificação
- Cada processo tem timestamp da última verificação
- Cron pula processos verificados há menos de `PDPJ_VERIFY_COOLDOWN_HORAS` horas
- Evita sobrecarregar a API do CNJ

### Token CNJ (PDPJ)
- Token JWT com expiração de ~7 horas (definido pelo portal CNJ)
- Precisa ser renovado manualmente pelo advogado no portal do CNJ
- Sistema detecta token expirado via claim `exp` do JWT
- Sem token válido: sincronizações e buscas falham graciosamente (não travam o sistema)
