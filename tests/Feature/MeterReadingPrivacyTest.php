<?php
namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterReadingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_readers_and_staff_cannot_receive_billing_details_from_preview_or_lookup(): void
    {
        $consumer = Consumer::create(['full_name' => 'Privacy Test', 'account_number' => 'P-1', 'meter_number' => 'MT-P1', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $reading = MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => '2026-10-01', 'previous_reading' => 0, 'current_reading' => 10, 'consumption' => 10, 'status' => 'Encoded']);
        Bill::create(['consumer_id' => $consumer->id, 'meter_reading_id' => $reading->id, 'bill_type' => 'Water', 'billing_period' => '2026-10-01', 'due_date' => '2026-11-02', 'prn' => 'PRIVACY-OCT', 'amount' => 10, 'status' => 'Unpaid']);

        foreach ([$reader, User::factory()->create(['role' => 'staff'])] as $user) {
            $this->actingAs($user)->get(route('meter-readings.index'))->assertOk()
                ->assertSee('Reading Preview')->assertSee('Previous Meter Reading')->assertSee('Current Meter Reading')->assertSee('Monthly Consumption')
                ->assertDontSee('Applicable Water Rate')->assertDontSee('Base Charge')
                ->assertDontSee('Previous Outstanding Balance')->assertDontSee('Estimated Total Due')
                ->assertDontSee('preview_unit_rate')->assertDontSee('preview_total_bill');
            $this->actingAs($user)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-P1', 'reading_date' => '2026-11-17'])
                ->assertOk()->assertJsonPath('consumer.previous_reading', '10.00')
                ->assertJsonMissingPath('consumer.unit_rate')->assertJsonMissingPath('consumer.base_charge')
                ->assertJsonMissingPath('consumer.previous_balance')->assertJsonMissingPath('consumer.outstanding_period');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('meter-readings.index'))->assertOk()
            ->assertSee('Billing Preview')->assertSee('Applicable Water Rate')->assertSee('Estimated Total Due');
        $this->actingAs($admin)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-P1', 'reading_date' => '2026-11-17'])
            ->assertOk()->assertJsonPath('consumer.previous_reading', '10.00')->assertJsonPath('consumer.unit_rate', '18.00')
            ->assertJsonPath('consumer.base_charge', '150.00')->assertJsonPath('consumer.previous_balance', '10.00');
    }

    public function test_admin_preview_counts_only_outstanding_bills_before_the_selected_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create(['full_name' => 'Billing Preview', 'account_number' => 'P-2', 'meter_number' => 'MT-P2', 'status' => 'Active']);

        foreach (['2026-08-01' => [75, 'Unpaid'], '2026-09-01' => [0, 'Paid'], '2026-10-01' => [100, 'Unpaid'], '2026-11-01' => [125, 'Unpaid']] as $period => [$amount, $status]) {
            $consumer->bills()->create(['bill_type' => 'Water', 'billing_period' => $period, 'due_date' => $period,
                'prn' => 'PREVIEW-'.$period, 'amount' => $amount, 'status' => $status]);
        }

        $this->actingAs($admin)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-P2', 'reading_date' => '2026-10-17'])
            ->assertOk()->assertJsonPath('consumer.previous_balance', '75.00')->assertJsonPath('consumer.outstanding_period', 'August 2026');
    }
}

