<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterLookupSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_save_and_billing_records_without_generating_charges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = Consumer::create(['full_name' => 'Lookup Consumer', 'account_number' => 'LOOK-1', 'meter_number' => 'MT-LOOK-1', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => '2026-07-01', 'previous_reading' => 8, 'current_reading' => 13, 'consumption' => 5, 'status' => 'Encoded']);
        $this->actingAs($reader)->getJson(route('meter-readings.csrf-token'))->assertOk()->assertJsonStructure(['token'])->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($reader)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => ' mt-look-1 ', 'reading_date' => '2026-08-01'])
            ->assertOk()->assertJsonPath('consumer.full_name', 'Lookup Consumer')->assertJsonPath('consumer.account_number', 'LOOK-1')->assertJsonPath('consumer.previous_reading', '8.00');
        $this->actingAs($reader)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MISSING'])->assertStatus(422)->assertJsonPath('message', 'Invalid Meter ID.');
        $this->actingAs($reader)->post(route('meter-readings.store'), ['meter_number' => 'MT-LOOK-1', 'reading_date' => '2026-08-01', 'current_reading' => 20])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bills', 0);
        $page = $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $this->assertSame($consumer->id, $page->viewData('billingAccounts')->first()['consumer']->id);
        $history = $this->get(route('billing.monthly-history', $consumer))->assertOk()->viewData('records');
        $this->assertSame('reading', $history->first()->record_type);
        $this->assertSame('12.00', $history->first()->item->consumption);
    }

    public function test_existing_unverified_meter_is_not_reported_as_invalid(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = Consumer::create(['full_name' => 'Not Verified', 'account_number' => 'LOOK-2', 'meter_number' => 'MT-LOOK-2', 'status' => 'Pending', 'membership_type' => 'Non-Member']);
        \App\Models\MeterAssignment::where('consumer_id', $consumer->id)->delete();
        $this->actingAs($reader)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-LOOK-2'])
            ->assertStatus(422)->assertJsonPath('message', 'This account has not been verified by the administrator. Meter readings are only available for verified accounts.');
    }

    public function test_token_endpoint_requires_authorized_account(): void
    {
        $this->getJson(route('meter-readings.csrf-token'))->assertUnauthorized();
        $consumer = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($consumer)->getJson(route('meter-readings.csrf-token'))->assertForbidden();
    }
}
