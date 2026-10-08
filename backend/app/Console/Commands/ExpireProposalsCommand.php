<?php

namespace App\Console\Commands;

use App\Actions\Proposals\ExpireDueProposals;
use Illuminate\Console\Command;

class ExpireProposalsCommand extends Command
{
    protected $signature = 'proposals:expire';

    protected $description = 'Marca como vencidas as propostas enviadas cujo prazo de validade expirou.';

    public function handle(ExpireDueProposals $expireDueProposals): int
    {
        $expired = $expireDueProposals->handle()->data['expired'];

        $this->info(sprintf('%d proposta(s) vencida(s).', $expired));

        return self::SUCCESS;
    }
}
