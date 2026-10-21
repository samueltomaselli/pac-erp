<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Question = 'question';
    case Problem = 'problem';
    case Request = 'request';
    case Billing = 'billing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Question => 'Dúvida',
            self::Problem => 'Problema',
            self::Request => 'Solicitação',
            self::Billing => 'Financeiro',
            self::Other => 'Outro',
        };
    }
}
