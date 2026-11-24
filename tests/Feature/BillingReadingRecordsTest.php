<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingReadingRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_readings_appear_without_charges_then_become_one_bill_each(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $first = Consumer::create(['full_name' => 'First Reader', 'account_number' => 'BR-1', 'meter_number' => 'MT-BR-1', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $second = Consumer::create(['full_name' => 'Second Reader', 'account_number' => 'BR-2', 'meter_number' => 'MT-BR-2', 'status' => 'Active', 'membership_type' => 'Non-Member']);

        $this->travelTo(now()->startOfDay()->addHours(8));
        $this->actingAs($reader)->post(route('meter-readings.store'), ['meter_number' => $first->meter_number, 'reading_date' => '2026-08-20', 'current_reading' => 10])->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        // An earlier reading date submitted later must appear first.
        $this->actingAs($reader)->post(route('meter-readings.store'), ['meter_number' => $second->meter_number, 'reading_date' => '2026-08-05', 'current_reading' => 12])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bills', 0);

        $response = $this->actingAs($admin)->get(route('billing.index'));
        $response->assertOk()->assertSeeInOrder(['Second Reader', 'First Reader'])->assertDontSee('Pending Billing');
        $accounts = $response->viewData('billingAccounts');
        $this->assertSame([$second->id, $first->id], $accounts->getCollection()->pluck('consumer.id')->all());
        $history = $this->get(route('billing.monthly-history', $second))->assertOk();
        $this->assertSame('reading', $history->viewData('records')->first()->record_type);
        $history->assertSee('<td>—</td><td>—</td><td>—</td><td>—</td>', false);
        $filtered = $this->actingAs($admin)->get(route('billing.index', ['status' => 'Unpaid']))->assertOk();
        $this->assertSame(0, $filtered->viewData('billingAccounts')->total());

        $payload = ['billing_period' => '2026-08-01', 'due_date' => '2026-08-31', 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20];
        $this->actingAs($admin)->post(route('billing.generate'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bills', 2);
        $this->assertEquals(390, Bill::where('consumer_id', $second->id)->firstOrFail()->amount);
        $after = $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $this->assertSame($second->id, $after->viewData('billingAccounts')->first()['consumer']->id);
        $this->assertSame(2, $after->viewData('billingAccounts')->total());
        foreach ([$first, $second] as $consumer) {
            $records = $this->get(route('billing.monthly-history', $consumer))->assertOk()->viewData('records');
            $this->assertSame(['bill'], $records->getCollection()->pluck('record_type')->all());
        }
        $this->actingAs($admin)->post(route('billing.generate'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bills', 2);
        $this->assertEquals(390, Bill::where('consumer_id', $second->id)->firstOrFail()->amount);
        $this->travelBack();
    }
}
