<?php

namespace App\Enums;

enum CustomerSegment: string
{
    case Corban = 'corban';
    case RealEstate = 'real_estate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Corban => 'Corban',
            self::RealEstate => 'Imobiliária',
            self::Other => 'Outro',
        };
    }
}
