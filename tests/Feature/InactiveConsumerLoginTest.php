<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveConsumerLoginTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $status = 'Disconnected'): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Inactive Account Consumer',
            'account_number' => 'INACTIVE-100',
            'meter_number' => 'INACTIVE-METER-100',
            'status' => $status,
            'membership_type' => 'Non-Member',
            'reconnection_fee' => 125.75,
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'prn' => 'INACTIVE-BILL-100',
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'amount' => 850.50,
            'status' => 'Unpaid',
        ]);

        return [$user, $consumer, $bill];
    }

    private function payment(Bill $bill, float $amount, string $status): Payment
    {
        return Payment::query()->create([
            'consumer_id' => $bill->consumer_id,
            'bill_id' => $bill->id,
            'reference_number' => 'INACTIVE-PAY-'.(Payment::query()->count() + 1),
            'amount' => $amount,
            'payment_date' => today(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => $status,
        ]);
    }

    public function test_existing_credentials_open_the_popup_with_actual_balance_fee_and_total(): void
    {
        [$user, $consumer, $bill] = $this->account();
        $this->payment($bill, 200.25, 'Verified');
        $this->payment($bill, 100, 'Pending');

        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => 'password'])
            ->assertRedirect(route('account.inactive'))->assertSessionHas('inactive_login_notice', true);
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.inactive'))->assertOk()->assertSee('id="inactiveAccountNotice"', false)
            ->assertSee('Consumer Name')->assertSee($consumer->full_name)->assertSee($consumer->account_number)
            ->assertSee('Meter Number')->assertSee($consumer->meter_number)->assertSee('Log Out')
            ->assertDontSee('data-bs-dismiss', false)->assertDontSee('<nav', false)->assertDontSee('<aside', false)
            ->assertSee('Account Status')->assertSee('Inactive')->assertSee('PHP 650.25')
            ->assertDontSee('Reconnection Fee')->assertDontSee('PHP 125.75')->assertSee('visit the office')
            ->assertSee('authorized reactivation');
    }

    public function test_unknown_fee_does_not_invent_a_fee_or_final_total_and_zero_fee_is_supported(): void
    {
        [$user, $consumer] = $this->account();
        $consumer->update(['reconnection_fee' => null]);
        $this->actingAs($user)->get(route('account.inactive'))->assertOk()
            ->assertSee('PHP 850.50')->assertDontSee('assessment pending')->assertDontSee('Reconnection Fee');
        $consumer->update(['reconnection_fee' => 0]);
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 850.50')->assertDontSee('Reconnection Fee');
    }

    public function test_all_portal_routes_are_blocked_while_only_the_popup_and_logout_remain_accessible(): void
    {
        [$user] = $this->account();
        $this->actingAs($user);
        foreach (['dashboard', 'loans.index', 'incidents.index', 'announcements.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('account.inactive'));
        }
        $this->post(route('loans.store'), [])->assertForbidden();
        $this->post(route('incidents.store'), [])->assertForbidden();
        $this->post(route('billing.requests.store'), ['request_type' => 'New Connection'])->assertForbidden();
        $this->getJson(route('loans.index'))->assertForbidden();

        foreach (['billing.index', 'payments.index', 'consumer.profile.show', 'connection.status'] as $route) {
            $this->get(route($route))->assertRedirect(route('account.inactive'));
        }
        $this->get(route('account.inactive'))->assertDontSee('My Loans')->assertDontSee('My Incidents')
            ->assertDontSee('My Payments')->assertDontSee('View My Bills')->assertSee('Log Out');
        $this->post(route('payments.store'), [])->assertForbidden();
        $this->patch(route('consumer.profile.password'), [])->assertForbidden();
        $this->post(route('billing.requests.store'), ['request_type' => 'Reconnection'])->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_payment_alone_and_reconnection_approval_do_not_restore_normal_access(): void
    {
        [$user, $consumer, $bill] = $this->account();
        $this->payment($bill, 850.50, 'Verified');
        $consumer->update(['status' => 'For Reconnection', 'full_payment_verified_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 0.00');
        $this->assertNull($consumer->refresh()->reconnected_at);
    }

    public function test_only_authorized_completion_restores_service_and_requires_a_password_change(): void
    {
        [$user, $consumer, $bill] = $this->account();
        $password = $user->password;
        $this->payment($bill, 850.50, 'Verified');
        $consumer->update(['status' => 'For Reconnection', 'full_payment_verified_at' => now()]);
        $serviceRequest = ServiceRequest::query()->create([
            'consumer_id' => $consumer->id,
            'request_type' => 'Reconnection',
            'status' => 'Approved',
            'requested_at' => now(),
            'current_balance_snapshot' => 0,
        ]);
        $this->actingAs($user)->patch(route('billing.requests.update', $serviceRequest), ['status' => 'Completed'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $serviceRequest), ['status' => 'Completed'])->assertSessionHasNoErrors();

        $this->assertNull($consumer->refresh()->reconnected_at);
        $consumer->update(['reconnection_fee' => 0]);
        $consumer->payments()->update(['verified_at' => now(), 'verified_by' => $admin->id]);
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1,
            'reconnection_date' => today()->toDateString(),
            'installation_scheduled_at' => now('Asia/Manila')->addMinute()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertNull($consumer->refresh()->reconnected_at);
        $record = $consumer->reconnections()->sole();
        $this->post(route('consumers.reconnection-complete', [$consumer, $record]))->assertSessionHasNoErrors();
        $this->assertNotNull($consumer->refresh()->reconnected_at);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()->assertSee('Connected');
        $this->assertTrue($user->fresh()->must_change_reactivation_password);
        $this->assertNotSame($password, $user->refresh()->password);
        $this->assertSame('INACTIVE-METER-100', $consumer->meter_number);
    }

    public function test_fee_is_admin_managed_and_changes_are_visible_without_relogin(): void
    {
        [$user, $consumer] = $this->account();
        $this->actingAs($user)->patch(route('consumers.reconnection-fee', $consumer), ['reconnection_fee' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('consumers.reconnection-fee', $consumer), ['reconnection_fee' => -5])->assertSessionHasErrors('reconnection_fee');
        $this->patch(route('consumers.reconnection-fee', $consumer), ['reconnection_fee' => 73.25])->assertSessionHasErrors('reconnection_fee');
        $this->actingAs($user)->get(route('account.inactive'))->assertSee('PHP 850.50')->assertDontSee('Reconnection Fee');
    }

    public function test_active_consumers_and_staff_keep_normal_access(): void
    {
        [$user] = $this->account('Active');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('account.inactive'))->assertRedirect(route('dashboard'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('dashboard'))->assertOk();
        $this->get(route('account.inactive'))->assertForbidden();
    }
}
