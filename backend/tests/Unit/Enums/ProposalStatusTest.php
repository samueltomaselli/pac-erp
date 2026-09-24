<?php

namespace Tests\Unit\Enums;

use App\Enums\ProposalStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProposalStatusTest extends TestCase
{
    #[DataProvider('allowedTransitionsProvider')]
    public function test_allowed_transitions_match_the_state_machine(ProposalStatus $status, array $expected): void
    {
        $this->assertSame($expected, array_map(fn ($item) => $item->value, $status->allowedTransitions()));
    }

    public static function allowedTransitionsProvider(): array
    {
        return [
            [ProposalStatus::Draft, ['sent']],
            [ProposalStatus::Sent, ['accepted', 'rejected']],
            [ProposalStatus::Accepted, []],
            [ProposalStatus::Rejected, []],
        ];
    }
}
