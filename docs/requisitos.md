# Requisitos funcionais

Escopo acordado com a organização parceira: 25 requisitos funcionais, priorizados por MoSCoW (20 Must e 5 Should).

## Situação em 05/11/2026

| ID | Descrição | Prioridade | Sprint | Status |
|---|---|---|---|---|
| RF-01 | Cadastrar cliente (nome, CNPJ/CPF, contatos, status, segmento) e gerar credencial | Must | S2 | Entregue |
| RF-02 | Editar cliente | Must | S2 | Entregue |
| RF-03 | Listar clientes com busca por nome ou CNPJ/CPF | Must | S2 | Entregue |
| RF-04 | Filtrar clientes por status e vínculo com parceiro | Must | S2 | Entregue com ajuste: filtro por status e segmento (não há entidade Parceiro no sistema) |
| RF-05 | Detalhe do cliente com propostas, tarefas e chamados | Must | S2, S4 e S7 | Entregue |
| RF-06 | Inativar cliente (exclusão lógica) | Should | S2 | Entregue |
| RF-07 | Criar proposta (título, emissão, validade) | Must | S3 | Entregue |
| RF-08 | Itens da proposta | Must | S3 | Entregue |
| RF-09 | Forma de pagamento | Must | S3 | Entregue |
| RF-10 | Status da proposta com máquina de estados | Must | S3 | Entregue |
| RF-11 | Histórico de propostas do cliente | Should | S4 | Entregue |
| RF-12 | Vencimento automático (job agendado) | Should | S4 | Entregue |
| RF-13 | Criar tarefa vinculada a cliente | Must | S2 | Entregue |
| RF-14 | Prioridade da tarefa | Must | S2 | Entregue |
| RF-15 | Concluir tarefa com data e hora | Must | S2 | Entregue |
| RF-16 | Listar tarefas com filtro por cliente e prazo | Must | S2 | Entregue |
| RF-17 | Destaque de tarefa vencida | Should | S2 | Entregue |
| RF-18 | Abrir chamado (admin ou cliente) | Must | S5-6 | Entregue |
| RF-19 | Prioridade do chamado | Must | S5-6 | Entregue |
| RF-20 | Responsável interno (só admin) | Must | S5-6 | Entregue |
| RF-21 | Ciclo de status do chamado (máquina de estados) | Must | S5-6 | Entregue |
| RF-22 | Comentários com autor e data, incluindo o cliente | Must | S5-6 | Entregue |
| RF-23 | Anexos no chamado e nas interações | Should | S5-6 | Entregue |
| RF-24 | Fila filtrável por status, prioridade, responsável e cliente | Must | S7 | Entregue |
| RF-25 | Datas de abertura, atualização e fechamento | Must | S5-6 e S7 | Entregue |

25 de 25 requisitos entregues. Meta da entrega parcial (N2): 70%.

## Decisões de escopo

| Requisito | Pedido original | Como ficou | Motivo |
|---|---|---|---|
| RF-04 | Filtrar clientes por status e vínculo com parceiro | Filtro por status e segmento | O sistema não tem cadastro de parceiro; o segmento separa a carteira |
| RF-10 | Controlar o status da proposta | Transições válidas: rascunho → enviada → aceita, recusada ou vencida | Impede, por exemplo, uma proposta aceita voltar a rascunho |
| RF-10 | Aceite da proposta | O cliente aceita e assina pelo link público; a recusa é registrada pela equipe | O aceite fica com nome, documento, e-mail, IP, data e assinatura |
| RF-12 | Marcar propostas vencidas | Rotina agendada todos os dias às 03:00 | Ninguém precisa conferir a validade manualmente |
| RF-18 | Cliente abre chamado | Nasce com prioridade média e sempre no cadastro do próprio cliente | A prioridade é definida pela equipe |
| RF-21 | Ciclo de status do chamado | "Aguardando cliente" volta sozinho para "em atendimento" quando o cliente responde | A equipe não precisa mudar o status manualmente |

## Sprints

| Sprint | Período | Entrega |
|---|---|---|
| S1 | 13/08 – 26/08 | Base, autenticação e papéis |
| S2 | 27/08 – 09/09 | Clientes (RF-01 a RF-06) e tarefas (RF-13 a RF-17) |
| S3 | 10/09 – 23/09 | Propostas I (RF-07 a RF-10) |
| S4 | 24/09 – 07/10 | Propostas II (RF-11, RF-12 e link público) |
| S5 e S6 | 08/10 – 22/10 | Chamados (RF-18 a RF-23) |
| S7 | 23/10 – 05/11 | Fila de chamados e detalhe do cliente (RF-24, RF-25 e RF-05) |
| S8 | 19/11 – 26/11 | Refinamento, implantação e documentação final |
