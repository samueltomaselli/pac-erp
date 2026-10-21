<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::InProgress => 'Em atendimento',
            self::WaitingCustomer => 'Aguardando cliente',
            self::Resolved => 'Resolvido',
            self::Closed => 'Fechado',
        };
    }
}
