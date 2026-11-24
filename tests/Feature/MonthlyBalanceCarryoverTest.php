<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBalanceCarryoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_balance_moves_forward_once_and_tracks_payments_across_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Carry Test', 'account_number' => 'CARRY-1111', 'meter_number' => 'MT-1111', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $october = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-10-01', 'due_date' => '2026-10-31', 'prn' => 'CARRY-OCT', 'amount' => 10, 'status' => 'Unpaid']);
        $this->reading($consumer, $reader, '2026-11-17', 1);
        $this->generate($admin, '2026-11-01');
        $november = Bill::where('consumer_id', $consumer->id)->whereDate('billing_period', '2026-11-01')->firstOrFail();
        $this->assertEquals(10, $november->arrears_amount);
        $this->assertEquals(170, $november->water_charge);
        $this->assertEquals(180, $november->amount);
        $this->assertEquals($october->id, $november->carried_forward_from_bill_id);
        $this->assertEquals($november->id, $october->fresh()->carried_forward_to_bill_id);
        $this->assertEquals(180, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));

        $this->generate($admin, '2026-11-01');
        $this->assertEquals($october->id, $november->fresh()->carried_forward_from_bill_id);
        $this->assertSame(2, Bill::where('consumer_id', $consumer->id)->count());

        Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $november->id, 'payment_type' => 'Water', 'reference_number' => 'CARRY-PAY', 'amount' => 60, 'payment_date' => '2026-11-20', 'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Verified']);
        $november->refresh()->syncPaymentStatus();
        $this->assertEquals(120, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Loan', 'billing_period' => '2026-12-01', 'due_date' => '2026-12-31', 'prn' => 'CARRY-LOAN', 'amount' => 5, 'status' => 'Unpaid']);
        $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('November 2026');
        $this->assertSame('2026-11-01', $dashboard->viewData('billingSummary')['current_bill']->billing_period->toDateString());
        $this->actingAs($admin)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-1111', 'reading_date' => '2026-12-10'])->assertOk()->assertJsonPath('consumer.outstanding_period', 'November 2026')->assertJsonPath('consumer.previous_balance', '120.00');

        $this->reading($consumer, $reader, '2026-12-10', 2);
        $this->generate($admin, '2026-12-01');
        $december = Bill::where('consumer_id', $consumer->id)->where('bill_type', 'Water')->whereDate('billing_period', '2026-12-01')->firstOrFail();
        $this->assertEquals(120, $december->arrears_amount);
        $this->assertEquals(290, $december->amount);
        $this->assertEquals(290, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertEquals($december->id, $november->fresh()->carried_forward_to_bill_id);
        $this->assertSame('2026-10-01', $october->fresh()->billing_period->toDateString());
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('December 2026');
        $this->actingAs($user)->get(route('payments.index', ['view' => 'water-payments']))->assertOk()->assertSee('Dec 2026');
        $this->actingAs($admin)->get(route('billing.index'))->assertOk()->assertSee('December 2026')->assertSee('290.00');
        $this->actingAs($admin)->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => 'MT-1111', 'reading_date' => '2027-01-10'])->assertOk()->assertJsonPath('consumer.outstanding_period', 'December 2026')->assertJsonPath('consumer.previous_balance', '290.00');
    }

    private function reading(Consumer $consumer, User $reader, string $date, int $current): void
    {
        MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => $date, 'previous_reading' => 0, 'current_reading' => $current, 'consumption' => $current, 'status' => 'Encoded']);
    }

    private function generate(User $admin, string $period): void
    {
        $this->actingAs($admin)->post(route('billing.generate'), ['billing_period' => $period, 'due_date' => \Illuminate\Support\Carbon::parse($period)->endOfMonth()->toDateString(), 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20])->assertSessionHasNoErrors();
    }
}
