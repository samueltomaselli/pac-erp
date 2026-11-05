# Arquitetura

Monorepo com duas aplicações:

- `backend/`: API em Laravel 12 (PHP 8.3+), MySQL em produção e SQLite nos testes.
- `frontend/`: SPA em Vue 3 com Vite e Tailwind CSS.

A SPA conversa com a API por sessão (Laravel Sanctum, cookie de sessão + CSRF). Há dois papéis: `admin` (equipe interna) e `customer` (cliente), separados por middleware nas rotas.

## Backend

Cada requisição passa pelas mesmas camadas:

| Camada | Pasta | Responsabilidade |
|---|---|---|
| Rotas | `routes/api.php` | Grupos por papel (`role:admin`, `role:customer`) e rotas públicas da proposta |
| Form Requests | `app/Http/Requests` | Validação de entrada com mensagens em português |
| Controllers | `app/Http/Controllers` | Recebem a requisição, chamam a regra e devolvem o Resource |
| Actions | `app/Actions` | Regra de negócio isolada, uma classe por operação (criar cliente, transicionar proposta, abrir chamado...) |
| Policies | `app/Policies` | Quem pode ver e alterar cada registro, principalmente chamados e anexos |
| Models e Enums | `app/Models`, `app/Enums` | Relacionamentos, casts e os valores possíveis de status, prioridade, tipo e segmento |
| Resources | `app/Http/Resources` | Formato do JSON devolvido, sem expor caminhos de arquivo |

### Regras transversais

- **Status só muda por Action.** Proposta e chamado não aceitam `status` no formulário; as transições válidas ficam nas Actions `TransitionProposalStatus` e `TransitionTicketStatus`, e a API devolve as transições permitidas para a tela montar os botões.
- **Dinheiro em centavos inteiros.** Valores, descontos e parcelas são `bigint` em centavos.
- **Fuso America/Sao_Paulo.** Prazos vencidos e validade de propostas são calculados no horário de Brasília.
- **Arquivos em disco privado.** Anexos de chamados e assinaturas de propostas não ficam em pasta pública; saem só por rota autenticada.
- **Rotina agendada.** `proposals:expire` roda todos os dias às 03:00 e marca como vencidas as propostas enviadas fora da validade.

## Frontend

| Pasta | Conteúdo |
|---|---|
| `src/pages` | Telas da equipe (`Admin/`), do cliente (`Customer*.vue`), autenticação e a proposta pública |
| `src/components` | Componentes compartilhados: layout com menu lateral, botões, etiquetas, gavetas, modais, avisos, assinatura |
| `src/utils/api` | Uma função por endpoint da API |
| `src/stores` | Estado de autenticação (Pinia) |
| `src/constants` | Rótulos e cores de status, prioridade e tipos |
| `src/router` | Rotas com guarda por papel |

### Padrão visual

- Paleta da marca em 10 tons de azul-petróleo (`--theme-light-50` a `--theme-light-900`, principal `#19819d`), definida em `src/style.css`.
- Cores de status com significado fixo: verde concluído/aceito, azul e azul-claro em andamento, âmbar aguardando, vermelho atrasado ou urgente.
- Tipografia: Sora nos títulos, Inter no texto, IBM Plex Mono em códigos e documentos.
- Layout único: menu lateral (240 px, recolhe para 64 px) com etiqueta da área (Equipe ou Área do cliente), cabeçalho fixo com título e ações, conteúdo em painéis.

## Testes

Suíte em PHPUnit no backend (`php artisan test`), com testes de feature por módulo: clientes, propostas, tarefas, chamados, isolamento entre clientes e fluxo público da proposta.
