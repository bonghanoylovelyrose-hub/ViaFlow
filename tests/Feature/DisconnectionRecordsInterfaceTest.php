<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\InstallationRescheduleRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\DisconnectionNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DisconnectionRecordsInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 12:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_notice_and_active_tables_have_automatic_filters_and_no_old_membership_label(): void
    {
        $this->account('Window', '2026-09-10 12:00:01');
        $response = $this->records()->assertOk()->assertSee('id="disconnection-records"', false)
            ->assertSee('Active Notices')->assertSee('Within Grace Period')->assertSee('Resolved')->assertSee('Unpaid Accounts')
            ->assertSee('Ready for Disconnection')->assertDontSee('Disconnected Accounts')
            ->assertSee('data-auto-filter', false)->assertSee('All Membership Records');

        $this->assertSame(2, substr_count($response->getContent(), '<table '));
        $this->assertStringNotContainsString('class="table-danger"', $response->getContent());
        $this->assertStringNotContainsString('text-danger', $this->table($response->getContent()));
    }

    public function test_filters_use_real_balances_and_preserve_history_without_listing_disconnected_accounts(): void
    {
        [$paid, $paidBill] = $this->account('Paid', '2026-09-09 12:00:00');
        $this->pay($paidBill, 'Verified');
        [$window] = $this->account('Window', '2026-09-11 12:00:00');
        [$ready] = $this->account('Ready', '2026-09-10 12:00:00');
        [$disconnected] = $this->account('Disconnected', '2026-09-09 12:00:00');
        app(DisconnectionNoticeService::class)->confirmDisconnection($disconnected, $this->admin);

        $expected = [
            '' => [$window->id, $ready->id],
            'paid' => [$paid->id],
            'unpaid' => [$window->id, $ready->id],
            'grace' => [$window->id],
            'ready' => [$ready->id],
        ];

        foreach ($expected as $status => $consumerIds) {
            $rows = $this->records(['notice_status' => $status])->assertOk()->viewData('disconnectionNotices');
            $this->assertEqualsCanonicalizing($consumerIds, $rows->pluck('consumer.id')->all(), $status);
        }

        $paidPage = $this->records(['notice_status' => 'paid'])->assertOk();
        $paidPage->assertSee('data-record-status="Resolved"', false)->assertSee('Water Billing Overdue');
        $this->assertSame(1, $paidPage->viewData('disconnectionNotices')->total());
        $this->assertContains($paid->id, $paidPage->viewData('consumers')->pluck('id')->all());
        $this->assertDatabaseHas('disconnection_notices', ['consumer_id' => $paid->id, 'status' => 'Settled']);
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertSee($disconnected->full_name);
        $this->assertDatabaseCount('disconnection_notices', 4);
    }

    public function test_exact_deadline_turns_red_but_only_confirmation_changes_the_account_to_disconnected(): void
    {
        [$consumer] = $this->account('Boundary', '2026-09-10 12:00:01');
        $this->records(['notice_status' => 'grace'])->assertDontSee('class="table-danger"', false)
            ->assertSee('data-record-status="Within Grace Period"', false)
            ->assertDontSee('id="consumerDisconnectionModal"', false);
        $this->assertSame(0, $this->records(['notice_status' => 'ready'])->viewData('disconnectionNotices')->total());

        Carbon::setTestNow('2026-09-10 12:00:01');
        $confirmUrl = route('consumers.confirm-disconnection', $consumer);
        $this->records(['notice_status' => 'ready'])->assertSee('class="table-danger"', false)
            ->assertSee('data-record-status="Ready for Disconnection"', false)
            ->assertSee('data-bs-target="#consumerDisconnectionModal"', false)
            ->assertSee('data-disconnection-url="'.$confirmUrl.'"', false)
            ->assertSee('data-disconnection-name="Boundary"', false)
            ->assertSee('data-disconnection-account="'.$consumer->account_number.'"', false)
            ->assertSee('data-disconnection-meter="'.$consumer->meter_number.'"', false)
            ->assertSee('data-disconnection-balance="300.00"', false)
            ->assertSee('data-disconnection-status="Ready for Disconnection"', false)
            ->assertSee('Confirm Account Disconnection?')
            ->assertSee('name="disconnection_reason"', false)
            ->assertDontSee('Confirm that the physical disconnection has already been carried out for');
        $this->assertSame(0, $this->records(['notice_status' => 'grace'])->viewData('disconnectionNotices')->total());
        $this->assertSame('Active', $consumer->refresh()->status);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->post($confirmUrl)->assertForbidden();
        $this->actingAs($this->admin)->post($confirmUrl, ['disconnection_reason' => 'Service shut off at the meter.'])
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']))
            ->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->records()->assertDontSee('Boundary');
        $inactive = $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()->assertSee('Boundary');
        $inactiveConsumers = $inactive->viewData('consumers');
        $this->assertSame([$consumer->id], $inactiveConsumers->getCollection()->pluck('id')->all());
        $this->assertSame('DR-'.$consumer->id, $consumer->account_number);
        $this->assertSame('DR-METER-'.$consumer->id, $consumer->meter_number);
        $notice = DisconnectionNotice::query()->sole();
        $this->assertSame('Disconnected', $notice->status);
        $this->assertSame($this->admin->id, $notice->confirmed_by);
        $this->assertNotNull($notice->disconnected_at);
        $this->assertSame('300.00', $notice->outstanding_at_disconnection);
        $this->assertSame('Service shut off at the meter.', $notice->disconnection_reason);
        $this->post($confirmUrl)->assertSessionHasErrors();
        $this->assertDatabaseCount('consumers', 1);
        $this->assertDatabaseCount('disconnection_notices', 1);
    }

    public function test_notice_rows_sort_ready_first_then_newest_notice_before_pagination(): void
    {
        [$graceOld] = $this->account('Grace old', '2026-09-11 09:00:00');
        [$readyOld] = $this->account('Ready old', '2026-09-09 12:00:00');
        [$graceNew] = $this->account('Grace new', '2026-09-12 12:00:00');
        [$readyNew] = $this->account('Ready new', '2026-09-10 11:30:00');
        [$graceMiddle] = $this->account('Grace middle', '2026-09-11 10:00:00');
        [$readyMiddle] = $this->account('Ready middle', '2026-09-10 10:00:00');

        $firstPage = $this->records()->assertOk()->viewData('disconnectionNotices');
        $this->assertSame([$readyNew->id, $readyMiddle->id], $firstPage->pluck('consumer.id')->all());
        $this->assertSame('Ready for Disconnection', $firstPage->items()[0]['record_status']);
        $this->assertSame(3, $firstPage->lastPage());

        $secondPage = $this->records(['notice_page' => 2])->viewData('disconnectionNotices');
        $this->assertSame([$readyOld->id, $graceNew->id], $secondPage->pluck('consumer.id')->all());
        $this->assertSame('Within Grace Period', $secondPage->items()[1]['record_status']);
        $thirdPage = $this->records(['notice_page' => 3])->viewData('disconnectionNotices');
        $this->assertSame([$graceMiddle->id, $graceOld->id], $thirdPage->pluck('consumer.id')->all());
    }

    public function test_pending_payment_keeps_ready_row_red_and_verified_payment_preserves_history_without_red(): void
    {
        [$consumer, $bill] = $this->account('Payment', '2026-09-10 12:00:00');
        $payment = $this->pay($bill, 'Pending');
        $this->records(['notice_status' => 'ready'])->assertSee('class="table-danger"', false);
        $payment->update(['status' => 'Verified']);

        $response = $this->records(['notice_status' => 'paid'])->assertDontSee('class="table-danger"', false);
        $this->assertSame(1, $response->viewData('disconnectionNotices')->total());
        $response->assertSee('data-record-status="Resolved"', false);
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', ['consumer_id' => $consumer->id, 'status' => 'Settled']);
    }

    public function test_partial_payment_remains_in_notice_table_when_outstanding_balance_remains(): void
    {
        [$consumer, $bill] = $this->account('Partial Payment', '2026-09-11 12:00:00');
        $payment = $this->pay($bill, 'Verified');
        $payment->update(['amount' => 100]);

        $response = $this->records()->assertOk()->assertSee('Partial Payment');
        $record = $response->viewData('disconnectionNotices')->first();

        $this->assertSame(1, $response->viewData('disconnectionNotices')->total());
        $this->assertSame(200.0, $record['total_outstanding']);
        $this->assertSame(DisconnectionNotice::STATUS_ACTIVE, $record['notice_status']);
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_consumer_connection_page_displays_their_saved_payment_deadline(): void
    {
        [$consumer] = $this->account('Window', '2026-09-11 12:00:00');
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer->update(['user_id' => $user->id]);
        $this->account('Other Account', '2026-09-10 11:00:00');

        $this->actingAs($user)->get(route('connection.status'))->assertOk()
            ->assertSee('Disconnection Notice')->assertSee('300.00')
            ->assertSee('September 11, 2026 12:00 PM')
            ->assertDontSee('September 10, 2026 11:00 AM');

        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_filters_are_preserved_across_pages_without_duplicate_account_rows(): void
    {
        for ($index = 1; $index <= 3; $index++) {
            $this->account('Searchable '.$index, '2026-09-10 12:00:00');
        }
        $this->account('Excluded', '2026-09-11 12:00:00');
        $filters = ['notice_search' => 'Searchable', 'notice_status' => 'ready', 'notice_membership' => 'non_member'];
        $response = $this->records($filters)->assertOk()->assertSee('Showing 1–2 of 3 accounts')
            ->assertSee('rel="next">Next</a>', false)->assertDontSee('rel="prev">Previous</a>', false);
        $records = $response->viewData('disconnectionNotices');
        $this->assertSame(3, $records->total());
        $this->assertCount(2, $records->items());
        $second = $this->records($filters + ['notice_page' => 2])->assertOk()->assertSee('Showing 3–3 of 3 accounts')
            ->assertSee('rel="prev">Previous</a>', false)->assertDontSee('rel="next">Next</a>', false);
        $last = $second->viewData('disconnectionNotices');
        $this->assertCount(1, $last->items());
        $this->assertNotContains($last->first()['consumer']->id, $records->pluck('consumer.id')->all());
        foreach ([$records->nextPageUrl() => 2, $last->previousPageUrl() => 1] as $url => $page) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertEquals($filters + ['account_category' => 'active', 'notice_page' => $page], $query);
        }

        $narrower = $this->records(['notice_search' => 'Searchable 3', 'notice_page' => 2])
            ->assertOk()->assertSee('Showing 1–1 of 1 account')->viewData('disconnectionNotices');
        $this->assertSame(1, $narrower->currentPage());
    }

    public function test_notice_row_keeps_only_the_badge_and_view_details_contains_the_history(): void
    {
        [$consumer] = $this->account('History', '2026-09-09 12:00:00');
        $previous = DisconnectionNotice::query()->sole();
        $previous->update(['status' => 'Settled', 'settled_at' => now()->subDay()]);
        DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id,
            'status' => 'Active',
            'issued_at' => now(),
            'deadline_at' => now()->addDays(3),
            'outstanding_amount' => 300,
            'months_unpaid' => 3,
        ]);
        $consumer->reconnections()->create([
            'confirmed_by' => $this->admin->id,
            'reconnection_date' => today()->subDay(),
            'installation_scheduled_at' => now()->subDay(),
            'activated_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'reconnection_fee' => 0,
            'verified_payment_ids' => [],
        ]);
        $consumer->installationRescheduleRequests()->create([
            'reschedule_type' => InstallationRescheduleRequest::TYPE_CONNECTION,
            'status' => InstallationRescheduleRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subDays(2),
            'reason' => 'Requested a different installation schedule',
            'original_installation_date' => today()->subDays(2),
        ]);

        $response = $this->records()->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $modalId = 'disconnectionNoticeDetails-'.$consumer->id;
        $row = $xpath->query('//table[@id="disconnection-records"]/tbody/tr')->item(0);
        $this->assertSame('Within Grace Period', trim($xpath->query('./td[10]', $row)->item(0)->textContent));
        $this->assertSame(1, $xpath->query('.//button[@data-bs-target="#'.$modalId.'" and normalize-space(.)="View Details"]', $row)->length);
        $this->assertSame(1, $xpath->query('.//button[@data-bs-target="#consumerEditModal"]', $row)->length);
        $modal = $xpath->query('//div[@id="'.$modalId.'"]')->item(0);
        $this->assertNotNull($modal);
        foreach (['Within Grace Period', 'Active', 'Connected', 'Water Billing Overdue', 'P 300.00', 'Notice date', 'Payment deadline',
            'Notice history (2)', 'Settled', 'Reconnection history (1)', 'Originally scheduled:', 'Completed:', '1 of 3 used', '2 reschedule attempts remaining'] as $details) {
            $this->assertStringContainsString($details, $modal->textContent);
        }

        $this->assertSame(1, $response->viewData('disconnectionNotices')->total());
        $this->assertSame('Within Grace Period', $response->viewData('disconnectionNotices')->first()['record_status']);
        $this->assertSame('Settled', $previous->refresh()->status);
        $this->assertDatabaseCount('disconnection_notices', 2);
    }

    private function records(array $filters = []): TestResponse
    {
        return $this->get(route('consumers.index', array_merge(['account_category' => 'active'], $filters)));
    }

    private function account(string $name, string $deadline): array
    {
        $consumer = Consumer::query()->create([
            'full_name' => $name,
            'account_number' => 'DR-'.(Consumer::query()->count() + 1),
            'meter_number' => 'DR-METER-'.(Consumer::query()->count() + 1),
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'prn' => 'DR-BILL-'.$consumer->id,
            'amount' => 300,
            'status' => 'Unpaid',
        ]);
        DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id,
            'bill_id' => $bill->id,
            'status' => 'Active',
            'issued_at' => Carbon::parse($deadline)->subDays(3),
            'deadline_at' => $deadline,
            'outstanding_amount' => 300,
            'months_unpaid' => 3,
        ]);

        return [$consumer, $bill];
    }

    private function pay(Bill $bill, string $status): Payment
    {
        return Payment::query()->create([
            'consumer_id' => $bill->consumer_id,
            'bill_id' => $bill->id,
            'reference_number' => 'DR-PAY-'.$bill->id,
            'amount' => 300,
            'payment_date' => today(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => $status,
        ]);
    }

    private function table(string $html): string
    {
        return substr($html, strpos($html, '<table '), strpos($html, '</table>') - strpos($html, '<table '));
    }
}
