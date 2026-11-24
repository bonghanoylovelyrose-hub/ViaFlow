<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsumerInstallationFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function consumer(string $account, array $attributes = []): Consumer
    {
        return Consumer::create(array_merge([
            'full_name' => $account.' Consumer',
            'email' => strtolower($account).'@example.test',
            'account_number' => $account,
            'meter_number' => 'M-'.$account,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }

    private function accountRows(array $query = []): string
    {
        $response = $this->actingAs($this->admin)->get(route('consumers.index', array_merge(['account_category' => 'active'], $query)))->assertOk();
        $html = $response->getContent();
        $start = strpos($html, 'id="consumer-accounts-table"');
        $end = strpos($html, '</table>', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_all_pending_and_completed_dropdown_filters_active_accounts(): void
    {
        $this->consumer('FILTER-UNSCHEDULED');
        $this->consumer('FILTER-PENDING', ['installation_date' => now()->addDays(2)->toDateString()]);
        $this->consumer('FILTER-COMPLETED', ['installation_date' => now()->subDays(1)->toDateString(), 'installation_completed_at' => now()]);

        $defaultHtml = $this->actingAs($this->admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk()->getContent();
        $selectStart = strpos($defaultHtml, '<select class="form-select" id="installation_status"');
        $selectEnd = strpos($defaultHtml, '</select>', $selectStart);
        $statusSelect = substr($defaultHtml, $selectStart, $selectEnd - $selectStart);
        $this->assertStringContainsString('All Installations', $statusSelect);
        $this->assertStringContainsString('Pending Installation', $statusSelect);
        $this->assertStringContainsString('Completed', $statusSelect);
        $this->assertSame(1, preg_match('/<option value="all" selected/', $statusSelect));
        $this->assertStringNotContainsString('aria-label="Installation status filters"', $defaultHtml);
        $this->assertStringContainsString('id="search"', $defaultHtml);
        $this->assertStringContainsString('id="membership"', $defaultHtml);
        $this->assertLessThan(strpos($defaultHtml, 'id="membership"'), strpos($defaultHtml, 'id="search"'));
        $this->assertLessThan(strpos($defaultHtml, 'id="installation_status"'), strpos($defaultHtml, 'id="membership"'));
        $this->assertStringContainsString('class="mb-3 active-account-filters"', $defaultHtml);
        $all = $this->accountRows();
        $this->assertStringContainsString('FILTER-UNSCHEDULED', $all);
        $this->assertStringContainsString('FILTER-PENDING', $all);
        $this->assertStringContainsString('FILTER-COMPLETED', $all);

        $pending = $this->accountRows(['installation_status' => 'pending']);
        $this->assertStringContainsString('FILTER-PENDING', $pending);
        $this->assertStringNotContainsString('FILTER-UNSCHEDULED', $pending);
        $this->assertStringNotContainsString('FILTER-COMPLETED', $pending);

        $completed = $this->accountRows(['installation_status' => 'completed']);
        $this->assertStringContainsString('FILTER-COMPLETED', $completed);
        $this->assertStringNotContainsString('FILTER-UNSCHEDULED', $completed);
        $this->assertStringNotContainsString('FILTER-PENDING', $completed);
    }

    public function test_installation_filter_combines_with_search_and_membership_dropdown(): void
    {
        $this->consumer('COMBO-MEMBER-PENDING', ['full_name' => 'Avery Searchable', 'membership_type' => 'Member', 'installation_date' => now()->addDays(2)->toDateString()]);
        $this->consumer('COMBO-NONMEMBER-PENDING', ['full_name' => 'Avery Other', 'installation_date' => now()->addDays(2)->toDateString()]);
        $this->consumer('COMBO-MEMBER-COMPLETE', ['full_name' => 'Avery Complete', 'membership_type' => 'Member', 'installation_date' => now()->subDay()->toDateString(), 'installation_completed_at' => now()]);

        $combined = $this->accountRows(['installation_status' => 'pending', 'membership' => 'member', 'search' => 'Searchable']);
        $this->assertStringContainsString('COMBO-MEMBER-PENDING', $combined);
        $this->assertStringNotContainsString('COMBO-NONMEMBER-PENDING', $combined);
        $this->assertStringNotContainsString('COMBO-MEMBER-COMPLETE', $combined);

        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active', 'installation_status' => 'pending', 'membership' => 'member', 'search' => 'Searchable', 'page' => 3,
        ]))->assertOk();
        $response->assertSee('name="search" value="Searchable"', false)
            ->assertSee('<option value="member" selected', false)
            ->assertSee('<option value="pending" selected', false);
    }

    public function test_filter_is_applied_before_pagination_and_status_change_returns_to_same_filter(): void
    {
        $now = now();
        $consumer = null;
        for ($index = 1; $index <= 11; $index++) {
            $consumer = $this->travelTo($now->copy()->subSeconds($index), fn () => $this->consumer('PAGE-PENDING-'.$index, [
                'installation_date' => $now->copy()->addDays(2)->toDateString(),
            ]));
        }

        $pageTwo = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active', 'installation_status' => 'pending', 'page' => 2,
        ]))->assertOk();
        $pageTwo->assertSee('Showing 11 to 11 of 11 consumers')
            ->assertSee('installation_status=pending', false);
        $this->assertStringContainsString('PAGE-PENDING-11', $pageTwo->getContent());

        $completionUrl = route('consumers.installation-complete', array_merge(
            ['consumer' => $consumer],
            ['account_category' => 'active', 'installation_status' => 'pending', 'membership' => 'non_member', 'page' => 2],
        ));
        $this->actingAs($this->admin)->post($completionUrl)
            ->assertRedirect(route('consumers.index', [
                'account_category' => 'active', 'installation_status' => 'pending', 'membership' => 'non_member', 'page' => 1,
            ]));

        $pending = $this->accountRows(['installation_status' => 'pending', 'membership' => 'non_member']);
        $this->assertStringNotContainsString('PAGE-PENDING-11', $pending);
        $completed = $this->accountRows(['installation_status' => 'completed', 'membership' => 'non_member']);
        $this->assertStringContainsString('PAGE-PENDING-11', $completed);
        $all = $this->accountRows(['installation_status' => 'all', 'membership' => 'non_member', 'search' => 'PAGE-PENDING-11']);
        $this->assertStringContainsString('PAGE-PENDING-11', $all);
    }

    public function test_active_account_clear_buttons_reset_their_own_filter(): void
    {
        $date = today()->subDay()->toDateString();
        $completed = ['installation_date' => $date, 'installation_completed_at' => now()];
        $this->consumer('CLEAR-MEMBER', $completed + ['full_name' => 'Searchable Member', 'membership_type' => 'Member']);
        $this->consumer('CLEAR-MEMBER-PENDING', ['full_name' => 'Searchable Pending', 'membership_type' => 'Member', 'installation_date' => $date]);
        $this->consumer('CLEAR-NONMEMBER', $completed + ['full_name' => 'Searchable Nonmember']);
        $this->consumer('CLEAR-OUTSIDE-SEARCH', $completed);

        $url = route('consumers.index', [
            'account_category' => 'active',
            'search' => 'Searchable',
            'membership' => 'member',
            'installation_status' => 'completed',
            'notice_search' => 'Notice search',
        ]);
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $form = '//form[@id="account-filters"]';
        $this->assertSame(2, $xpath->query($form.'//button[normalize-space()="Clear"]')->length);
        $this->assertSame(0, $xpath->query($form.'//a[normalize-space()="Clear filters"]')->length);
        $this->assertSame(1, $xpath->query($form.'//select[@name="installation_status"]/following-sibling::button[@name="installation_status" and @value="all" and normalize-space()="Clear" and not(i)]')->length);
        $this->assertSame(1, $xpath->query($form.'//select[@name="membership"]/following-sibling::button[@name="membership" and @value="" and normalize-space()="Clear"]')->length);

        foreach ([
            'installation_status=all' => ['member', 'all', ['CLEAR-MEMBER', 'CLEAR-MEMBER-PENDING']],
            'membership=' => ['', 'completed', ['CLEAR-MEMBER', 'CLEAR-NONMEMBER']],
        ] as $submitter => [$membership, $installation, $accounts]) {
            $cleared = $this->get($url.'&'.$submitter)->assertOk();
            $filters = $cleared->viewData('filters');
            $this->assertSame('Searchable', $filters['search']);
            $this->assertSame('Notice search', $filters['notice_search']);
            $this->assertSame($membership, $filters['membership']);
            $this->assertSame($installation, $filters['installation_status']);
            $this->assertSame($accounts, $cleared->viewData('consumers')->pluck('account_number')->sort()->values()->all());
        }
    }
}








