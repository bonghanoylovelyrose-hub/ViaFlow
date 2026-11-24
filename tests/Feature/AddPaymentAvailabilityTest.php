<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddPaymentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_payable_bills_can_open_original_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->get(route('payments.index'))->assertOk()
            ->assertSee('class="btn viaflow-add-payment"', false)->assertSee('data-bs-target="#paymentModal"', false)
            ->assertSee('id="payment_consumer_no_balance"', false);
    }
    public function test_no_balance_button_is_interactive_and_paid_bill_cannot_be_submitted(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $user->id, 'full_name' => 'Consumer', 'account_number' => 'ACC-TEST',
            'meter_number' => 'MT-TEST', 'status' => 'Active', 'membership_type' => 'Non-Member',
        ]);
        $bill = Bill::create([
            'consumer_id' => $consumer->id, 'billing_period' => '2026-01-01', 'due_date' => '2026-01-31',
            'prn' => 'PRN-TEST', 'amount' => 500, 'status' => 'Paid', 'months_unpaid' => 0,
        ]);

        $this->actingAs($user)->get(route('payments.index'))->assertOk()
            ->assertSee('class="btn viaflow-add-payment"', false)->assertSee('data-bs-target="#paymentModal"', false)
            ->assertSee('No payment is currently due.')
            ->assertSee('id="submit_payment_button" disabled', false);

        $this->post(route('payments.store'), [
            'bill_id' => $bill->id, 'amount' => 100, 'payment_date' => '2026-01-15',
            'payment_channel' => 'Online', 'payment_method' => 'GCash',
        ])->assertSessionHasErrors('bill_id');
        $this->assertDatabaseCount('payments', 0);
    }
}
