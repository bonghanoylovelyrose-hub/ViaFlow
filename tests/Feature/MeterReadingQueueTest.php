<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterReadingQueueTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pending_queue_is_newest_verified_first(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $newest = $this->createConsumer([
            'full_name' => 'Newest Verified',
            'verified_at' => '2026-10-03 09:00:00',
        ]);
        $oldest = $this->createConsumer([
            'full_name' => 'Oldest Verified',
            'verified_at' => '2026-10-01 08:00:00',
        ]);
        $middle = $this->createConsumer([
            'full_name' => 'Middle Verified',
            'verified_at' => '2026-10-02 10:30:00',
        ]);

        $pending = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(
            [$newest->full_name, $middle->full_name, $oldest->full_name],
            $pending->pluck('full_name')->all(),
        );
    }

    public function test_newly_verified_account_immediately_appears_at_the_top(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $existing = $this->createConsumer([
            'full_name' => 'Existing Queue Member',
            'verified_at' => '2026-10-01 08:00:00',
        ]);

        // Created as verified after the queue page already has a waiting
        // account. No queue row is inserted or regenerated manually.
        $newcomer = $this->createConsumer([
            'full_name' => 'Just Verified Today',
            'verified_at' => '2026-11-02 11:58:36',
        ]);

        $pending = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(
            [$newcomer->full_name, $existing->full_name],
            $pending->pluck('full_name')->all(),
        );
        $this->assertSame($newcomer->id, $pending->first()->id);
        $this->assertCount(2, $pending);
        $this->assertSame($pending->pluck('id')->unique()->count(), $pending->count());
    }

    public function test_newly_verified_account_stays_first_when_the_queue_spans_pages(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        for ($index = 1; $index <= 12; $index++) {
            $this->createConsumer([
                'full_name' => sprintf('Older Verified %02d', $index),
                'verified_at' => sprintf('2026-09-%02d 08:00:00', $index),
            ]);
        }

        $newcomer = $this->createConsumer([
            'full_name' => 'Newest Verified',
            'verified_at' => '2026-11-02 11:58:36',
        ]);

        $firstPage = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');
        $laterPage = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['page' => 2]))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertCount(13, $firstPage);
        $this->assertSame($newcomer->id, $firstPage->first()->id);
        $this->assertSame($newcomer->id, $laterPage->first()->id);
        $this->assertSame(
            $firstPage->pluck('id')->all(),
            $laterPage->pluck('id')->all(),
        );
        $this->assertSame($firstPage->pluck('id')->unique()->count(), $firstPage->count());
    }

    public function test_verification_timestamps_and_queue_order_survive_a_new_billing_month(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        Carbon::setTestNow('2026-10-31 12:00:00');

        $older = $this->createConsumer([
            'full_name' => 'Older October Account',
            'verified_at' => '2026-09-15 08:00:00',
        ]);
        $newer = $this->createConsumer([
            'full_name' => 'Newer October Account',
            'verified_at' => '2026-10-30 09:00:00',
        ]);

        $before = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(
            [$newer->full_name, $older->full_name],
            $before->pluck('full_name')->all(),
        );

        $olderVerifiedAt = $older->refresh()->verified_at;
        $newerVerifiedAt = $newer->refresh()->verified_at;

        // A new billing month must not regenerate the queue or re-stamp the
        // accounts that were already verified.
        Carbon::setTestNow('2026-11-15 09:00:00');

        $after = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(
            [$newer->full_name, $older->full_name],
            $after->pluck('full_name')->all(),
        );
        $this->assertTrue($older->refresh()->verified_at->equalTo($olderVerifiedAt));
        $this->assertTrue($newer->refresh()->verified_at->equalTo($newerVerifiedAt));
    }

    public function test_verified_at_is_stamped_when_an_account_becomes_verified_and_is_not_moved_later(): void
    {
        Carbon::setTestNow('2026-11-02 08:00:00');

        $consumer = Consumer::query()->create([
            'full_name' => 'Pending Applicant',
            'account_number' => null,
            'meter_number' => null,
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
        ]);

        $this->assertNull($consumer->verified_at);

        $consumer->update([
            'account_number' => 'VQ-1001',
            'meter_number' => 'VQ-M-1001',
            'status' => 'Active',
        ]);

        $verifiedAt = $consumer->refresh()->verified_at;
        $this->assertNotNull($verifiedAt);
        $this->assertTrue($verifiedAt->equalTo(Carbon::parse('2026-11-02 08:00:00')));

        // A later profile edit must not move the account within the queue.
        Carbon::setTestNow('2026-11-05 15:30:00');
        $consumer->update(['full_name' => 'Renamed Applicant']);

        $this->assertTrue($consumer->refresh()->verified_at->equalTo($verifiedAt));
    }

    public function test_queue_only_contains_connected_active_accounts_with_a_meter(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $this->createConsumer(['full_name' => 'Eligible Active', 'status' => 'Active']);
        $this->createConsumer(['full_name' => 'Eligible For Review', 'status' => 'For Review']);
        $this->createConsumer(['full_name' => 'Pending Applicant', 'status' => 'Pending Verification']);
        $this->createConsumer(['full_name' => 'Rejected Applicant', 'status' => 'Rejected']);
        $this->createConsumer(['full_name' => 'Disconnected Account', 'status' => 'Disconnected']);
        $this->createConsumer(['full_name' => 'Awaiting Reconnection', 'status' => 'For Reconnection']);
        $this->createConsumer(['full_name' => 'No Meter Account', 'meter_number' => null]);
        $this->createConsumer(['full_name' => 'No Account Number', 'account_number' => null]);
        $this->createConsumer([
            'full_name' => 'Flagged Disconnected Active',
            'status' => 'Active',
            'disconnected_at' => '2026-10-15 09:00:00',
        ]);

        $pending = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(
            ['Eligible Active', 'Eligible For Review'],
            $pending->pluck('full_name')->sort()->values()->all(),
        );
    }

    public function test_encoded_reading_removes_account_but_pending_capture_keeps_it_queued(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $otherReader = User::factory()->create(['role' => 'meter_reader']);

        $encoded = $this->createConsumer(['full_name' => 'Already Encoded']);
        $revisit = $this->createConsumer(['full_name' => 'Still Pending Capture']);

        $this->makeReading($encoded, $reader, '2026-11-01 09:00:00', '2026-11-01', MeterReading::STATUS_ENCODED);
        $this->makeReading($revisit, $reader, '2026-11-01 10:00:00', '2026-11-01', MeterReading::STATUS_PENDING);

        // Another reader's completed reading also completes the cycle for the
        // whole team; the account must not reappear in a colleague's queue.
        $pending = $this->actingAs($otherReader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertSame(['Still Pending Capture'], $pending->pluck('full_name')->all());
    }

    public function test_done_readings_are_latest_encoded_first_not_by_reading_date(): void
    {
        Carbon::setTestNow('2026-11-15 12:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);

        $first = $this->createConsumer(['full_name' => 'First Encoded']);
        $second = $this->createConsumer(['full_name' => 'Second Encoded']);
        $third = $this->createConsumer(['full_name' => 'Third Encoded']);

        // Deliberately reverse the reading_date order so the assertion proves
        // the sort uses the completion timestamp, not reading_date.
        $this->makeReading($first, $reader, '2026-11-01 08:00:00', '2026-11-03', MeterReading::STATUS_ENCODED);
        $this->makeReading($second, $reader, '2026-11-01 09:00:00', '2026-11-02', MeterReading::STATUS_ENCODED);
        $this->makeReading($third, $reader, '2026-11-01 10:00:00', '2026-11-01', MeterReading::STATUS_ENCODED);

        $completed = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('completedReadings');

        $this->assertSame(
            ['Third Encoded', 'Second Encoded', 'First Encoded'],
            $completed->pluck('consumer.full_name')->all(),
        );
    }

    public function test_done_readings_default_to_current_month_and_pending_resets_with_the_new_month(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['full_name' => 'October Cycle Account']);

        $this->makeReading(
            $consumer,
            $reader,
            '2026-10-20 10:00:00',
            '2026-10-15',
            MeterReading::STATUS_ENCODED,
        );

        // The November view is a fresh cycle: the October completion is not
        // shown as a November completion, and the consumer is pending again.
        $november = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk();

        $this->assertSame([], $november->viewData('completedReadings')->pluck('consumer.full_name')->all());
        $this->assertTrue($november->viewData('pendingConsumers')->contains('id', $consumer->id));
        $this->assertSame('2026-11', $november->viewData('filters')['month']);

        // Selecting the past billing month restores its Done list and removes
        // the account from that month's pending queue.
        $october = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => '2026-10']))
            ->assertOk();

        $this->assertSame(
            ['October Cycle Account'],
            $october->viewData('completedReadings')->pluck('consumer.full_name')->all(),
        );
        $this->assertFalse($october->viewData('pendingConsumers')->contains('id', $consumer->id));
        $this->assertSame('2026-10', $october->viewData('filters')['month']);
    }

    public function test_month_filter_preserves_completed_history_across_billing_months(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['full_name' => 'Recurring Cycle Account']);

        $octoberReading = $this->makeReading(
            $consumer,
            $reader,
            '2026-10-20 10:00:00',
            '2026-10-15',
            MeterReading::STATUS_ENCODED,
        );
        $novemberReading = $this->makeReading(
            $consumer,
            $reader,
            '2026-11-10 10:00:00',
            '2026-11-08',
            MeterReading::STATUS_ENCODED,
        );

        $october = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => '2026-10']))
            ->assertOk()
            ->viewData('completedReadings');
        $november = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => '2026-11']))
            ->assertOk()
            ->viewData('completedReadings');

        $this->assertSame([$octoberReading->id], $october->pluck('id')->all());
        $this->assertSame([$novemberReading->id], $november->pluck('id')->all());
        $this->assertSame(2, MeterReading::query()->where('consumer_id', $consumer->id)->count());
    }

    public function test_done_readings_show_only_the_latest_row_when_legacy_duplicates_exist(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['full_name' => 'Legacy Duplicate Account']);

        $original = $this->makeReading(
            $consumer,
            $reader,
            '2026-11-02 09:00:00',
            '2026-11-02',
            MeterReading::STATUS_ENCODED,
        );
        $replacement = $this->makeReading(
            $consumer,
            $reader,
            '2026-11-03 09:00:00',
            '2026-11-03',
            MeterReading::STATUS_ENCODED,
        );

        $completed = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('completedReadings');

        $this->assertCount(1, $completed);
        $this->assertSame($replacement->id, $completed->first()->id);
        $this->assertNotSame($original->id, $completed->first()->id);

        // The superseded row is hidden from this cycle's Done list but is not
        // deleted from the database or from the full capture history.
        $this->assertSame(2, MeterReading::query()->where('consumer_id', $consumer->id)->count());
    }

    public function test_past_month_queue_excludes_accounts_verified_after_that_month(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);
        $earlier = $this->createConsumer([
            'full_name' => 'Verified Before October',
            'verified_at' => '2026-10-15 08:00:00',
        ]);
        $later = $this->createConsumer([
            'full_name' => 'Verified In November',
            'verified_at' => '2026-11-02 11:58:36',
        ]);

        $october = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => '2026-10']))
            ->assertOk()
            ->viewData('pendingConsumers');
        $november = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => '2026-11']))
            ->assertOk()
            ->viewData('pendingConsumers');

        $this->assertTrue($october->contains('id', $earlier->id));
        $this->assertFalse($october->contains('id', $later->id));
        $this->assertTrue($november->contains('id', $earlier->id));
        $this->assertTrue($november->contains('id', $later->id));
    }

    public function test_invalid_month_filter_falls_back_to_current_month(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');

        $reader = User::factory()->create(['role' => 'meter_reader']);

        $response = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['month' => 'not-a-month']))
            ->assertOk();

        $this->assertSame('2026-11', $response->viewData('filters')['month']);
        $this->assertSame('November 2026', $response->viewData('filters')['month_label']);
    }

    public function test_encoding_a_reading_moves_account_to_done_and_prevents_duplicates(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['full_name' => 'Fresh Reading Account']);

        $before = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->viewData('pendingConsumers');
        $this->assertTrue($before->contains('id', $consumer->id));

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => now()->toDateString(),
                'current_reading' => 125,
                'status' => MeterReading::STATUS_ENCODED,
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasNoErrors();

        $after = $this->actingAs($reader)->get(route('meter-readings.index'));
        $this->assertFalse($after->viewData('pendingConsumers')->contains('id', $consumer->id));
        $this->assertTrue($after->viewData('completedReadings')->contains('consumer_id', $consumer->id));
        $this->assertSame(1, MeterReading::query()->where('consumer_id', $consumer->id)->count());

        // A second attempt for the same month is rejected and creates no row.
        $this->actingAs($reader)
            ->from(route('meter-readings.index'))
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => now()->toDateString(),
                'current_reading' => 130,
                'status' => MeterReading::STATUS_ENCODED,
            ])
            ->assertSessionHasErrors('duplicate_reading');

        $this->assertSame(1, MeterReading::query()->where('consumer_id', $consumer->id)->count());
    }

    public function test_pending_capture_can_be_completed_without_creating_a_duplicate(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['full_name' => 'Pending Capture Account']);

        $pending = $this->makeReading(
            $consumer,
            $reader,
            '2026-11-01 10:00:00',
            now()->toDateString(),
            MeterReading::STATUS_PENDING,
        );

        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('already_read', false);

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => now()->toDateString(),
                'current_reading' => 125,
                'status' => MeterReading::STATUS_ENCODED,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('meter-readings.index'));

        $this->assertSame(1, MeterReading::query()->where('consumer_id', $consumer->id)->count());
        $pending->refresh();
        $this->assertSame(MeterReading::STATUS_ENCODED, $pending->status);
        $this->assertSame(125.0, (float) $pending->current_reading);
    }

    public function test_all_captured_readings_keep_completion_order_across_pagination(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $expected = [];
        for ($index = 1; $index <= 12; $index++) {
            $consumer = $this->createConsumer(['full_name' => sprintf('Paged Consumer %02d', $index)]);
            $this->makeReading(
                $consumer,
                $reader,
                Carbon::parse('2026-11-01 08:00:00')->addMinutes($index),
                '2026-10-'.str_pad((string) (13 - $index), 2, '0', STR_PAD_LEFT),
                MeterReading::STATUS_ENCODED,
            );
            $expected[] = $consumer->full_name;
        }

        $expected = array_reverse($expected);

        $pageOne = $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->viewData('readings');
        $pageTwo = $this->actingAs($reader)
            ->get(route('meter-readings.index', ['page' => 2]))
            ->assertOk()
            ->viewData('readings');

        $pageOneNames = $pageOne->getCollection()->pluck('consumer.full_name')->all();
        $pageTwoNames = $pageTwo->getCollection()->pluck('consumer.full_name')->all();

        $this->assertSame(array_slice($expected, 0, 10), $pageOneNames);
        $this->assertSame(array_slice($expected, 10), $pageTwoNames);
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        $this->sequence++;

        return Consumer::query()->create(array_merge([
            'full_name' => 'Queue Consumer '.$this->sequence,
            'account_number' => 'Q-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'meter_number' => 'Q-M-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'verified_at' => '2026-10-01 08:00:00',
        ], $attributes));
    }

    private function makeReading(
        Consumer $consumer,
        User $reader,
        string $completedAt,
        string $readingDate,
        string $status,
    ): MeterReading {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow($completedAt);

        try {
            return MeterReading::query()->create([
                'consumer_id' => $consumer->id,
                'reader_id' => $reader->id,
                'reading_date' => $readingDate,
                'previous_reading' => 100,
                'current_reading' => 110,
                'consumption' => 10,
                'status' => $status,
            ]);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }
}
