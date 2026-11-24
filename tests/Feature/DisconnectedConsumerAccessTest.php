<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisconnectedConsumerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function disconnectForArrears(): array
    {
        $user = User::factory()->create([
            'role' => 'consumer',
            'email' => 'disconnected.consumer@example.com',
            'username' => 'DC-M-1',
        ]);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Disconnected Consumer',
            'email' => 'disconnected.consumer@example.com',
            'account_number' => 'DC-1',
            'meter_number' => 'DC-M-1',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        foreach (['2026-05-01', '2026-06-01', '2026-07-01'] as $period) {
            Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => $period,
                'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(),
                'prn' => 'DC-'.str_replace('-', '', $period),
                'amount' => 300.00,
                'status' => 'Unpaid',
            ]);
        }

        $consumer->syncAccountStanding();

        // Three unpaid months raise a notice while the service stays active.
        $this->assertSame('Active', $consumer->refresh()->status);

        $admin = User::factory()->create(['role' => 'admin']);
        $notice = app(DisconnectionNoticeService::class)->activeNoticeFor($consumer->id);

        $this->assertNotNull($notice);

        // Once the three-day payment window has elapsed, the administrator
        // confirms the physical disconnection.
        $notice->forceFill([
            'issued_at' => now()->subDays(5),
            'deadline_at' => now()->subDay(),
        ])->save();

        app(DisconnectionNoticeService::class)->confirmDisconnection($consumer->fresh(), $admin);

        $this->assertSame('Disconnected', $consumer->refresh()->status);

        return [$user->fresh(), $consumer, $admin];
    }

    private function credentials(string $password = 'password'): array
    {
        return ['login' => 'DC-M-1', 'password' => $password];
    }

    public function test_a_disconnected_consumer_can_sign_in(): void
    {
        [$user] = $this->disconnectForArrears();
        $this->assertSame('Disconnected', $user->consumer->status);

        $this->post(route('login.attempt'), $this->credentials())
            ->assertRedirect(route('account.inactive'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_consumer_awaiting_reconnection_can_sign_in(): void
    {
        [$user, $consumer, $admin] = $this->disconnectForArrears();

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'bill_id' => Bill::query()->where('consumer_id', $consumer->id)->orderByDesc('billing_period')->value('id'),
                'amount' => 900.00,
                'payment_date' => now()->toDateString(),
                'payment_channel' => 'Counter',
                'payment_method' => 'Cash',
            ]);

        $this->assertSame('For Reconnection', $consumer->refresh()->status);

        // The login route sits behind guest middleware, so sign the admin out first.
        $this->post(route('logout'));

        $this->post(route('login.attempt'), $this->credentials())
            ->assertRedirect(route('account.inactive'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_disconnected_consumer_sees_the_balance_only_in_the_popup_and_pays_at_the_office(): void
    {
        [$user, $consumer, $admin] = $this->disconnectForArrears();

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 900.00');
        $this->post(route('payments.store'), [])->assertForbidden();

        $payment = Payment::query()->create([
            'bill_id' => Bill::query()->where('consumer_id', $consumer->id)->orderByDesc('billing_period')->value('id'),
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-DC-1',
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        app(PaymentAllocationService::class)->allocateVerifiedFullPayment(
            $payment,
            $admin,
        );

        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertSame('For Reconnection', $consumer->refresh()->status);
    }

    public function test_reconnection_is_refused_while_a_balance_is_outstanding(): void
    {
        Storage::fake('local');

        [$user, $consumer] = $this->disconnectForArrears();

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), [
                'request_type' => 'Reconnection',
                'information' => 'Confirmation email received from the association.',
                'proof' => UploadedFile::fake()->create('proof.png', 20, 'image/png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->count());

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), ['request_type' => 'Reconnection'])
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_verified_payment_does_not_unlock_consumer_portal_requests_before_activation(): void
    {
        Storage::fake('local');

        [$user, $consumer, $admin] = $this->disconnectForArrears();

        $payment = Payment::query()->create([
            'bill_id' => Bill::query()->where('consumer_id', $consumer->id)->orderByDesc('billing_period')->value('id'),
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-DC-2',
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        app(PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $admin);
        $this->assertSame('For Reconnection', $consumer->refresh()->status);

        app(DisconnectionNoticeService::class)
            ->grantEligibilityIfFullySettled($consumer->fresh(), 900.00);

        $this->actingAs($user)
            ->post(route('billing.requests.store'), [
                'request_type' => 'Reconnection',
                'information' => 'Confirmation email received from the association.',
                'proof' => UploadedFile::fake()->create('proof.png', 20, 'image/png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->where('status', 'Pending')->count());
    }

    public function test_rejected_and_unverified_applications_are_still_blocked(): void
    {
        $rejectedUser = User::factory()->create(['role' => 'consumer', 'email' => 'rejected.person@example.com']);
        Consumer::query()->create([
            'user_id' => $rejectedUser->id,
            'full_name' => 'Rejected Person',
            'email' => 'rejected.person@example.com',
            'account_number' => 'RJ-1',
            'meter_number' => 'DC-RJ-1',
            'status' => 'Rejected',
        ]);

        $this->post(route('login.attempt'), ['login' => 'DC-RJ-1', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $pendingUser = User::factory()->create(['role' => 'consumer', 'email' => 'pending.person@example.com']);
        Consumer::query()->create([
            'user_id' => $pendingUser->id,
            'full_name' => 'Pending Person',
            'email' => 'pending.person@example.com',
            'meter_number' => 'DC-PD-1',
            'status' => 'Pending Verification',
        ]);

        $this->post(route('login.attempt'), ['login' => 'DC-PD-1', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
