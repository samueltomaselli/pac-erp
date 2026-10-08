<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProposalExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_sent_proposal_valid_until_yesterday_expires(): void
    {
        $proposal = Proposal::factory()->sent()->create(['valid_until' => '2026-10-05']);

        $this->artisan('proposals:expire')
            ->expectsOutput('1 proposta(s) vencida(s).')
            ->assertSuccessful();

        $this->assertSame(ProposalStatus::Expired, $proposal->fresh()->status);
    }

    public function test_a_sent_proposal_valid_until_today_does_not_expire(): void
    {
        $proposal = Proposal::factory()->sent()->create(['valid_until' => '2026-10-06']);

        $this->artisan('proposals:expire')
            ->expectsOutput('0 proposta(s) vencida(s).')
            ->assertSuccessful();

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }

    public function test_a_sent_proposal_valid_until_tomorrow_does_not_expire(): void
    {
        $proposal = Proposal::factory()->sent()->create(['valid_until' => '2026-10-07']);

        $this->artisan('proposals:expire')->assertSuccessful();

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }

    public function test_a_proposal_valid_until_today_does_not_expire_late_in_the_brazilian_evening(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 02:00:00', 'UTC'));

        $this->assertSame('2026-10-06', today()->toDateString());

        $proposal = Proposal::factory()->sent()->create(['valid_until' => '2026-10-06']);

        $this->artisan('proposals:expire')->assertSuccessful();

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }

    public function test_decided_proposals_and_drafts_past_their_deadline_are_left_untouched(): void
    {
        $accepted = Proposal::factory()->accepted()->create([
            'valid_until' => '2026-09-01',
            'decided_at' => '2026-08-20 10:00:00',
        ]);
        $rejected = Proposal::factory()->rejected()->create([
            'valid_until' => '2026-09-01',
            'decided_at' => '2026-08-21 10:00:00',
        ]);
        $draft = Proposal::factory()->draft()->create(['valid_until' => '2026-09-01']);

        $this->artisan('proposals:expire')
            ->expectsOutput('0 proposta(s) vencida(s).')
            ->assertSuccessful();

        $this->assertSame(ProposalStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame('2026-08-20 10:00:00', $accepted->fresh()->decided_at->format('Y-m-d H:i:s'));
        $this->assertSame(ProposalStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame('2026-08-21 10:00:00', $rejected->fresh()->decided_at->format('Y-m-d H:i:s'));
        $this->assertSame(ProposalStatus::Draft, $draft->fresh()->status);
    }

    public function test_running_the_command_twice_changes_nothing_the_second_time(): void
    {
        $proposal = Proposal::factory()->sent()->create(['valid_until' => '2026-10-01']);

        $this->artisan('proposals:expire')->expectsOutput('1 proposta(s) vencida(s).');

        $updatedAt = $proposal->fresh()->updated_at;

        Carbon::setTestNow('2026-10-06 13:00:00');

        $this->artisan('proposals:expire')->expectsOutput('0 proposta(s) vencida(s).');

        $this->assertSame(ProposalStatus::Expired, $proposal->fresh()->status);
        $this->assertEquals($updatedAt, $proposal->fresh()->updated_at);
    }

    public function test_expiring_preserves_sent_at_and_leaves_decided_at_null(): void
    {
        $proposal = Proposal::factory()->sent()->create([
            'valid_until' => '2026-10-01',
            'sent_at' => '2026-09-15 14:30:00',
        ]);

        $this->artisan('proposals:expire');

        $proposal->refresh();

        $this->assertSame(ProposalStatus::Expired, $proposal->status);
        $this->assertSame('2026-09-15 14:30:00', $proposal->sent_at->format('Y-m-d H:i:s'));
        $this->assertNull($proposal->decided_at);
    }

    public function test_every_due_proposal_in_a_large_batch_expires(): void
    {
        Proposal::factory()->count(150)->sent()->create(['valid_until' => '2026-10-01']);
        Proposal::factory()->count(5)->sent()->create(['valid_until' => '2026-10-10']);

        $this->artisan('proposals:expire')->expectsOutput('150 proposta(s) vencida(s).');

        $this->assertSame(150, Proposal::query()->status(ProposalStatus::Expired)->count());
        $this->assertSame(5, Proposal::query()->status(ProposalStatus::Sent)->count());
    }

    public function test_a_proposal_decided_after_the_query_is_skipped_and_the_batch_goes_on(): void
    {
        $decidedMeanwhile = Proposal::factory()->sent()->create(['valid_until' => '2026-10-01']);
        $due = Proposal::factory()->sent()->create(['valid_until' => '2026-10-01']);

        $alreadyDecided = false;
        Proposal::retrieved(function (Proposal $proposal) use ($decidedMeanwhile, &$alreadyDecided): void {
            if ($alreadyDecided || $proposal->id !== $decidedMeanwhile->id) {
                return;
            }

            $alreadyDecided = true;
            DB::table('proposals')->where('id', $proposal->id)->update([
                'status' => ProposalStatus::Accepted->value,
                'decided_at' => now(),
            ]);
        });

        $this->artisan('proposals:expire')
            ->expectsOutput('1 proposta(s) vencida(s).')
            ->assertSuccessful();

        $this->assertSame(ProposalStatus::Accepted, $decidedMeanwhile->fresh()->status);
        $this->assertSame(ProposalStatus::Expired, $due->fresh()->status);
    }

    public function test_an_expired_proposal_cannot_be_accepted_by_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-01']);

        $this->artisan('proposals:expire');

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível mudar de Vencida para Aceita.');

        $this->assertSame(ProposalStatus::Expired, $proposal->fresh()->status);
    }

    public function test_an_expired_proposal_cannot_be_rejected_by_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-01']);

        $this->artisan('proposals:expire');

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/reject")
            ->assertStatus(422);

        $this->assertSame(ProposalStatus::Expired, $proposal->fresh()->status);
    }

    public function test_the_admin_is_never_offered_expiring_a_proposal_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-10']);

        $response = $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}");

        $response->assertStatus(200);
        $this->assertSame(
            ['accepted', 'rejected'],
            array_column($response->json('data.allowed_transitions'), 'status'),
        );
    }

    public function test_an_expired_proposal_is_exposed_as_vencida_with_no_transitions(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-01']);

        $this->artisan('proposals:expire');

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.status_label', 'Vencida')
            ->assertJsonPath('data.is_editable', false)
            ->assertJsonPath('data.allowed_transitions', [])
            ->assertJsonPath('data.decided_at', null);
    }

    public function test_the_command_is_scheduled_daily_at_three_in_the_morning_brazilian_time(): void
    {
        // withSchedule() só registra os eventos quando o console do Artisan sobe, como no schedule:run.
        // O RefreshDatabase recria o Artisan depois da migração, então aqui o evento aparece repetido.
        $this->artisan('schedule:list')->assertSuccessful();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'proposals:expire'));

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertSame('0 3 * * *', $event->expression);
            $this->assertSame(
                '2026-10-07 03:00:00',
                Carbon::instance($event->nextRunDate(Carbon::now()))
                    ->setTimezone('America/Sao_Paulo')
                    ->format('Y-m-d H:i:s'),
            );
        }
    }
}
