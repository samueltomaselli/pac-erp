<?php

namespace Tests\Unit\Enums;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketStatusTest extends TestCase
{
    #[DataProvider('allowedTransitionsProvider')]
    public function test_allowed_transitions_match_the_state_machine(TicketStatus $status, array $expected): void
    {
        $this->assertSame($expected, array_map(fn ($item) => $item->value, $status->allowedTransitions()));
    }

    public static function allowedTransitionsProvider(): array
    {
        return [
            [TicketStatus::Open, ['in_progress']],
            [TicketStatus::InProgress, ['waiting_customer', 'resolved']],
            [TicketStatus::WaitingCustomer, ['in_progress', 'resolved']],
            [TicketStatus::Resolved, ['closed', 'in_progress']],
            [TicketStatus::Closed, []],
        ];
    }

    public function test_only_closed_is_final(): void
    {
        $final = array_values(array_filter(
            TicketStatus::cases(),
            fn (TicketStatus $status) => $status->isFinal(),
        ));

        $this->assertSame([TicketStatus::Closed], $final);
    }

    public function test_labels_are_in_portuguese(): void
    {
        $this->assertSame(
            ['Aberto', 'Em atendimento', 'Aguardando cliente', 'Resolvido', 'Fechado'],
            array_map(fn (TicketStatus $status) => $status->label(), TicketStatus::cases()),
        );
        $this->assertSame(
            ['Urgente', 'Alta', 'Média', 'Baixa'],
            array_map(fn (TicketPriority $priority) => $priority->label(), TicketPriority::cases()),
        );
        $this->assertSame(
            ['Dúvida', 'Problema', 'Solicitação', 'Financeiro', 'Outro'],
            array_map(fn (TicketCategory $category) => $category->label(), TicketCategory::cases()),
        );
    }
}
