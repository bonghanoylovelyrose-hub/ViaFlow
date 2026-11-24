<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumerManagementTablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $name, bool $notice = false, string $membership = 'Non-Member'): Consumer
    {
        $sequence = Consumer::query()->count() + 1;
        $consumer = Consumer::query()->create([
            'full_name' => $name,
            'account_number' => 'TABLE-'.$sequence,
            'meter_number' => 'TABLE-METER-'.$sequence,
            'status' => 'Active',
            'membership_type' => $membership,
        ]);
        if ($notice) {
            $bill = Bill::query()->create([
                'consumer_id' => $consumer->id, 'prn' => 'TABLE-BILL-'.$sequence,
                'billing_period' => '2026-10-01', 'due_date' => '2026-10-31', 'amount' => 250, 'status' => 'Unpaid',
            ]);
            DisconnectionNotice::query()->create([
                'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'Active',
                'issued_at' => now(), 'deadline_at' => now()->addDays(3), 'outstanding_amount' => 250, 'months_unpaid' => 3,
            ]);
        }

        return $consumer;
    }

    public function test_notice_table_precedes_active_table_with_disjoint_account_rows_and_required_columns(): void
    {
        $notice = $this->account('Notice Account', true);
        $active = $this->account('Ordinary Active Account');
        $inactive = $this->account('Disconnected Account');
        $inactive->update(['status' => 'Disconnected']);
        $response = $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk();

        $this->assertSame([$notice->id], $response->viewData('disconnectionNotices')->pluck('consumer.id')->all());
        $this->assertSame([$active->id], $response->viewData('consumers')->pluck('id')->all());
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, '<table '));
        $this->assertLessThan(strpos($html, 'id="active-accounts"'), strpos($html, 'id="active-disconnection-notices"'));
        $response->assertSee('Payment Status')->assertSee('Outstanding Balance')->assertSee('Disconnection Notice Date')
            ->assertSee('Grace Period / Payment Deadline')->assertSee('3 full days from notice')->assertDontSee('Disconnected Account');

        DisconnectionNotice::query()->create([
            'consumer_id' => $notice->id, 'status' => 'Settled', 'issued_at' => now()->subMonths(4), 'settled_at' => now()->subMonths(3),
        ]);
        $response = $this->get(route('consumers.index', ['account_category' => 'active']));
        $this->assertSame(1, $response->viewData('disconnectionNotices')->total());
        $this->assertSame([$active->id], $response->viewData('consumers')->pluck('id')->all());
    }

    public function test_search_and_membership_filters_are_independent_in_both_directions(): void
    {
        $noticeMember = $this->account('Notice Member', true, 'Member');
        $noticeOther = $this->account('Notice Other', true);
        $activeMember = $this->account('Active Member', false, 'Member');
        $activeOther = $this->account('Active Other');

        $response = $this->get(route('consumers.index', ['account_category' => 'active', 'search' => 'Active Member', 'membership' => 'member']));
        $this->assertSame([$activeMember->id], $response->viewData('consumers')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$noticeMember->id, $noticeOther->id], $response->viewData('disconnectionNotices')->pluck('consumer.id')->all());

        $response = $this->get(route('consumers.index', ['account_category' => 'active', 'notice_search' => 'Notice Other', 'notice_membership' => 'non_member']));
        $this->assertSame([$noticeOther->id], $response->viewData('disconnectionNotices')->pluck('consumer.id')->all());
        $this->assertEqualsCanonicalizing([$activeMember->id, $activeOther->id], $response->viewData('consumers')->pluck('id')->all());

        $response = $this->get(route('consumers.index', ['account_category' => 'active', 'notice_search' => 'no matches']));
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertSame(2, $response->viewData('consumers')->total());
    }

    public function test_each_paginator_and_filter_form_preserves_the_other_table_state(): void
    {
        for ($index = 1; $index <= 11; $index++) {
            $this->account('Notice '.$index, true);
            $this->account('Active '.$index);
        }
        $response = $this->get(route('consumers.index', [
            'account_category' => 'active', 'notice_search' => 'Notice', 'search' => 'Active', 'notice_page' => 2, 'page' => 2,
        ]))->assertOk();
        $notices = $response->viewData('disconnectionNotices');
        $accounts = $response->viewData('consumers');
        $this->assertSame(11, $notices->total());
        $this->assertSame(11, $accounts->total());
        $this->assertCount(2, $notices->items());
        $this->assertCount(1, $accounts->items());
        $this->assertStringContainsString('page=2', $notices->previousPageUrl());
        $this->assertStringContainsString('notice_page=2', $accounts->previousPageUrl());
        $this->assertSame([], array_intersect($notices->pluck('consumer.id')->all(), $accounts->pluck('id')->all()));

        preg_match('/<form[^>]+id="notice-filters"[^>]*>(.*?)<\/form>/s', $response->getContent(), $noticeForm);
        preg_match('/<form[^>]+id="account-filters"[^>]*>(.*?)<\/form>/s', $response->getContent(), $accountForm);
        $this->assertStringContainsString('name="page" value="2"', $noticeForm[1]);
        $this->assertStringContainsString('name="search" value="Active"', $noticeForm[1]);
        $this->assertStringContainsString('name="notice_page" value="2"', $accountForm[1]);
        $this->assertStringContainsString('name="notice_search" value="Notice"', $accountForm[1]);
        $this->assertStringNotContainsString('name="notice_page"', $noticeForm[1]);
        $this->assertStringNotContainsString('name="page"', $accountForm[1]);
    }

    public function test_both_tables_remain_visible_when_empty(): void
    {
        $response = $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<table '));
        $response->assertSee('No disconnection records found.')->assertSee('No active accounts match the current filters.');
    }

    public function test_delete_action_removes_only_the_selected_consumer(): void
    {
        $selected = $this->account('Selected for deletion');
        $keep = $this->account('Keep this consumer');

        $this->delete(route('consumers.destroy', $selected))
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHas('success', 'Consumer deleted successfully.');

        $this->assertDatabaseMissing('consumers', ['id' => $selected->id]);
        $this->assertDatabaseHas('consumers', ['id' => $keep->id]);
    }
}
