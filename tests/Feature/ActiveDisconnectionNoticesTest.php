<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActiveDisconnectionNoticesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_active_notice_section_is_only_rendered_inside_active_accounts(): void
    {
        $flagged = $this->createConsumer([
            'full_name' => 'Flagged Account',
            'account_number' => 'ADN-001',
            'meter_number' => 'ADN-MTR-001',
        ]);
        $this->createNoticeBill($flagged, 'ADN-BILL-001');

        $active = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
        ]));

        $active->assertOk()
            ->assertSee('id="active-disconnection-notices"', false)
            ->assertSee('Disconnection Notice')
            ->assertSee('Flagged Account')
            ->assertSee('ADN-001')
            ->assertSee('ADN-MTR-001')
            ->assertSee('Account Number')
            ->assertSee('Consumer Name')
            ->assertSee('Meter Number')
            ->assertSee('Membership')
            ->assertSee('Outstanding Balance')
            ->assertSee('Disconnection Notice Date')
            ->assertSee('Status')
            ->assertSee('Actions');

        foreach (['', 'pending', 'rejected', 'inactive'] as $category) {
            $response = $this->actingAs($this->admin)->get(route('consumers.index', array_filter([
                'account_category' => $category,
            ])));

            $response->assertOk()
                ->assertDontSee('id="active-disconnection-notices"', false)
                ->assertDontSee('Disconnection Notice');
        }

        $this->assertTrue($this->actingAs($this->admin)
            ->get(route('consumers.index', ['account_category' => 'pending']))
            ->viewData('disconnectionNotices')
            ->isEmpty());
    }

    public function test_active_notice_table_supports_search_and_membership_filters(): void
    {
        $member = $this->createConsumer([
            'full_name' => 'Notice Member',
            'account_number' => 'ADN-MEMBER-1',
            'meter_number' => 'ADN-MTR-MEMBER-1',
            'membership_type' => 'Member',
        ]);
        $this->createNoticeBill($member, 'ADN-BILL-MEMBER-1');

        $nonMember = $this->createConsumer([
            'full_name' => 'Notice Non Member',
            'account_number' => 'ADN-NONMEMBER-1',
            'meter_number' => 'ADN-MTR-NONMEMBER-1',
            'membership_type' => 'Non-Member',
        ]);
        $this->createNoticeBill($nonMember, 'ADN-BILL-NONMEMBER-1');

        $byName = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_search' => 'Notice Member',
        ]));
        $byName->assertOk()->assertSee('Notice Member')->assertDontSee('Notice Non Member');

        $sectionSearch = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_search' => 'Notice Member',
        ]));
        $sectionSearch->assertOk()
            ->assertSee('Notice Member')
            ->assertDontSee('Notice Non Member');
        $this->assertSame(1, $sectionSearch->viewData('disconnectionNotices')->total());
        $this->assertSame(0, $sectionSearch->viewData('consumers')->total());

        $byAccount = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_search' => 'ADN-NONMEMBER-1',
        ]));
        $byAccount->assertOk()->assertSee('Notice Non Member')->assertDontSee('Notice Member</div>');

        $byMeter = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_search' => 'ADN-MTR-MEMBER-1',
        ]));
        $byMeter->assertOk()->assertSee('Notice Member')->assertDontSee('Notice Non Member</div>');

        $membersOnly = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_membership' => 'member',
        ]));
        $membersOnly->assertOk()
            ->assertSee('Notice Member')
            ->assertDontSee('Notice Non Member</div>')
            ->assertSee('Clear');

        $nonMembersOnly = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_membership' => 'non_member',
        ]));
        $nonMembersOnly->assertOk()
            ->assertSee('Notice Non Member')
            ->assertDontSee('Notice Member</div>');

        $sectionMembersOnly = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_membership' => 'member',
        ]));
        $this->assertSame(1, $sectionMembersOnly->viewData('disconnectionNotices')->total());
        $this->assertSame(0, $sectionMembersOnly->viewData('consumers')->total());
    }

    public function test_notice_details_shows_only_notice_fields_without_account_status(): void
    {
        $consumer = $this->createConsumer();
        $this->createNoticeBill($consumer, 'ADN-DETAILS');

        $page = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
        ]))->assertOk();

        $document = new \DOMDocument;
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $modal = '//*[@id="disconnectionNoticeDetails-'.$consumer->id.'"]';
        $labels = $xpath->query($modal.'//dl//dt');
        $this->assertSame([
            'Disconnection status', 'Disconnection reason', 'Outstanding balance', 'Notice date', 'Payment deadline',
        ], array_map(fn ($label) => trim($label->textContent), iterator_to_array($labels)));
        $this->assertSame(0, $xpath->query($modal.'//select')->length);
        $this->assertSame('Active', $consumer->fresh()->status);
    }

    public function test_notice_edit_saves_profile_fields_without_updating_account_status(): void
    {
        $consumer = $this->createConsumer();
        $this->createNoticeBill($consumer, 'ADN-EDIT');
        $page = route('consumers.index', ['account_category' => 'active', 'notice_search' => $consumer->meter_number]);
        $edit = route('consumers.update-from-disconnection-notice', $consumer);

        $this->actingAs($this->admin)->get($page)->assertOk()
            ->assertSee('data-notice-edit-url="'.$edit.'"', false);

        $name = 'Updated notice account';
        $fields = ['full_name' => $name, 'membership_type' => $consumer->membership_type];
        $this->from($page)->patch($edit, $fields)->assertRedirect($page)->assertSessionHasNoErrors();
        $this->assertSame($name, $consumer->refresh()->full_name);
        $this->assertSame('Active', $consumer->status);

        $this->from($page)->patch($edit, $fields + ['status' => 'Disconnected', 'contact_number' => '09171234567'])
            ->assertRedirect($page)->assertSessionHasNoErrors();
        $this->assertSame('09171234567', $consumer->refresh()->contact_number);
        $this->assertSame('Active', $consumer->status);
        $this->assertNull($consumer->disconnected_at);
        $this->assertTrue($consumer->disconnectionNotices()->active()->exists());

        $this->from($page)->patch($edit, ['membership_type' => $consumer->membership_type])
            ->assertRedirect($page)->assertSessionHasErrors('full_name')->assertSessionHas('consumer_notice_edit', true);
    }

    public function test_active_notice_table_is_paginated_and_keeps_the_active_category(): void
    {
        for ($index = 1; $index <= 3; $index++) {
            $consumer = $this->createConsumer([
                'full_name' => sprintf('Notice Account %02d', $index),
                'account_number' => sprintf('ADN-PAGE-%02d', $index),
                'meter_number' => sprintf('ADN-MTR-PAGE-%02d', $index),
            ]);
            $this->createNoticeBill($consumer, sprintf('ADN-BILL-PAGE-%02d', $index));
        }

        $firstPage = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
        ]));

        $firstPage->assertOk()->assertSee('3 accounts')->assertSee('Showing 1–2 of 3 accounts')
            ->assertSee('aria-label="Disconnection Notice pages"', false)->assertSee('Previous')->assertSee('Next');

        $notices = $firstPage->viewData('disconnectionNotices');
        $this->assertSame(3, $notices->total());
        $this->assertCount(2, $notices->items());
        $this->assertNotNull($notices->nextPageUrl());
        $this->assertStringContainsString('account_category=active', $notices->nextPageUrl());
        $this->assertStringContainsString('notice_page=2', $notices->nextPageUrl());

        $secondPage = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'notice_page' => 2,
        ]));

        $secondPage->assertOk()
            ->assertSee('Notice Account 01')
            ->assertSee('Showing 3–3 of 3 accounts');
        $this->assertCount(1, $secondPage->viewData('disconnectionNotices')->items());
    }

    public function test_active_notice_section_shows_an_empty_state_without_hiding_active_accounts(): void
    {
        $healthy = $this->createConsumer([
            'full_name' => 'Healthy Active Account',
            'account_number' => 'ADN-HEALTHY-1',
            'meter_number' => 'ADN-MTR-HEALTHY-1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
        ]));

        $response->assertOk()
            ->assertSee('Disconnection Notice')
            ->assertSee('No disconnection records found.')
            ->assertSee('Healthy Active Account');

        $this->assertSame(
            ['Healthy Active Account'],
            $response->viewData('consumers')->pluck('full_name')->all(),
        );
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        static $sequence = 0;
        $sequence++;

        return Consumer::query()->create(array_merge([
            'full_name' => 'Active Notice Consumer '.$sequence,
            'account_number' => 'ADN-'.$sequence,
            'meter_number' => 'ADN-MTR-'.$sequence,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }

    private function createNoticeBill(Consumer $consumer, string $prn): Bill
    {
        $bills = collect();

        foreach (['2026-05-01', '2026-06-01', '2026-07-01'] as $period) {
            $bills->push(Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => $period,
                'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(),
                'prn' => $prn.'-'.Carbon::parse($period)->format('m'),
                'amount' => 500.00,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]));
        }

        // Three consecutive overdue months raise the notice while the account
        // remains Active until an administrator confirms disconnection.
        $consumer->syncAccountStanding();

        return $bills->last();
    }
}
