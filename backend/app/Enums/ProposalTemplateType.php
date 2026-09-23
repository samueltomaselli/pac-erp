<?php

namespace App\Enums;

enum ProposalTemplateType: string
{
    case Observations = 'observations';
    case GeneralConditions = 'general_conditions';

    public function label(): string
    {
        return match ($this) {
            self::Observations => 'Observações',
            self::GeneralConditions => 'Condições gerais',
        };
    }
}
