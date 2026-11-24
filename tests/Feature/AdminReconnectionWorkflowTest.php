<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\InstallationRescheduleRequest;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConsumerReconnectionService;
use App\Services\ConsumerStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminReconnectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 01:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => 'Reconnect Office Consumer',
            'email' => $user->email, 'account_number' => 'RECONNECT-100', 'meter_number' => 'RECONNECT-METER-100',
            'status' => 'Disconnected', 'membership_type' => 'Non-Member',
            'installation_date' => '2026-08-11', 'installation_time' => '08:15:00',
        ]);
        $consumer->update(['disconnected_at' => now()->subHour()]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id, 'billing_period' => '2026-10-01',
            'due_date' => '2026-10-31', 'prn' => 'RECONNECT-BILL-100', 'amount' => 300, 'status' => 'Unpaid',
        ]);
        DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'Disconnected',
            'issued_at' => now()->subDays(4), 'deadline_at' => now()->subDay(),
            'disconnected_at' => $consumer->disconnected_at, 'confirmed_by' => $this->admin->id,
        ]);

        return [$consumer->fresh(), $bill, $user];
    }

    private function payload(): array
    {
        return ['confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-05T09:30'];
    }

    private function payAtOffice(Bill $bill): void
    {
        $this->post(route('payments.store'), [
            'bill_id' => $bill->id, 'amount' => 300, 'payment_date' => today()->toDateString(),
            'payment_method' => 'Cash', 'payment_channel' => 'Counter',
        ])->assertSessionHasNoErrors();
    }

    public function test_office_payment_scheduling_reschedule_and_completion_preserve_reconnection_history(): void
    {
        [$consumer, $bill, $user] = $this->account();
        $disconnectionId = DisconnectionNotice::query()->sole()->id;
        $this->assertSame(300.0, app(ConsumerReconnectionService::class)->eligibility($consumer)['outstanding']);
        $this->payAtOffice($bill);
        $this->assertSame('For Reconnection', $consumer->refresh()->status);
        $payment = Payment::query()->sole()->getRawOriginal();
        $request = $consumer->serviceRequests()->create([
            'request_type' => ServiceRequest::TYPE_RECONNECTION, 'status' => ServiceRequest::STATUS_PENDING, 'requested_at' => now(),
        ]);
        $originalCompletion = now()->subMonths(2);
        $consumer->forceFill(['installation_completed_at' => $originalCompletion, 'installation_completed_by' => $this->admin->id])->save();

        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()
            ->assertSee('Reconnect')->assertSee('Confirm Reconnection and Activation')
            ->assertSee('Ready for Reconnection')->assertSee('Scheduled Reconnection Date & Time')->assertSee('Original Installation Date')
            ->assertSee('I confirm that the outstanding balance is zero and approve the reconnection schedule.')
            ->assertSee('data-original-installation-date="Aug 11, 2026"', false)->assertDontSee('Renewal Date');
        $this->post(route('consumers.reconnect', $consumer), $this->payload() + ['installation_date' => '2026-11-05', 'installation_time' => '09:30:00'])
            ->assertRedirect(route('consumers.index', ['account_category' => 'active']))->assertSessionHasNoErrors();

        $consumer->refresh();
        $this->assertSame('Active', $consumer->status);
        $this->assertSame(ConsumerStatusService::RECONNECTION_PENDING, app(ConsumerStatusService::class)->key($consumer));
        $this->assertSame('2026-11-05', $consumer->reconnection_date->toDateString());
        $this->assertSame('2026-11-05 01:30:00', $consumer->reconnection_scheduled_at->toDateTimeString());
        $this->assertNull($consumer->reconnected_at);
        $this->assertFalse(Consumer::query()->readingQueueEligible()->whereKey($consumer->id)->exists());
        $this->assertSame('2026-08-11', $consumer->installation_date->toDateString());
        $this->assertSame('08:15:00', $consumer->installation_time);
        $reconnection = $consumer->reconnections()->sole();
        $this->assertSame('2026-11-05 01:30:00', $reconnection->installation_scheduled_at->toDateTimeString());
        $this->assertSame('2026-11-05 01:00:00', $reconnection->activated_at->toDateTimeString());
        $this->assertNull($reconnection->completed_at);
        $this->assertSame(ServiceRequest::STATUS_APPROVED, $request->refresh()->status);
        $this->assertDatabaseHas('consumer_reconnections', ['consumer_id' => $consumer->id, 'confirmed_by' => $this->admin->id, 'reconnection_fee' => 0]);
        $this->assertDatabaseHas('consumer_notification_logs', ['consumer_id' => $consumer->id, 'event' => 'consumer_reconnected_1', 'status' => 'sent']);
        $notification = $user->notifications()->where('data', 'like', '%Account Activated%')->firstOrFail();
        $this->assertStringContainsString('RECONNECT-100', $notification->data['message']);
        $this->assertStringContainsString('November 05, 2026 9:30 AM', $notification->data['message']);
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->filter(fn ($sent) => $sent->getOriginalMessage()->getSubject() === \App\Mail\ReconnectionScheduleConfirmation::SUBJECT)->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('Active / Pending Reconnection', $mail->getHtmlBody());
        $this->assertStringContainsString('November 05, 2026', $mail->getHtmlBody());
        $this->assertStringContainsString('9:30 AM', $mail->getHtmlBody());
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertDontSee($consumer->full_name);
        $pendingList = route('consumers.index', ['account_category' => 'active', 'installation_status' => 'pending']);
        $completedList = route('consumers.index', ['account_category' => 'active', 'installation_status' => 'completed']);
        $this->get($pendingList)->assertSee($consumer->full_name)->assertSee('Pending Reconnection')->assertSee('Nov 05, 2026 9:30 AM')
            ->assertSee('Mark as Completed')->assertDontSee('>Connected<', false);
        $this->get($completedList)->assertDontSee($consumer->full_name);
        $history = route('consumers.index', ['account_category' => 'disconnection_history']);
        $this->get($history)->assertSee('Pending Reconnection')->assertSee('November 05, 2026 9:30 AM');
        $this->assertTrue($user->fresh()->must_change_reactivation_password);
        $this->actingAs($user->fresh())->get(route('connection.status'))->assertOk()
            ->assertSee('November 05, 2026 9:30 AM')->assertSee('Request Reschedule');

        $requestRoute = route('reconnection.reschedule.store', $reconnection);
        $this->post($requestRoute, ['preferred_date' => '2026-11-06', 'preferred_time' => '14:45', 'reason' => 'Work schedule conflict.'])->assertSessionHas('success');
        $reschedule = $consumer->installationRescheduleRequests()->pending()->sole();
        $complete = route('consumers.reconnection-complete', [$consumer, $reconnection]);
        $this->post($complete)->assertForbidden();
        $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $reschedule), [
            'new_installation_date' => '2026-11-06', 'new_installation_time' => '14:45',
        ])->assertSessionHas('success');
        $this->assertSame('2026-11-06 06:45:00', $consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
        $this->assertNull($consumer->reconnected_at);
        $updatedMail = Mail::mailer()->getSymfonyTransport()->messages()->filter(fn ($sent) => str_contains($sent->getOriginalMessage()->getSubject(), 'Updated Reconnection Schedule'))->sole()->getOriginalMessage();
        $this->assertStringContainsString('November 06, 2026 at 2:45 PM', $updatedMail->getHtmlBody());
        $this->get($pendingList)->assertSee('Nov 06, 2026 2:45 PM');

        $this->actingAs($user->fresh())->post($requestRoute, ['preferred_date' => '2026-11-07', 'preferred_time' => '14:45', 'reason' => 'Another schedule conflict.'])->assertSessionHas('success');
        $unassigned = $consumer->installationRescheduleRequests()->pending()->sole();
        Carbon::setTestNow('2026-11-06 07:00:00');
        $this->actingAs($this->admin)->get($pendingList)->assertSee('Pending Reconnection');
        $this->assertNull($consumer->refresh()->reconnected_at);
        $this->post($complete)->assertSessionHas('success');
        $this->assertFalse($consumer->refresh()->reconnection_pending);
        $this->assertSame(ConsumerStatusService::ACTIVE, app(ConsumerStatusService::class)->key($consumer));
        $this->assertSame('2026-11-06 07:00:00', $consumer->reconnected_at->toDateTimeString());
        $this->assertTrue($consumer->reconnected_at->equalTo($reconnection->refresh()->completed_at));
        $this->assertSame($this->admin->id, $reconnection->completed_by);
        $this->assertSame(ServiceRequest::STATUS_COMPLETED, $request->refresh()->status);
        $this->assertSame(InstallationRescheduleRequest::STATUS_CANCELLED, $unassigned->refresh()->status);
        $this->assertTrue($consumer->installation_completed_at->equalTo($originalCompletion));
        $this->assertTrue(Consumer::query()->readingQueueEligible()->whereKey($consumer->id)->exists());
        $this->get($pendingList)->assertViewHas('consumers', fn ($consumers) => $consumers->isEmpty());
        $this->get($completedList)->assertSee($consumer->full_name)->assertSee('Connected')->assertSee('Nov 06, 2026 3:00 PM')->assertDontSee('Mark as Completed');
        $this->get($history)->assertSee('Nov 06, 2026 3:00:00 PM')->assertSee('November 06, 2026 2:45 PM');
        $this->get($history)->assertSee('Nov 06, 2026 3:00:00 PM');
        Carbon::setTestNow(now()->addHour());
        $this->post($complete)->assertSessionHas('success');
        $this->assertSame('2026-11-06 07:00:00', $reconnection->refresh()->completed_at->toDateTimeString());
        $this->patch(route('installation-reschedules.assign', $unassigned), [
            'new_installation_date' => '2026-11-07', 'new_installation_time' => '14:45',
        ])->assertSessionHasErrors('reschedule');
        $this->assertSame($payment, Payment::query()->sole()->getRawOriginal());
        $this->assertSame($disconnectionId, DisconnectionNotice::query()->sole()->id);
        $this->assertDatabaseCount('consumer_reconnections', 1);
        $this->actingAs($user->fresh())->get(route('connection.status'))->assertOk()->assertSee('Reconnection completed on')->assertDontSee('Request Reschedule');
        $this->get(route('reconnection.reschedule', $reconnection))->assertForbidden();
        $this->post($requestRoute, ['preferred_date' => '2026-11-07', 'preferred_time' => '14:45', 'reason' => 'Already completed.'])->assertForbidden();
    }

    public function test_active_accounts_sort_activation_and_reactivation_together_across_filters_and_pages(): void
    {
        $start = now();
        [$consumer, $bill] = $this->account();
        $consumer->forceFill(['created_at' => $start->copy()->subYear(), 'verified_at' => $start->copy()->subYear()])->saveQuietly();
        $this->payAtOffice($bill);

        $activeAccount = fn (string $suffix, array $attributes = []) => Consumer::forceCreate($attributes + [
            'full_name' => 'Reconnect Sort '.$suffix,
            'account_number' => 'SORT-'.$suffix,
            'meter_number' => 'SORT-METER-'.$suffix,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'installation_date' => today()->subDay(),
            'verified_at' => now()->subYear(),
        ]);

        $previous = $activeAccount('Previous', ['reconnection_pending' => true, 'reconnection_scheduled_at' => now()->addDays(2)]);
        foreach ([1, 8] as $daysAgo) {
            $previous->reconnections()->create([
                'confirmed_by' => $this->admin->id,
                'activated_at' => now()->subDays($daysAgo),
                'reconnection_date' => today()->addDays(2),
                'installation_scheduled_at' => $previous->reconnection_scheduled_at,
                'reconnection_fee' => 0,
                'verified_payment_ids' => [],
            ]);
        }
        $legacy = $activeAccount('Legacy', ['reconnected_at' => now()->subDays(4)]);
        $legacy->reconnections()->create([
            'confirmed_by' => $this->admin->id,
            'activated_at' => now()->subDays(7),
            'reconnection_date' => today()->subDays(7),
            'installation_scheduled_at' => now()->subDays(7),
            'reconnection_fee' => 0,
            'verified_payment_ids' => [],
        ]);
        $between = $activeAccount('Between', ['verified_at' => now()->subDays(3)]);
        $activeAccount('Member', ['membership_type' => 'Member']);
        $activeAccount('Completed', ['installation_completed_at' => now()->subDay()]);
        $activeAccount('Different Name', ['full_name' => 'Different Consumer']);

        $ordinary = [];
        foreach (range(1, 11) as $index) {
            $ordinary[] = $activeAccount((string) $index, [
                'verified_at' => now()->subDays(5)->subMinutes($index),
                'created_at' => now()->subYear()->addMinutes($index),
            ])->id;
        }

        $active = route('consumers.index', ['account_category' => 'active']);
        $this->post(route('consumers.reconnect', $consumer), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect($active);
        $this->get($active)->assertOk()
            ->assertViewHas('consumers', fn ($consumers) => $consumers->first()->id === $consumer->id);

        Carbon::setTestNow($start->copy()->addMinutes(10));
        $applicant = Consumer::forceCreate([
            'full_name' => 'Reconnect Sort Newly Activated', 'status' => 'Pending Verification',
            'email' => 'sort-new-activation@example.test', 'membership_type' => 'Non-Member',
            'created_at' => $start->copy()->subYears(2),
        ]);
        $this->patch(route('consumers.activate', $applicant), [
            'meter_number' => 'SORT-METER-NEW', 'membership_type' => 'Non-Member', 'installation_date' => today()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($applicant->fresh()->verified_at->equalTo(now()));

        $filters = ['account_category' => 'active', 'search' => 'Reconnect', 'membership' => 'non_member', 'installation_status' => 'pending'];
        $expected = [$applicant->id, $consumer->id, $previous->id, $between->id, $legacy->id, ...$ordinary];
        foreach ([1, 2, 1] as $page) {
            $accounts = $this->get(route('consumers.index', $filters + ['page' => $page]))->assertOk()->viewData('consumers');
            $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), $accounts->getCollection()->modelKeys());
            $this->assertSame(count($expected), $accounts->total());
        }

        Carbon::setTestNow($start->copy()->addHour());
        $reconnection = $consumer->reconnections()->sole();
        $this->post(route('consumers.reconnection-complete', [$consumer, $reconnection]))->assertSessionHasNoErrors();
        $this->get($active)->assertOk()->assertViewHas('consumers', fn ($accounts) => $accounts->pluck('id')->take(2)->all() === [$consumer->id, $applicant->id]);
    }

    public function test_unpaid_and_pending_payments_cannot_be_confirmed_by_a_checkbox(): void
    {
        [$consumer, $bill] = $this->account();
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference_number' => 'PENDING-OFFICE',
            'amount' => 300, 'payment_date' => today(), 'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Pending',
        ]);
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasErrors('reconnection');
        $this->assertSame('Disconnected', $consumer->refresh()->status);

        $this->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_bare_paid_status_or_forged_verification_marker_cannot_bypass_payment_records(): void
    {
        [$consumer, $bill] = $this->account();
        $consumer->update(['full_payment_verified_at' => now()]);
        $bill->update(['status' => 'Paid']);
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasErrors('reconnection');
        $this->assertDatabaseCount('consumer_reconnections', 0);
    }

    public function test_initial_activation_and_request_completion_cannot_bypass_the_reconnect_payment_gate(): void
    {
        [$consumer] = $this->account();
        $originalAccount = $consumer->account_number;
        $this->patch(route('consumers.activate', $consumer), [
            'meter_number' => $consumer->meter_number, 'membership_type' => 'Non-Member', 'installation_date' => today()->toDateString(),
        ])->assertSessionHasErrors('reconnection');
        $this->assertSame($originalAccount, $consumer->refresh()->account_number);
        $this->assertSame('Disconnected', $consumer->status);
        $this->assertNull($consumer->reconnected_at);
    }

    public function test_balance_settlement_allows_reconnection_without_a_fee_assessment(): void
    {
        [$consumer, $bill] = $this->account();
        Payment::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference_number' => 'BASE-ONLY',
            'amount' => 300, 'payment_date' => today(), 'payment_method' => 'Cash', 'payment_channel' => 'Counter',
            'status' => 'Verified', 'verified_at' => now(), 'verified_by' => $this->admin->id,
        ]);
        $this->assertNull($consumer->refresh()->reconnection_fee);
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_only_admins_can_reconnect_and_timestamp_and_confirmation_are_required(): void
    {
        [$consumer, $bill, $user] = $this->account();
        $this->payAtOffice($bill);
        $this->post(route('consumers.reconnect', $consumer), [])->assertSessionHasErrors(['confirm_full_payment', 'installation_scheduled_at']);
        foreach (['2026-11-06', '2026-11-06T25:30', '2026-11-04T09:00', '2026-11-05T08:59:59'] as $invalidTimestamp) {
            $this->post(route('consumers.reconnect', $consumer), array_merge($this->payload(), ['installation_scheduled_at' => $invalidTimestamp]))
                ->assertSessionHasErrors('installation_scheduled_at');
        }
        $this->actingAs($user)->post(route('consumers.reconnect', $consumer), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('consumers.reconnect', $consumer), $this->payload())->assertForbidden();
    }

    public function test_repeated_confirmation_does_not_duplicate_history_or_activation_email(): void
    {
        [$consumer, $bill] = $this->account();
        $this->payAtOffice($bill);
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHasErrors('reconnection');
        $this->assertDatabaseCount('consumer_reconnections', 1);
        $this->assertSame(1, ConsumerNotificationLog::query()->where('event', 'like', 'consumer_reconnected_%')->count());
    }

    public function test_smtp_failure_is_logged_without_undoing_authorized_activation(): void
    {
        [$consumer, $bill] = $this->account();
        $this->payAtOffice($bill);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post(route('consumers.reconnect', $consumer), $this->payload())->assertSessionHas('email_error');
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'failed']);
    }
}
