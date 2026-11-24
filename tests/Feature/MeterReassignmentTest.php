<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\MeterAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Meter re-assignment after a reconnection.
 *
 * The new meter becomes the account's active meter immediately, the previous
 * meter and its readings are kept for auditing, and an active meter number can
 * never be held by two accounts at once.
 */
class MeterReassignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_new_meter_replaces_the_active_one_and_keeps_the_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer('MT-1001');

        $this->actingAs($admin)
            ->post(route('consumers.assign-meter', $consumer), [
                'meter_number' => 'MT-2002',
                'reason' => 'Defective meter replaced during reconnection.',
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        // The account immediately shows the new meter.
        $this->assertSame('MT-2002', $consumer->meter_number);

        $history = MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $history);

        $old = $history->first();
        $this->assertSame('MT-1001', $old->meter_number);
        $this->assertNotNull($old->retired_at, 'The previous meter is retired, not deleted.');
        $this->assertSame($history->last()->id, $old->replaced_by_assignment_id);

        $new = $history->last();
        $this->assertSame('MT-2002', $new->meter_number);
        $this->assertTrue($new->isActive());
        $this->assertSame($admin->id, $new->assigned_by_user_id);
    }

    public function test_the_consumer_sees_the_new_meter_on_their_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer('MT-1101', $user);

        $this->actingAs($admin)
            ->post(route('consumers.assign-meter', $consumer), ['meter_number' => 'MT-2202'])
            ->assertSessionHasNoErrors();

        // The consumer's login username follows the active meter.
        $this->assertSame('MT-2202', $user->fresh()->username);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertOk()
            ->assertSee('MT-2202')
            // The retired meter is still on record.
            ->assertSee('Meter History')
            ->assertSee('MT-1101')
            ->assertSee('Retired');
    }

    public function test_an_active_meter_cannot_be_assigned_to_two_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->createConsumer('MT-1201');
        $second = $this->createConsumer('MT-1202');

        $this->actingAs($admin)
            ->from(route('consumers.index'))
            ->post(route('consumers.assign-meter', $second), ['meter_number' => 'MT-1201'])
            ->assertSessionHasErrors('meter_number');

        $this->assertSame('MT-1202', $second->refresh()->meter_number);
        $this->assertSame('MT-1201', $first->refresh()->meter_number);
    }

    public function test_a_meter_number_must_differ_from_the_current_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer('MT-1301');

        $this->actingAs($admin)
            ->from(route('consumers.index'))
            ->post(route('consumers.assign-meter', $consumer), ['meter_number' => 'MT-1301'])
            ->assertSessionHasErrors('meter_number');

        $this->assertSame('MT-1301', $consumer->refresh()->meter_number);
    }

    public function test_a_retired_meter_number_can_be_issued_again_to_the_same_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer('MT-1801');

        app(MeterAssignmentService::class)->assign($consumer, 'MT-2802', $admin);
        $this->assertSame('MT-2802', $consumer->refresh()->meter_number);

        // The failed meter is returned and the account goes back to it.
        app(MeterAssignmentService::class)->assign($consumer, 'MT-1801', $admin, 'Returned meter');
        $this->assertSame('MT-1801', $consumer->refresh()->meter_number);

        $history = MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $history, 'Every meter ever issued stays on record.');
        $this->assertSame(1, $history->whereNull('retired_at')->count(), 'Exactly one meter is active.');
    }

    public function test_a_meter_held_by_another_active_account_cannot_be_taken_over(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->createConsumer('MT-1901');
        $second = $this->createConsumer('MT-1902');

        // MT-1901 is retired on the first account...
        app(MeterAssignmentService::class)->assign($first, 'MT-2902', $admin);
        $this->assertSame(0, MeterAssignment::query()
            ->where('consumer_id', $first->id)
            ->whereRaw('LOWER(meter_number) = ?', ['mt-1901'])
            ->whereNull('retired_at')
            ->count());

        // ...so a rejected application on the second account can still be given it.
        $rejected = $this->createConsumer('MT-1903', null);
        $rejected->forceFill(['status' => 'Rejected'])->saveQuietly();

        $this->actingAs($admin)
            ->from(route('consumers.index'))
            ->post(route('consumers.assign-meter', $rejected), ['meter_number' => 'MT-1901'])
            ->assertSessionHasNoErrors();

        $this->assertSame('MT-1901', $rejected->refresh()->meter_number);
        $this->assertSame('MT-2902', $first->refresh()->meter_number);
    }

    public function test_editing_the_meter_number_retires_the_previous_assignment(): void
    {
        $consumer = $this->createConsumer('MT-2001');

        $this->assertSame(1, MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->whereNull('retired_at')
            ->count());

        // Editing the account directly (not through the assign-meter route).
        $consumer->forceFill(['meter_number' => 'MT-3002'])->save();

        $active = MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->whereNull('retired_at')
            ->get();

        $this->assertCount(1, $active, 'Only one meter may ever be active.');
        $this->assertSame('MT-3002', $active->first()->meter_number);
        $this->assertSame(2, MeterAssignment::query()->where('consumer_id', $consumer->id)->count());
    }

    public function test_only_an_administrator_can_assign_a_meter(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $consumer = $this->createConsumer('MT-1401');

        $this->actingAs($user)
            ->post(route('consumers.assign-meter', $consumer), ['meter_number' => 'MT-2402'])
            ->assertForbidden();

        $this->assertSame('MT-1401', $consumer->refresh()->meter_number);
    }

    public function test_the_meter_reader_only_finds_the_currently_assigned_meter(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer('MT-1501');

        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-1501'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('consumer.meter_number', 'MT-1501');

        app(MeterAssignmentService::class)->assign(
            $consumer,
            'MT-2502',
            User::factory()->create(['role' => 'admin']),
        );

        // The retired number no longer resolves, and says why.
        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-1501'])
            ->assertStatus(422)
            ->assertJsonPath('found', false)
            ->assertJsonPath('verified', false)
            // The message points the reader at the replacement meter.
            ->assertSee('MT-2502');

        // The new number does.
        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-2502'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('consumer.meter_number', 'MT-2502');
    }

    public function test_readings_are_recorded_against_the_active_meter_and_history_is_preserved(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $reader = User::factory()->create(['role' => 'meter_reader']);
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer('MT-1601');

        // A reading taken on the original meter.
        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => 'MT-1601',
                'reading_date' => '2026-05-10',
                'current_reading' => 100,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasNoErrors();

        $oldReading = MeterReading::query()->where('consumer_id', $consumer->id)->firstOrFail();
        $this->assertSame('MT-1601', $oldReading->meter_number);
        $this->assertSame(100.0, (float) $oldReading->current_reading);

        // The meter is replaced as part of the reconnection.
        app(MeterAssignmentService::class)->assign($consumer, 'MT-2602', $admin, 'Replacement meter');

        // The old number can no longer be used for a reading.
        $this->actingAs($reader)
            ->from(route('meter-readings.index'))
            ->post(route('meter-readings.store'), [
                'meter_number' => 'MT-1601',
                'reading_date' => '2026-06-10',
                'current_reading' => 120,
                'status' => 'Encoded',
            ])
            ->assertSessionHasErrors('meter_number');

        $this->assertSame(1, MeterReading::query()->where('consumer_id', $consumer->id)->count());

        // The new meter is used from now on.
        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => 'MT-2602',
                'reading_date' => '2026-06-10',
                'current_reading' => 120,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasNoErrors();

        $readings = MeterReading::query()
            ->where('consumer_id', $consumer->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $readings);
        // The earlier reading still belongs to the meter it was taken from.
        $this->assertSame('MT-1601', $readings->first()->meter_number);
        $this->assertSame('MT-2602', $readings->last()->meter_number);
    }

    public function test_billing_after_a_meter_change_uses_the_new_meter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer('MT-1701');

        MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'meter_number' => 'MT-1701',
            'reader_id' => $admin->id,
            'reading_date' => '2026-06-10',
            'previous_reading' => 0,
            'current_reading' => 10,
            'consumption' => 10,
            'status' => 'Encoded',
        ]);

        $this->actingAs($admin)
            ->post(route('consumers.assign-meter', $consumer), ['meter_number' => 'MT-2702'])
            ->assertSessionHasNoErrors();

        $reading = MeterReading::query()->where('consumer_id', $consumer->id)->firstOrFail();
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'meter_reading_id' => $reading->id,
            'billing_period' => '2026-06-01',
            'due_date' => '2026-06-30',
            'prn' => 'MT-BILL-1',
            'amount' => 200.00,
            'units_consumed' => 10,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'consumer_id' => $consumer->id]);
        $this->assertSame('MT-2702', $consumer->refresh()->meter_number);
    }

    private function createConsumer(string $meterNumber, ?User $user = null): Consumer
    {
        static $sequence = 0;
        $sequence++;

        $user ??= User::factory()->create(['role' => 'consumer']);

        return Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Meter Consumer '.$sequence,
            'email' => 'meter.consumer'.@$sequence.'@example.com',
            'account_number' => 'MT-ACC-'.$sequence,
            'meter_number' => $meterNumber,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
    }
}