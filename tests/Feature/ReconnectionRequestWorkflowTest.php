<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusNotification;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconnectionRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ?User $consumerUser = null;

    public function test_reconnection_portal_stays_blocked_even_after_the_balance_is_settled(): void
    {
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $this->disconnectWithUnpaidMonths($consumer);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertSee('PHP 900.00');

        $this->payAll($consumer);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertSee('PHP 0.00');
    }

    public function test_the_request_button_stays_locked_until_the_payment_is_verified(): void
    {
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer, verifyWithAdmin: false);

        // Settled but not yet approved: the consumer must wait for the admin.
        $this->assertNull($consumer->refresh()->full_payment_verified_at);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertRedirect(route('account.inactive'));
    }

    public function test_verifying_the_full_payment_grants_reconnection_eligibility(): void
    {
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer, verifyWithAdmin: true);

        $consumer->refresh();

        $this->assertNotNull($consumer->full_payment_verified_at);
        $this->assertSame('For Reconnection', $consumer->status);

        // The confirmation is recorded so it is never emailed twice.
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertRedirect(route('account.inactive'));
    }

    public function test_backend_rejects_reconnection_while_a_balance_remains(): void
    {
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $this->disconnectWithUnpaidMonths($consumer);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), array_merge(
                $this->reconnectionPayload(),
                ['notes' => 'Trying early'],
            ))
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_backend_rejects_reconnection_when_the_payment_is_not_yet_verified(): void
    {
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer, verifyWithAdmin: false);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), $this->reconnectionPayload())
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_backend_rejects_reconnection_for_accounts_that_were_never_disconnected(): void
    {
        $user = $this->consumerAccount(['status' => 'Active']);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), $this->reconnectionPayload())
            ->assertSessionHasErrors('request_type');

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_inactive_consumers_cannot_submit_requests_with_any_proof_payload(): void
    {
        Storage::fake('local');

        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), ['request_type' => 'Reconnection'])
            ->assertForbidden();

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), [
                'request_type' => 'Reconnection',
                'information' => 'Confirmation email received on 12 September.',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), [
                'request_type' => 'Reconnection',
                'information' => 'Confirmation email received on 12 September.',
                'proof' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain'),
            ])
            ->assertForbidden();

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_even_a_complete_request_is_blocked_after_payment_without_creating_records(): void
    {
        Storage::fake('local');

        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $this->actingAs($user)
            ->post(route('billing.requests.store'), $this->reconnectionPayload())
            ->assertForbidden();
        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.requests.store'), $this->reconnectionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_the_administrator_can_open_the_uploaded_proof(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $request = $this->storedRequest($consumer);
        Storage::disk('local')->put('proof/legacy-proof.png', 'Legacy proof retained');
        $request->update(['proof_path' => 'proof/legacy-proof.png']);

        $this->actingAs($admin)
            ->get(route('billing.requests.proof', $request))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('billing.requests.proof', $request))
            ->assertRedirect(route('account.inactive'));

        // Nobody else may.
        $other = User::factory()->create(['role' => 'consumer']);
        Consumer::query()->create([
            'user_id' => $other->id,
            'full_name' => 'Other Consumer',
            'email' => 'other.consumer@example.com',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $this->actingAs($other)
            ->get(route('billing.requests.proof', $request))
            ->assertForbidden();
    }

    public function test_admin_can_approve_without_restoring_the_supply(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $request = $this->storedRequest($consumer);

        $this->assertSame('For Reconnection', $consumer->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), [
                'status' => 'Approved',
                'admin_notes' => 'Approved for scheduled restoration.',
            ])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success', 'Service request updated successfully.');

        $request->refresh();

        $this->assertSame('Approved', $request->status);
        $this->assertNotNull($request->reviewed_at);
        $this->assertSame($admin->id, $request->reviewed_by_user_id);

        // Supply is NOT auto-restored: the account waits for the procedure.
        $this->assertSame('For Reconnection', $consumer->refresh()->status);
        $this->assertNotSame('Active', $consumer->status);
    }

    public function test_admin_can_reject_and_complete_a_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $request = $this->storedRequest($consumer);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), [
                'status' => 'Rejected',
                'admin_notes' => 'Please provide supporting documents.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Rejected', $request->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), [
                'status' => 'Completed',
                'admin_notes' => 'Supply restored under association procedure.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Completed', $request->refresh()->status);
    }

    public function test_admin_cannot_approve_a_reconnection_with_an_outstanding_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;

        $request = $this->storedRequest($consumer);

        $this->createBill($consumer, 'RC-APPROVE-BLOCK', 500.00, '2026-07-01');

        $this->actingAs($admin)
            ->from(route('billing.index'))
            ->patch(route('billing.requests.update', $request), ['status' => 'Approved'])
            ->assertSessionHasErrors('status');

        $this->assertSame('Pending', $request->refresh()->status);
    }

    public function test_consumer_is_notified_of_every_status_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $request = $this->storedRequest($consumer);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), [
                'status' => 'Approved',
                'admin_notes' => 'Approved for scheduled restoration.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Approved', $request->refresh()->status);
        $this->assertNotNull($request->reviewed_at);

        // The consumer receives the status change through the database channel.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'type' => ServiceRequestStatusNotification::class,
        ]);

        $notification = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('type', ServiceRequestStatusNotification::class)
            ->first();

        $payload = json_decode($notification->data, true);

        $this->assertSame('Approved', $payload['status']);
        $this->assertStringContainsString('approved', strtolower($payload['message']));

        // A further decision produces another notification.
        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), ['status' => 'Completed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Completed', $request->refresh()->status);

        $all = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('type', ServiceRequestStatusNotification::class)
            ->pluck('data')
            ->map(fn ($data) => json_decode($data, true)['status'] ?? '?')
            ->all();

        $this->assertSame(['Approved', 'Completed'], $all);
    }

    public function test_each_decision_emails_the_customer_exactly_once(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        $request = $this->storedRequest($consumer);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), ['status' => 'Approved'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'service_request_'.$request->id.'_approved',
            'status' => 'sent',
        ]);

        // Re-saving the same decision must not email the customer again.
        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), ['status' => 'Approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('consumer_notification_logs')
            ->where('event', 'service_request_'.$request->id.'_approved')
            ->count());

        // A different decision is a different email.
        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), ['status' => 'Completed'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'service_request_'.$request->id.'_completed',
            'status' => 'sent',
        ]);
    }

    public function test_request_history_is_retained_for_auditing(): void
    {
        Storage::fake('local');

        $user = $this->consumerAccount();
        $consumer = $user->consumer;
        $this->disconnectWithUnpaidMonths($consumer);
        $this->payAll($consumer);

        foreach (['Rejected', 'Pending'] as $status) {
            ServiceRequest::query()->create([
                'consumer_id' => $consumer->id,
                'request_type' => 'Reconnection',
                'status' => $status,
                'requested_at' => now()->subDay(),
            ]);
        }

        $this->assertSame(2, ServiceRequest::query()->where('consumer_id', $consumer->id)->count());

        $admin = User::query()->where('role', 'admin')->sole();
        $this->actingAs($admin)->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => today()->toDateString(),
            'installation_scheduled_at' => now('Asia/Manila')->addMinute()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $record = $consumer->reconnections()->sole();
        $this->post(route('consumers.reconnection-complete', [$consumer, $record]))->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertOk()
            ->assertSee('Rejected')->assertSee('Completed');
    }

    private function reconnectionPayload(): array
    {
        return [
            'request_type' => 'Reconnection',
            'information' => 'Confirmation email received from the association on 12 September.',
            'proof' => UploadedFile::fake()->create('reconnection-proof.png', 20, 'image/png'),
        ];
    }

    private function storedRequest(Consumer $consumer): ServiceRequest
    {
        return ServiceRequest::query()->create([
            'consumer_id' => $consumer->id,
            'request_type' => 'Reconnection',
            'status' => 'Pending',
            'requested_at' => now(),
        ]);
    }

    private function consumerAccount(array $overrides = []): User
    {
        $user = User::factory()->create(['role' => 'consumer']);

        Consumer::query()->create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Reconnection Consumer',
            'email' => 'reconnection.consumer@example.com',
            'account_number' => 'RC-1001',
            'meter_number' => 'RC-M-1001',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'contact_number' => '09171234567',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $overrides));

        // Hold on to the exact instance the tests act as, so the cached
        // consumer relation can be dropped after a state change.
        return $this->consumerUser = $user->fresh();
    }

    /**
     * Three consecutive unpaid billing months past their grace period raise a
     * notice while the service stays active. The administrator then confirms
     * the physical disconnection after the three-day payment window.
     */
    private function disconnectWithUnpaidMonths(Consumer $consumer): void
    {
        $this->createBill($consumer, 'RC-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'RC-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'RC-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->assertSame('Active', $consumer->refresh()->status);

        $admin = User::query()->where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $notice = app(DisconnectionNoticeService::class)->activeNoticeFor($consumer->id);

        $this->assertNotNull($notice);

        $notice->forceFill([
            'issued_at' => now()->subDays(5),
            'deadline_at' => now()->subDay(),
        ])->save();

        app(DisconnectionNoticeService::class)->confirmDisconnection($consumer->fresh(), $admin);

        $this->assertSame('Disconnected', $consumer->refresh()->status);
    }

    /**
     * Settles the account the way an administrator would, optionally running the
     * verification step that grants reconnection eligibility.
     */
    private function payAll(Consumer $consumer, bool $verifyWithAdmin = true): void
    {
        $balances = app(ConsumerBalanceService::class);
        $admin = User::query()->where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        $total = $balances->totalOutstandingFor($consumer->id);

        if ($total <= 0) {
            return;
        }

        $target = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->orderByDesc('billing_period')
            ->firstOrFail();

        $payment = Payment::query()->create([
            'bill_id' => $target->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-RC-'.uniqid(),
            'amount' => $total,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        app(PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $admin);

        $this->assertEquals(0.00, $balances->totalOutstandingFor($consumer->id));

        if ($verifyWithAdmin) {
            app(DisconnectionNoticeService::class)
                ->grantEligibilityIfFullySettled($consumer->fresh(), $total);
        }

        // A real request always loads a fresh consumer, so drop the cached
        // relation on the acting user to match that.
        $this->consumerUser?->unsetRelation('consumer');
    }

    private function createBill(Consumer $consumer, string $prn, float $amount, string $period): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(),
            'prn' => $prn,
            'amount' => $amount,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
    }
}
