# Modelagem de dados (DER)

Modelo derivado das migrations em `backend/database/migrations`. Tabelas de infraestrutura do Laravel (sessions, cache, jobs, tokens) ficam de fora.

11 tabelas de domínio, 16 migrations.

```mermaid
erDiagram
    users ||--o{ customers : "user_id (login do cliente)"
    users ||--o{ tasks : "created_by"
    users ||--o{ proposals : "created_by"
    users ||--o{ tickets : "opened_by / assigned_to"
    users ||--o{ ticket_comments : "user_id"
    users ||--o{ ticket_attachments : "uploaded_by"
    customers ||--o{ tasks : "customer_id"
    customers ||--o{ proposals : "customer_id"
    customers ||--o{ tickets : "customer_id"
    proposals ||--o{ proposal_items : "proposal_id"
    proposal_catalog_items |o--o{ proposal_items : "catalog_item_id"
    proposals ||--o| proposal_acceptances : "proposal_id (único)"
    tickets ||--o{ ticket_comments : "ticket_id"
    tickets ||--o{ ticket_attachments : "ticket_id"
    ticket_comments |o--o{ ticket_attachments : "comment_id"

    users { bigint id PK
        string name
        string email UK
        string password
        string role "admin | customer" }
    customers { bigint id PK
        bigint user_id FK
        string name
        string document UK
        string email
        string phone
        string contact_name
        string segment
        string status "active | inactive"
        timestamp deleted_at "soft delete" }
    tasks { bigint id PK
        bigint customer_id FK
        bigint created_by FK
        string title
        text description
        date due_date
        string priority
        string status
        timestamp completed_at }
    proposals { bigint id PK
        uuid pub_id UK "link público"
        bigint customer_id FK
        bigint created_by FK
        string title
        date issued_on
        date valid_until
        string payment_method
        text observations
        text terms
        string status "máquina de estados"
        timestamp sent_at
        timestamp decided_at }
    proposal_items { bigint id PK
        bigint proposal_id FK
        bigint catalog_item_id FK
        string type "one_time | recurring"
        string description
        int quantity
        bigint unit_amount_cents
        bigint discount_cents
        tinyint installments }
    proposal_catalog_items { bigint id PK
        string name
        string type
        int default_quantity
        bigint default_unit_amount_cents
        bool allows_installments
        bool is_active
        smallint sort_order }
    proposal_templates { bigint id PK
        string name
        string type
        text content }
    proposal_acceptances { bigint id PK
        bigint proposal_id FK,UK
        string name
        string document
        string email
        string ip_address
        string signature_path "PNG em disco privado"
        timestamp accepted_at }
    tickets { bigint id PK
        bigint customer_id FK
        bigint opened_by FK
        bigint assigned_to FK
        string title
        string category
        text description
        string priority
        string status "máquina de estados"
        timestamp resolved_at
        timestamp closed_at }
    ticket_comments { bigint id PK
        bigint ticket_id FK
        bigint user_id FK
        text body }
    ticket_attachments { bigint id PK
        bigint ticket_id FK
        bigint comment_id FK
        bigint uploaded_by FK
        string disk "privado"
        string path
        string original_name
        string mime_type
        bigint size }
```

## Decisões de modelagem
- Dinheiro sempre em centavos inteiros (`*_cents`), sem float.
- Status de proposta e de chamado não estão no `$fillable`: só mudam pelas Actions de transição.
- Cliente com exclusão lógica (`deleted_at`), preservando histórico de propostas e chamados.
- Plano atual do cliente não é coluna: é derivado da proposta aceita mais recente.
- Aceite público identificado por `pub_id` (UUID), nunca pelo id sequencial.
- Assinatura do aceite guardada como arquivo em disco privado; o banco guarda só o caminho.
- Anexos de chamado também em disco privado, servidos por rota autenticada que aplica a mesma regra de acesso do chamado.
- Índices compostos nas consultas de fila: `(customer_id, status)`, `(status, priority)`, `(status, valid_until)`, `(status, due_date)`.
