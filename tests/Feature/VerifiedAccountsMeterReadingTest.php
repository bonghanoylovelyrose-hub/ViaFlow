<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The meter reader module only ever surfaces verified accounts, and an
 * unverified account cannot submit a reading even via a direct POST.
 */
class VerifiedAccountsMeterReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_reader_queue_only_lists_verified_accounts(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $this->createConsumer(['full_name' => 'Verified Active', 'account_number' => 'MR-1', 'meter_number' => 'MR-M-1']);
        $this->createConsumer(['full_name' => 'Pending Verification', 'account_number' => 'MR-2', 'meter_number' => 'MR-M-2', 'status' => 'Pending Verification']);
        $this->createConsumer(['full_name' => 'No Meter Assigned', 'account_number' => 'MR-3', 'meter_number' => 'MR-M-3', 'meter_number' => null]);
        $this->createConsumer(['full_name' => 'Rejected Account', 'account_number' => 'MR-4', 'meter_number' => 'MR-M-4', 'status' => 'Rejected']);

        $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->assertSee('Verified Active')
            ->assertDontSee('Pending Verification')
            ->assertDontSee('No Meter Assigned')
            ->assertDontSee('Rejected Account');
    }

    public function test_disconnected_account_is_not_queued_for_reading(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $this->createConsumer([
            'full_name' => 'Disconnected But Verified',
            'account_number' => 'MR-D1',
            'meter_number' => 'MR-DM-1',
            'status' => 'Disconnected',
        ]);

        $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->assertDontSee('Disconnected But Verified');
    }

    public function test_unverified_account_cannot_submit_a_meter_reading(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $pending = $this->createConsumer([
            'account_number' => 'MR-P1',
            'meter_number' => 'MR-PM-1',
            'status' => 'Pending Verification',
        ]);

        $this->actingAs($reader)
            ->from(route('meter-readings.index'))
            ->post(route('meter-readings.store'), [
                'meter_number' => $pending->meter_number,
                'reading_date' => now()->startOfMonth()->toDateString(),
                'current_reading' => 120,
                'status' => 'Encoded',
            ])
            ->assertSessionHasErrors('meter_number');

        $this->assertSame(0, MeterReading::query()->where('consumer_id', $pending->id)->count());
    }

    public function test_verified_account_can_submit_a_meter_reading(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $verified = $this->createConsumer(['account_number' => 'MR-V1', 'meter_number' => 'MR-VM-1']);

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => $verified->meter_number,
                'reading_date' => now()->startOfMonth()->toDateString(),
                'current_reading' => 120,
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Meter reading captured successfully.');

        $this->assertDatabaseHas('meter_readings', [
            'consumer_id' => $verified->id,
            'current_reading' => 120,
            'status' => MeterReading::STATUS_ENCODED,
        ]);

        $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->assertSee('Meter reading captured successfully.');
    }

    public function test_meter_reading_form_has_confirmation_and_no_status_field(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $this->actingAs($reader)
            ->get(route('meter-readings.index'))
            ->assertOk()
            ->assertSee('id="meterReadingSubmitConfirmModal"', false)
            ->assertSee('Confirm Meter Reading Submission')
            ->assertSee('Are you sure you want to submit this meter reading?')
            ->assertSee('Yes, Submit')
            ->assertSee('Current reading must be greater than the previous reading.')
            ->assertSee('id="readingPreviewError"', false)
            ->assertDontSee('id="status_display"', false)
            ->assertDontSee('name="status" value="Encoded"', false);
    }

    public function test_meter_lookup_reports_unverified_accounts_separately(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $pending = $this->createConsumer([
            'account_number' => 'MR-L1',
            'meter_number' => 'MR-LM-1',
            'status' => 'Pending Verification',
        ]);

        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => $pending->meter_number])
            ->assertStatus(422)
            ->assertJsonPath('found', false)
            ->assertJsonPath('verified', false);

        $verified = $this->createConsumer(['account_number' => 'MR-L2', 'meter_number' => 'MR-LM-2']);

        $this->actingAs($reader)
            ->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => $verified->meter_number])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('verified', true)
            ->assertJsonPath('consumer.meter_number', $verified->meter_number)
            // The removed minimum charge is no longer part of the response.
            ->assertJsonMissingPath('consumer.minimum_charge');
    }

    public function test_captured_readings_filter_by_status_and_search(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $alpha = $this->createConsumer([
            'full_name' => 'Alpha Meter Consumer',
            'account_number' => 'MR-FILTER-1',
            'meter_number' => 'MR-FILTER-M-1',
        ]);
        $beta = $this->createConsumer([
            'full_name' => 'Beta Meter Consumer',
            'account_number' => 'MR-FILTER-2',
            'meter_number' => 'MR-FILTER-M-2',
        ]);

        MeterReading::query()->create([
            'consumer_id' => $alpha->id,
            'reader_id' => $reader->id,
            'reading_date' => now()->startOfMonth()->addDay()->toDateString(),
            'previous_reading' => 100,
            'current_reading' => 110,
            'consumption' => 10,
            'status' => 'Encoded',
        ]);
        MeterReading::query()->create([
            'consumer_id' => $beta->id,
            'reader_id' => $reader->id,
            'reading_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'previous_reading' => 200,
            'current_reading' => 215,
            'consumption' => 15,
            'status' => 'Pending',
        ]);

        $status = $this->actingAs($reader)->get(route('meter-readings.index', ['status' => 'Encoded']));
        $status->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="meter_filter_status"', false);
        $this->assertSame(
            ['Alpha Meter Consumer'],
            $status->viewData('readings')->getCollection()->map(fn (MeterReading $reading) => $reading->consumer->full_name)->all(),
        );

        $search = $this->actingAs($reader)->get(route('meter-readings.index', ['search' => 'MR-FILTER-M-2']));
        $search->assertOk();
        $this->assertSame(
            ['Beta Meter Consumer'],
            $search->viewData('readings')->getCollection()->map(fn (MeterReading $reading) => $reading->consumer->full_name)->all(),
        );
    }

    public function test_admin_period_picker_uses_the_existing_reading_cycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();
        $future = now()->addYears(2)->startOfMonth();
        MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $admin->id,
            'reading_date' => $future->toDateString(),
            'previous_reading' => 100,
            'current_reading' => 110,
            'consumption' => 10,
            'status' => MeterReading::STATUS_ENCODED,
        ]);

        $default = $this->actingAs($admin)->get(route('meter-readings.index'))->assertOk();
        $default->assertSee('data-period-picker', false)->assertSee('<option value="'.$future->year.'"', false);
        $this->assertSame(0, $default->viewData('stats')['completed_reads']);

        foreach ([['year' => $future->year, 'month' => $future->month], ['month' => $future->format('Y-m')]] as $period) {
            $filtered = $this->get(route('meter-readings.index', $period + ['search' => $consumer->meter_number, 'status' => 'Encoded']))->assertOk();
            $this->assertSame($future->format('Y-m'), $filtered->viewData('filters')['month']);
            $this->assertSame($consumer->meter_number, $filtered->viewData('filters')['search']);
            $this->assertSame('Encoded', $filtered->viewData('filters')['status']);
            $this->assertSame(1, $filtered->viewData('stats')['completed_reads']);
        }
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Meter Reader Consumer',
            'account_number' => 'MR-DEFAULT',
            'meter_number' => 'MR-DEFAULT-M',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }
}
