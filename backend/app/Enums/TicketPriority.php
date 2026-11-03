<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * @return list<string>
     */
    public static function sortOrder(): array
    {
        return [
            self::Urgent->value,
            self::High->value,
            self::Medium->value,
            self::Low->value,
        ];
    }

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
