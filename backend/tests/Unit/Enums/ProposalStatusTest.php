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
            [ProposalStatus::Sent, ['accepted', 'rejected', 'expired']],
            [ProposalStatus::Accepted, []],
            [ProposalStatus::Rejected, []],
            [ProposalStatus::Expired, []],
        ];
    }

    public function test_expired_is_labelled_in_portuguese(): void
    {
        $this->assertSame('Vencida', ProposalStatus::Expired->label());
    }

    public function test_only_sent_can_transition_to_expired(): void
    {
        $this->assertTrue(ProposalStatus::Sent->canTransitionTo(ProposalStatus::Expired));
        $this->assertFalse(ProposalStatus::Draft->canTransitionTo(ProposalStatus::Expired));
        $this->assertFalse(ProposalStatus::Accepted->canTransitionTo(ProposalStatus::Expired));
        $this->assertFalse(ProposalStatus::Rejected->canTransitionTo(ProposalStatus::Expired));
        $this->assertFalse(ProposalStatus::Expired->canTransitionTo(ProposalStatus::Expired));
    }

    #[DataProvider('finalityProvider')]
    public function test_final_statuses_include_expired(ProposalStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isFinal());
    }

    public static function finalityProvider(): array
    {
        return [
            [ProposalStatus::Draft, false],
            [ProposalStatus::Sent, false],
            [ProposalStatus::Accepted, true],
            [ProposalStatus::Rejected, true],
            [ProposalStatus::Expired, true],
        ];
    }

    public function test_only_draft_remains_editable(): void
    {
        $editable = array_values(array_filter(
            ProposalStatus::cases(),
            fn (ProposalStatus $status) => $status->isEditable(),
        ));

        $this->assertSame([ProposalStatus::Draft], $editable);
    }
}
