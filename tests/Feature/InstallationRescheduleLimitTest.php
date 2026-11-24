<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\InstallationRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InstallationRescheduleLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Consumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 08:00:00');
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'consumer']);
        $this->consumer = Consumer::query()->create([
            'user_id' => $this->user->id, 'full_name' => 'Three Reschedules Consumer', 'email' => $this->user->email,
            'account_number' => 'LIMIT-100', 'meter_number' => 'LIMIT-METER-100', 'status' => 'Active',
            'membership_type' => 'Member', 'installation_date' => '2026-11-10', 'installation_time' => '09:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function requestConnection(): InstallationRescheduleRequest
    {
        $this->actingAs($this->user)->post(route('installation.reschedule.connection.store', $this->consumer), ['reason' => 'Work conflict.'])->assertSessionHas('success');

        return $this->consumer->installationRescheduleRequests()->pending()->latest('id')->firstOrFail();
    }

    private function assign(InstallationRescheduleRequest $request, string $date): void
    {
        $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $request), [
            'new_installation_date' => $date, 'new_installation_time' => '14:00', 'admin_notes' => 'New schedule assigned.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_three_complete_reschedules_email_each_assignment_and_disable_consumer_and_backend_after_the_third(): void
    {
        $emails = [];
        Mail::shouldReceive('send')->times(3)->withArgs(function (string $view, array $data) use (&$emails): bool {
            $emails[] = view($view, $data)->render();

            return $view === 'emails.consumer.installation-rescheduled';
        });
        foreach (['2026-11-12', '2026-11-14', '2026-11-16'] as $index => $date) {
            $number = $index + 1;
            $request = $this->requestConnection();
            $this->assertSame($index, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
            $response = $this->actingAs($this->admin)->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']))->assertOk();
            $response->assertSee('Assign New Schedule')->assertDontSee('arrApproveForm')->assertDontSee('arrRejectForm');
            $this->assign($request, $date);
            $this->assertSame('assigned', $request->refresh()->status);
            $this->assertSame($number, $request->assignment_number);
            $this->assertNotNull($request->assigned_at);
            $this->assertSame($date, $this->consumer->refresh()->installation_date->toDateString());
            $this->assertSame('14:00:00', $this->consumer->installation_time);
            $this->assertSame($number, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
            $this->assertStringContainsString(InstallationRescheduleService::REMINDER, $emails[$index]);
            $this->assertStringContainsString($number.' of 3 used', $emails[$index]);
            $this->assertStringContainsString('2:00 PM', $emails[$index]);
            if ($number < 3) {
                $this->assertStringNotContainsString(InstallationRescheduleService::FINAL_WARNING, $emails[$index]);
            } else {
                $this->assertStringContainsString(InstallationRescheduleService::FINAL_WARNING, $emails[$index]);
                $this->assertStringNotContainsString('>Request for Reschedule</a>', $emails[$index]);
            }
            $this->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-20', 'new_installation_time' => '15:00'])
                ->assertSessionHasErrors('reschedule');
            $this->assertSame($number, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        }
        $this->actingAs($this->user)->get(route('connection.status'))->assertOk()->assertSee('3 of 3 used')
            ->assertSee('0 reschedule attempts remaining')->assertSee('disabled>Reschedule Installation', false);
        $this->get(URL::signedRoute('installation.reschedule.connection', $this->consumer))->assertOk()
            ->assertSee('3 of 3 used')->assertSee('disabled>Reschedule Installation', false)->assertDontSee('name="reason"', false);
        $this->post(route('installation.reschedule.connection.store', $this->consumer), ['reason' => 'Fourth attempt.'])->assertSessionHasErrors('reschedule');
        $this->assertDatabaseCount('installation_reschedule_requests', 3);
        $this->assertSame(3, DB::table('consumer_notification_logs')->where('event', 'like', 'installation_reschedule_%_assigned')->where('status', 'sent')->count());
        $this->assertSame('2026-11-16', $this->consumer->refresh()->installation_date->toDateString());
        $this->actingAs($this->admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk()->assertSee('3 of 3 used');
    }

    public function test_reconnection_uses_its_own_quota_and_preserves_installation_enforcement(): void
    {
        Mail::shouldReceive('send')->times(3)->andReturn(\Mockery::mock(\Illuminate\Mail\SentMessage::class));
        $first = $this->requestConnection();
        $this->assign($first, '2026-11-12');
        $loan = Loan::query()->create([
            'consumer_id' => $this->consumer->id, 'amount' => 1200, 'term_months' => 6, 'purpose' => 'Meter installation',
            'monthly_installment' => 200, 'status' => 'Approved', 'installation_status' => 'Scheduled',
            'installation_date' => '2026-11-10', 'installation_time' => '09:00:00', 'applied_at' => now(),
        ]);
        $this->actingAs($this->user)->post(route('installation.reschedule.loan.store', $loan), ['reason' => 'Loan schedule conflict.'])->assertSessionHas('success');
        $second = $this->consumer->installationRescheduleRequests()->pending()->sole();
        $this->assign($second, '2026-11-14');
        $this->assertSame('2026-11-14', $loan->refresh()->installation_date->toDateString());
        $this->consumer->forceFill(['reconnection_pending' => true, 'reconnection_scheduled_at' => '2026-11-12 06:00:00'])->save();
        $reconnection = ConsumerReconnection::query()->create([
            'consumer_id' => $this->consumer->id, 'confirmed_by' => $this->admin->id, 'reconnection_date' => '2026-11-12',
            'installation_scheduled_at' => '2026-11-12 06:00:00', 'activated_at' => now(), 'reconnection_fee' => 0, 'verified_payment_ids' => [],
        ]);
        $this->actingAs($this->user)->post(route('reconnection.reschedule.store', $reconnection), ['preferred_date' => '2026-11-16', 'preferred_time' => '14:00', 'reason' => 'Reconnection conflict.'])->assertSessionHas('success');
        $third = $this->consumer->installationRescheduleRequests()->pending()->sole();
        $this->assign($third, '2026-11-16');
        $this->assertSame([1, 2, 3], $this->consumer->installationRescheduleRequests()->orderBy('id')->pluck('assignment_number')->all());
        $this->actingAs($this->user)->get(route('installation.reschedule.loan', $loan))->assertSee('3 of 3 used');
        $this->get(route('reconnection.reschedule', $reconnection))->assertSee('1 of 3 used');
        $this->post(route('installation.reschedule.loan.store', $loan), ['reason' => 'Fourth loan request.'])->assertSessionHasErrors('reschedule');
        $this->post(route('reconnection.reschedule.store', $reconnection), ['preferred_date' => '2026-11-20', 'preferred_time' => '12:00', 'reason' => 'Second reconnection request.'])->assertSessionHas('success');
        $pending = InstallationRescheduleRequest::query()->create([
            'consumer_id' => $this->consumer->id, 'reschedule_type' => 'connection', 'status' => 'pending',
            'reason' => 'Older pending request.', 'original_installation_date' => '2026-11-16',
        ]);
        $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $pending), ['new_installation_date' => '2026-11-20', 'new_installation_time' => '12:00'])->assertSessionHasErrors('reschedule');
        $this->assertNull($pending->refresh()->assigned_at);
        $this->assertSame(3, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
    }

    public function test_invalid_identical_or_unauthorized_assignments_and_pending_requests_do_not_use_attempts(): void
    {
        $request = $this->requestConnection();
        $this->assertSame(0, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-12'])->assertSessionHasErrors('new_installation_time');
        $this->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-05', 'new_installation_time' => '12:00'])->assertSessionHasErrors('new_installation_date');
        $this->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-10', 'new_installation_time' => '09:00'])->assertSessionHasErrors('new_installation_time');
        $this->actingAs($this->user)->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-12', 'new_installation_time' => '12:00'])->assertForbidden();
        $this->assertSame(0, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertSame('2026-11-10', $this->consumer->refresh()->installation_date->toDateString());
    }

    public function test_email_failure_does_not_lose_assignment_and_retry_does_not_count_again(): void
    {
        $request = $this->requestConnection();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $request), ['new_installation_date' => '2026-11-12', 'new_installation_time' => '14:00'])->assertSessionHas('email_error');
        $this->assertSame(1, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $this->assertSame('2026-11-12', $this->consumer->refresh()->installation_date->toDateString());
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'installation_reschedule_'.$request->id.'_assigned', 'status' => 'failed']);
        Mail::shouldReceive('send')->once()->andReturnNull();
        $this->assertTrue(app(InstallationRescheduleService::class)->sendAssignmentEmail($request->refresh()));
        $this->assertSame(1, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'installation_reschedule_'.$request->id.'_assigned', 'status' => 'sent']);
    }

    public function test_existing_assigned_history_is_preserved_and_counts_towards_the_limit(): void
    {
        foreach (['2026-11-08', '2026-11-10'] as $date) {
            InstallationRescheduleRequest::query()->create([
                'consumer_id' => $this->consumer->id, 'reschedule_type' => 'connection', 'status' => 'approved',
                'reason' => 'Historical request.', 'original_installation_date' => '2026-11-07', 'new_installation_date' => $date,
            ]);
        }
        $this->assertSame(2, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $pending = $this->requestConnection();
        $this->assign($pending, '2026-11-16');
        $this->assertSame(3, $pending->refresh()->assignment_number);
        $this->assertSame(2, $this->consumer->installationRescheduleRequests()->where('status', 'approved')->count());
        $this->assertSame(3, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
    }

    public function test_rolled_back_assignment_does_not_consume_an_attempt_or_send_email(): void
    {
        $request = $this->requestConnection();
        Mail::shouldReceive('send')->never();
        try {
            DB::transaction(function () use ($request): void {
                app(InstallationRescheduleService::class)->assign($request, $this->admin, [
                    'new_installation_date' => '2026-11-12', 'new_installation_time' => '14:00',
                ]);
                throw new \RuntimeException('Rollback assignment');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback assignment', $exception->getMessage());
        }
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertNull($request->assignment_number);
        $this->assertSame(0, app(InstallationRescheduleService::class)->usage($this->consumer)['used']);
        $this->assertSame('2026-11-10', $this->consumer->refresh()->installation_date->toDateString());
        $this->assertDatabaseCount('consumer_notification_logs', 0);
    }
}
