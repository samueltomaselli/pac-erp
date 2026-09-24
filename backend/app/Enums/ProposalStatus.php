<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviada',
            self::Accepted => 'Aceita',
            self::Rejected => 'Recusada',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Accepted, self::Rejected],
            self::Accepted, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }
}
