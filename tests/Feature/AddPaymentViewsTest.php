<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddPaymentViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_and_loan_payment_views_keep_their_matching_forms_for_admin_and_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $user = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $user->id, 'full_name' => 'Consumer', 'account_number' => 'APV-1', 'meter_number' => 'APV-M1', 'status' => 'Active', 'membership_type' => 'Non-Member']);

        foreach ([$admin, $user] as $account) {
            foreach (['water-payments' => 'Water', 'loan-payments' => 'Loan'] as $view => $type) {
                $response = $this->actingAs($account)->get(route('payments.index', ['view' => $view]));
                $response->assertOk()->assertViewHas('recordType', $type)
                    ->assertSee('class="btn viaflow-add-payment"', false)
                    ->assertSee('data-bs-target="#paymentModal"', false)
                    ->assertSee('id="paymentModal"', false);
                if ($account->isConsumer()) {
                    $field = $type === 'Loan' ? 'loan_id' : 'bill_id';
                    $otherField = $type === 'Loan' ? 'bill_id' : 'loan_id';
                    $response->assertSee('pp-balance-card p-4 mb-4 payment-balance-card', false)
                        ->assertSee('payment-balance-action', false)
                        ->assertSee('action="'.route('payments.store', ['view' => $view]).'"', false)
                        ->assertSee('id="consumer_payment_bill_selector" name="'.$field.'"', false)
                        ->assertDontSee('id="consumer_payment_bill_selector" name="'.$otherField.'"', false);
                } else {
                    $response->assertSee('name="payment_type" value="'.$type.'"', false)
                        ->assertSee('value="'.$type.'" selected', false);
                }
            }
        }
    }
}
