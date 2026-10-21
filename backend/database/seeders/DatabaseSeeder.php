<?php

namespace Database\Seeders;

use App\Enums\CustomerSegment;
use App\Enums\ProposalStatus;
use App\Enums\TaskPriority;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Administrador',
            'email' => 'admin@rauzee.test',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $alvorada = $this->customer('Imobiliária Alvorada', '11222333000181', 'contato@alvorada.test', CustomerSegment::RealEstate, 'Marina Alves');
        $prata = $this->customer('Corban Prata', '11444777000161', 'financeiro@prata.test', CustomerSegment::Corban, 'Rafael Prata');
        $horizonte = $this->customer('Horizonte Serviços', '52998224725', 'contato@horizonte.test', CustomerSegment::Other, 'Carla Nunes');

        Task::factory()->for($alvorada)->create([
            'created_by' => $admin->id,
            'title' => 'Enviar documentação inicial',
            'description' => 'Reunir contrato social e comprovante de endereço.',
            'due_date' => today()->subDays(3),
            'priority' => TaskPriority::High,
        ]);

        Task::factory()->for($alvorada)->create([
            'created_by' => $admin->id,
            'title' => 'Agendar reunião de alinhamento',
            'description' => 'Confirmar a agenda com a Marina e reservar a sala.',
            'due_date' => today()->addDays(4),
            'priority' => TaskPriority::Medium,
        ]);

        Task::factory()->for($prata)->completed()->create([
            'created_by' => $admin->id,
            'title' => 'Validar dados bancários',
            'description' => 'Conferir agência e conta antes do primeiro repasse.',
            'due_date' => today()->subDays(6),
            'priority' => TaskPriority::High,
        ]);

        Task::factory()->for($horizonte)->create([
            'created_by' => $admin->id,
            'title' => 'Revisar tabela de comissões',
            'description' => 'Aplicar os percentuais novos combinados para o trimestre.',
            'due_date' => today(),
            'priority' => TaskPriority::Low,
        ]);

        $this->proposal($alvorada, $admin, ProposalStatus::Draft, 'Diagnóstico operacional');
        $this->proposal($prata, $admin, ProposalStatus::Sent, 'Assessoria mensal de crédito');
        $this->proposal($horizonte, $admin, ProposalStatus::Accepted, 'Implantação de processos');
        $this->proposal($alvorada, $admin, ProposalStatus::Rejected, 'Revisão documental');

        $boleto = $this->ticket($alvorada, $alvorada->user, $admin, TicketStatus::WaitingCustomer, TicketCategory::Billing, TicketPriority::High,
            'Erro ao emitir boleto',
            'Ao gerar o boleto da mensalidade de outubro, o sistema mostra "falha na comunicação com o banco".');
        $this->comment($boleto, $admin, 'Olá, Marina. Já estamos verificando com o banco. Pode enviar um print da tela de erro?');
        $this->comment($boleto, $alvorada->user, 'O erro aparece logo depois de clicar em "Gerar boleto". Tentei duas vezes hoje de manhã.');
        $this->comment($boleto, $admin, 'Obrigado. Consegue confirmar se o CNPJ do sacado está atualizado no cadastro?');

        $this->ticket($alvorada, $alvorada->user, null, TicketStatus::Open, TicketCategory::Question, TicketPriority::Medium,
            'Como cadastrar um novo corretor?',
            'Contratamos um corretor novo e queremos dar acesso a ele. Qual é o procedimento?');

        $repasse = $this->ticket($prata, $admin, $admin, TicketStatus::InProgress, TicketCategory::Problem, TicketPriority::Urgent,
            'Repasse de comissão não caiu',
            'O repasse previsto para o dia 15 não foi creditado na conta cadastrada.');
        $this->comment($repasse, $admin, 'Identificamos o lote do repasse e estamos conferindo com o financeiro.');

        $relatorio = $this->ticket($horizonte, $horizonte->user, $admin, TicketStatus::Resolved, TicketCategory::Request, TicketPriority::Low,
            'Relatório mensal em planilha',
            'Gostaríamos de receber o relatório mensal também em formato de planilha.');
        $this->comment($relatorio, $admin, 'Pronto, a partir deste mês o relatório vai em PDF e em planilha.');
        $this->comment($relatorio, $horizonte->user, 'Recebemos, muito obrigada!');

        $this->ticket($prata, $prata->user, $admin, TicketStatus::Closed, TicketCategory::Other, TicketPriority::Medium,
            'Atualizar telefone de contato',
            'O telefone do financeiro mudou para (11) 3333-4444.');
    }

    private function customer(string $name, string $document, string $email, CustomerSegment $segment, string $contact): Customer
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::Customer,
        ]);

        return Customer::factory()->for($user)->create([
            'name' => $name,
            'document' => $document,
            'email' => $email,
            'segment' => $segment,
            'contact_name' => $contact,
        ]);
    }

    private function proposal(Customer $customer, User $admin, ProposalStatus $status, string $title): Proposal
    {
        $proposal = Proposal::factory()->state([
            'created_by' => $admin->id,
            'status' => $status,
            'title' => $title,
            'issued_on' => today(),
            'valid_until' => today()->addDays(30),
        ])->when($status === ProposalStatus::Sent, fn ($factory) => $factory->sent())
            ->when($status === ProposalStatus::Accepted, fn ($factory) => $factory->accepted())
            ->when($status === ProposalStatus::Rejected, fn ($factory) => $factory->rejected())
            ->for($customer)
            ->create();

        ProposalItem::factory()->for($proposal)->recurring()->create([
            'description' => 'Acompanhamento mensal',
            'unit_amount_cents' => 50000,
        ]);
        ProposalItem::factory()->for($proposal)->oneTime()->installments(3)->create([
            'description' => 'Implantação inicial',
            'unit_amount_cents' => 100000,
        ]);

        return $proposal;
    }

    private function ticket(Customer $customer, User $openedBy, ?User $assignee, TicketStatus $status, TicketCategory $category, TicketPriority $priority, string $title, string $description): Ticket
    {
        return Ticket::factory()
            ->for($customer)
            ->status($status)
            ->create([
                'opened_by' => $openedBy->id,
                'assigned_to' => $assignee?->id,
                'category' => $category,
                'priority' => $priority,
                'title' => $title,
                'description' => $description,
            ]);
    }

    private function comment(Ticket $ticket, User $author, string $body): TicketComment
    {
        return TicketComment::factory()->for($ticket)->by($author)->create(['body' => $body]);
    }
}
