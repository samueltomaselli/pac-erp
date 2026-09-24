<?php

namespace App\Enums;

enum ProposalItemType: string
{
    case Recurring = 'recurring';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'Recorrente',
            self::OneTime => 'Pontual',
        };
    }
}
