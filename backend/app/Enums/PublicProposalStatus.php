<?php

namespace App\Enums;

use App\Models\Proposal;

enum PublicProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Accepted => 'Aceita',
            self::Expired => 'Expirada',
        };
    }

    public function canAccept(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Única fonte da regra de status público. Leitura e aceite usam este método.
     *
     * Uma proposta `sent` com validade vencida já é Expirada aqui, mesmo antes
     * do job de vencimento rodar. Retorna null quando a proposta não tem status
     * público (rascunho e recusada), e quem chama responde 404.
     */
    public static function for(Proposal $proposal): ?self
    {
        return match ($proposal->status) {
            ProposalStatus::Draft, ProposalStatus::Rejected => null,
            ProposalStatus::Sent => $proposal->valid_until->lt(today()) ? self::Expired : self::Pending,
            ProposalStatus::Accepted => self::Accepted,
            ProposalStatus::Expired => self::Expired,
        };
    }
}
