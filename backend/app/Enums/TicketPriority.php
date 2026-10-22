<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgente',
            self::High => 'Alta',
            self::Medium => 'Média',
            self::Low => 'Baixa',
        };
    }
}
