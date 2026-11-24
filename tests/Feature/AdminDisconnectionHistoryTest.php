<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDisconnectionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $identifier, array $attributes = []): Consumer
    {
        return Consumer::query()->create($attributes + [
            'full_name' => "Consumer $identifier",
            'account_number' => "ACCOUNT-$identifier",
            'meter_number' => "METER-$identifier",
            'status' => 'Active',
        ]);
    }

    private function historyUrl(array $filters = []): string
    {
        return route('consumers.index', ['account_category' => 'disconnection_history'] + $filters);
    }

    public function test_physical_disconnections_and_reconnections_persist_as_separate_cycles(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $consumer = $this->account('Repeated');
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()->assertSee($this->historyUrl(), false);

        foreach (['2026-09-09', '2026-10-09'] as $index => $day) {
            $this->travelTo(Carbon::parse($day.' 10:00:11'));
            $bill = Bill::query()->create([
                'consumer_id' => $consumer->id, 'prn' => 'HISTORY-'.$index, 'billing_period' => now()->subMonth()->startOfMonth(),
                'due_date' => now()->subDays(10), 'amount' => 275, 'status' => 'Unpaid',
            ]);
            $notice = $consumer->disconnectionNotices()->create([
                'bill_id' => $bill->id, 'issued_at' => now()->subDays(4), 'deadline_at' => now()->subDay(),
                'status' => DisconnectionNotice::STATUS_ACTIVE,
            ]);
            $this->post(route('consumers.confirm-disconnection', $consumer), ['disconnection_reason' => 'Unpaid cycle '.($index + 1)])
                ->assertSessionHasNoErrors()->assertRedirect(route('consumers.index', ['account_category' => 'inactive']));
            $this->assertTrue($notice->refresh()->disconnected_at->equalTo($consumer->refresh()->disconnected_at));
            $this->get($this->historyUrl(['history_status' => 'disconnected']))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->total() === 1)->assertSee('Unpaid cycle '.($index + 1));

            $this->post(route('payments.store'), [
                'bill_id' => $bill->id, 'amount' => 275, 'payment_date' => today()->toDateString(),
                'payment_method' => 'Cash', 'payment_channel' => 'Counter',
            ])->assertSessionHasNoErrors();
            $this->travel(15)->minutes();
            $this->post(route('consumers.reconnect', $consumer), [
                'confirm_full_payment' => 1, 'installation_scheduled_at' => $day.'T19:15:27',
            ])->assertSessionHasNoErrors()->assertRedirect(route('consumers.index', ['account_category' => 'active']));
            $record = $consumer->reconnections()->latest('id')->firstOrFail();
            $this->assertSame($notice->id, $record->disconnection_notice_id);
            $this->travelTo(Carbon::parse($day.' 11:15:27'));
            $this->post(route('consumers.reconnection-complete', [$consumer, $record]))->assertSessionHasNoErrors();
        }

        $this->get($this->historyUrl())->assertOk()
            ->assertSeeInOrder(['Oct 09, 2026 10:00:11 AM', 'Oct 09, 2026 7:15:27 PM', 'Sep 09, 2026 10:00:11 AM', 'Sep 09, 2026 7:15:27 PM']);
        $this->get($this->historyUrl(['history_status' => 'reconnected', 'search' => 'METER-Repeated']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 2);
        $this->get($this->historyUrl(['history_status' => 'disconnected']))->assertOk()->assertSee('No disconnection records');
        $this->assertDatabaseCount('disconnection_notices', 2);
        $this->assertDatabaseCount('consumer_reconnections', 2);
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()->assertDontSee($consumer->full_name);
        $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk()->assertSee($consumer->full_name);
    }

    public function test_legacy_records_show_known_times_only_and_notices_without_cutoffs_are_excluded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $unknown = $this->account('Unknown', ['status' => 'Inactive']);
        $scheduled = $this->account('Scheduled');
        $scheduled->reconnections()->create([
            'confirmed_by' => $admin->id, 'reconnection_date' => '2026-09-03', 'installation_scheduled_at' => '2026-09-03 12:00:00',
            'activated_at' => '2026-09-02 09:00:00', 'reconnection_fee' => 0, 'verified_payment_ids' => [],
        ]);
        $settled = $this->account('Notice Only');
        $settled->disconnectionNotices()->create(['status' => DisconnectionNotice::STATUS_SETTLED, 'issued_at' => '2026-09-03 08:00:00']);

        $this->get($this->historyUrl())->assertOk()->assertSee('Not Recorded')
            ->assertSee($unknown->full_name)->assertSee($scheduled->full_name)->assertDontSee($settled->full_name)
            ->assertDontSee('Sep 03, 2026 12:00:00 PM');
        $this->get($this->historyUrl(['history_status' => 'reconnected', 'search' => 'ACCOUNT-Scheduled']))->assertOk()
            ->assertSee($scheduled->full_name)->assertDontSee($unknown->full_name);
        $this->get($this->historyUrl(['history_status' => 'disconnected', 'search' => 'Consumer Unknown']))->assertOk()
            ->assertSee($unknown->full_name)->assertDontSee($scheduled->full_name);
    }

    public function test_inactive_accounts_and_history_each_clear_their_filters_without_switching_tabs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $consumer = $this->account('Disconnected', ['status' => 'Disconnected', 'membership_type' => 'Member']);

        foreach ([
            ['inactive', 'account-filters', 'membership', 'non_member', '', 'consumers'],
            ['disconnection_history', 'history-filters', 'history_status', 'reconnected', 'all', 'records'],
        ] as [$category, $form, $filter, $value, $default, $dataset]) {
            $response = $this->get(route('consumers.index', [
                'account_category' => $category, 'search' => $consumer->account_number, $filter => $value,
            ]))->assertOk();
            $this->assertSame(0, $response->viewData($dataset)->total());

            $document = new \DOMDocument();
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            if ($category === 'disconnection_history') {
                $this->assertSame(1, $xpath->query('//form[@id="history-filters"][@data-auto-filter]')->length);
                $this->assertSame(0, $xpath->query('//form[@id="history-filters"]//button[@type="submit"]')->length);
            }
            $clear = $xpath->query('//*[@id="'.$form.'"]//*[self::a or self::button][starts-with(normalize-space(.), "Clear")]');
            $this->assertSame(1, $clear->length, $category);
            $this->assertSame('Clear', trim($clear->item(0)->textContent), $category);
            $this->assertSame(
                $clear->item(0)->getAttribute('href'),
                $xpath->evaluate('string(//select[@name="'.$filter.'"]/following-sibling::a/@href)'),
            );

            $reset = $this->get($clear->item(0)->getAttribute('href'))->assertOk();
            $this->assertSame($category, $reset->viewData('filters')['account_category']);
            $this->assertSame('', $reset->viewData('filters')['search']);
            $this->assertSame($default, $reset->viewData('filters')[$filter]);
            $this->assertSame(1, $reset->viewData($dataset)->total());
        }
    }

    public function test_history_is_admin_only(): void
    {
        $this->get($this->historyUrl())->assertRedirect(route('login'));
        foreach (['consumer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($this->historyUrl())->assertForbidden();
        }
    }
}
