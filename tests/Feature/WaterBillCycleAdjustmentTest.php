<?php
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\WaterBillCycleAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WaterBillCycleAdjustmentTest extends TestCase
{
    use RefreshDatabase;
    public function test_cycle_shift_preserves_payment_and_moves_linked_dates(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = Consumer::create(['full_name'=>'Demo', 'account_number'=>'20', 'meter_number'=>'MT-1111', 'status'=>'Active', 'membership_type'=>'Member', 'membership_verification_status'=>'Verified']);
        $reading = MeterReading::create(['consumer_id'=>$consumer->id, 'reader_id'=>$reader->id, 'meter_number'=>'MT-1111', 'reading_date'=>'2026-11-17', 'previous_reading'=>0, 'current_reading'=>10, 'consumption'=>10, 'status'=>'Encoded']);
        $bill = Bill::create(['consumer_id'=>$consumer->id, 'meter_reading_id'=>$reading->id, 'bill_type'=>'Water', 'billing_period'=>'2026-11-01', 'due_date'=>'2026-12-02', 'prn'=>'DEMO-1', 'amount'=>180, 'status'=>'Paid with Balance']);
        $payment = Payment::create(['consumer_id'=>$consumer->id, 'bill_id'=>$bill->id, 'payment_type'=>'Water', 'reference_number'=>'DEMO-PAY', 'amount'=>170, 'payment_date'=>'2026-11-17', 'payment_method'=>'Cash', 'payment_channel'=>'Counter', 'status'=>'Verified']);
        $snapshot = $payment->fresh()->getAttributes();
        $updated = app(WaterBillCycleAdjuster::class)->adjust($bill->id, Carbon::parse('2026-10-01'));
        $this->assertSame('2026-10-01', $updated->billing_period->toDateString());
        $this->assertSame('2026-11-02', $updated->due_date->toDateString());
        $this->assertSame('2026-10-17', $reading->fresh()->reading_date->toDateString());
        $this->assertSame($snapshot, $payment->fresh()->getAttributes());
        $this->assertEquals(10, $updated->balance_due);
        $this->assertSame('Paid with Balance', $updated->status);
        $this->assertEquals(10, app(App\Services\MeterReadingBaselineService::class)->previousReadingFor($consumer, Carbon::parse('2026-11-01')));
        $this->assertSame(1, Bill::count());
    }

    public function test_conflicting_reading_blocks_adjustment_without_partial_writes(): void
    {
        $reader = User::factory()->create(['role'=>'meter_reader']);
        $consumer = Consumer::create(['full_name'=>'Demo', 'account_number'=>'20', 'meter_number'=>'MT-1111', 'status'=>'Active', 'membership_type'=>'Non-Member']);
        $reading = MeterReading::create(['consumer_id'=>$consumer->id, 'reader_id'=>$reader->id, 'meter_number'=>'MT-1111', 'reading_date'=>'2026-11-17', 'previous_reading'=>0, 'current_reading'=>10, 'consumption'=>10, 'status'=>'Encoded']);
        $bill = Bill::create(['consumer_id'=>$consumer->id, 'meter_reading_id'=>$reading->id, 'bill_type'=>'Water', 'billing_period'=>'2026-11-01', 'due_date'=>'2026-12-02', 'prn'=>'DEMO-2', 'amount'=>180, 'status'=>'Unpaid']);
        MeterReading::create(['consumer_id'=>$consumer->id, 'reader_id'=>$reader->id, 'meter_number'=>'MT-1111', 'reading_date'=>'2026-10-17', 'previous_reading'=>0, 'current_reading'=>5, 'consumption'=>5, 'status'=>'Encoded']);
        try { app(WaterBillCycleAdjuster::class)->adjust($bill->id, Carbon::parse('2026-10-01')); $this->fail('Conflict was accepted'); }
        catch (InvalidArgumentException $e) { $this->assertStringContainsString('conflicting', $e->getMessage()); }
        $this->assertSame('2026-11-01', $bill->fresh()->billing_period->toDateString());
        $this->assertSame('2026-11-17', $reading->fresh()->reading_date->toDateString());
    }
}
