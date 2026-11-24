<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumerDirectoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        Consumer::query()->create([
            'full_name' => 'Pending Applicant',
            'account_number' => 'CDF-PENDING',
            'meter_number' => 'CDF-M-PENDING',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
        ]);

        Consumer::query()->create([
            'full_name' => 'Rejected Applicant',
            'account_number' => 'CDF-REJECTED',
            'meter_number' => 'CDF-M-REJECTED',
            'status' => 'Rejected',
            'membership_type' => 'Non-Member',
        ]);

        Consumer::query()->create([
            'full_name' => 'Active Member',
            'account_number' => 'CDF-ACTIVE',
            'meter_number' => 'CDF-M-ACTIVE',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        Consumer::query()->create([
            'full_name' => 'Review Account',
            'account_number' => 'CDF-REVIEW',
            'meter_number' => 'CDF-M-REVIEW',
            'status' => 'For Review',
            'membership_type' => 'Non-Member',
        ]);

        Consumer::query()->create([
            'full_name' => 'Disconnected Member',
            'account_number' => 'CDF-DISCONNECTED',
            'meter_number' => 'CDF-M-DISCONNECTED',
            'status' => 'Disconnected',
            'membership_type' => 'Member',
        ]);

        Consumer::query()->create([
            'full_name' => 'Reconnection Account',
            'account_number' => 'CDF-RECONNECTION',
            'meter_number' => 'CDF-M-RECONNECTION',
            'status' => 'For Reconnection',
            'membership_type' => 'Non-Member',
        ]);
    }

    public function test_consumer_directory_filters_by_sidebar_account_category(): void
    {
        $this->assertEqualsCanonicalizing([
            'Pending Applicant',
            'Rejected Applicant',
            'Active Member',
            'Review Account',
            'Disconnected Member',
            'Reconnection Account',
        ], $this->consumerNames());

        $this->assertSame(['Pending Applicant'], $this->consumerNames(['account_category' => 'pending']));
        $this->assertSame(['Rejected Applicant'], $this->consumerNames(['account_category' => 'rejected']));
        $this->assertEqualsCanonicalizing(
            ['Active Member'],
            $this->consumerNames(['account_category' => 'active']),
        );
        $this->assertEqualsCanonicalizing(
            ['Disconnected Member', 'Reconnection Account'],
            $this->consumerNames(['account_category' => 'inactive']),
        );
    }

    public function test_all_consumers_has_one_clear_action_that_resets_search_membership_and_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'search' => 'Active', 'membership' => 'member', 'membership_verification' => 'verified', 'page' => 2,
        ]))->assertOk();

        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $clear = $xpath->query('//*[@id="account-filters"]//*[self::a or self::button][starts-with(normalize-space(.), "Clear")]');
        $this->assertSame(1, $clear->length);
        $this->assertSame('Clear', trim($clear->item(0)->textContent));

        $reset = $this->get($clear->item(0)->getAttribute('href'))->assertOk();
        $this->assertSame('', $reset->viewData('filters')['search']);
        $this->assertSame('', $reset->viewData('filters')['membership']);
        $this->assertSame('', $reset->viewData('filters')['membership_verification']);
        $this->assertSame(1, $reset->viewData('consumers')->currentPage());
        $this->assertSame(6, $reset->viewData('consumers')->total());
    }

    public function test_pending_tabs_have_one_clear_action_that_resets_filters_without_changing_tabs(): void
    {
        $this->actingAs($this->admin);
        foreach (['all', 'applications', 'reschedules'] as $tab) {
            $response = $this->get(route('consumers.index', [
                'account_category' => 'pending', 'pending_type' => $tab, 'search' => 'Applicant', 'membership' => 'member', 'page' => 2,
            ]))->assertOk();

            $document = new \DOMDocument();
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $actions = $xpath->query('//*[@id="account-filters"]//*[self::a or self::button][starts-with(normalize-space(.), "Clear")]');
            $this->assertSame(1, $actions->length, $tab);
            $this->assertSame('Clear', trim($actions->item(0)->textContent), $tab);
            $this->assertSame(1, $xpath->query('//*[@id="membership"]/following-sibling::a[@data-pending-clear]')->length);

            $reset = $this->get($actions->item(0)->getAttribute('href'))->assertOk();
            $this->assertSame('pending', $reset->viewData('filters')['account_category']);
            $this->assertSame($tab, $reset->viewData('filters')['pending_type']);
            $this->assertSame('', $reset->viewData('filters')['search']);
            $this->assertSame('', $reset->viewData('filters')['membership']);
            $this->assertSame(1, $reset->viewData('consumers')->currentPage());
        }
    }

    public function test_rejected_accounts_has_one_clear_action_that_resets_filters_and_keeps_the_category(): void
    {
        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'rejected', 'search' => 'Applicant', 'membership' => 'member',
        ]))->assertOk();
        $this->assertSame(0, $response->viewData('consumers')->total());

        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $clear = $xpath->query('//*[@id="account-filters"]//*[self::a or self::button][starts-with(normalize-space(.), "Clear")]');
        $this->assertSame(1, $clear->length);
        $this->assertSame('Clear', trim($clear->item(0)->textContent));
        $this->assertSame(
            $clear->item(0)->getAttribute('href'),
            $xpath->evaluate('string(//*[@id="membership"]/following-sibling::a/@href)'),
        );

        $reset = $this->get($clear->item(0)->getAttribute('href'))->assertOk();
        $this->assertSame('rejected', $reset->viewData('filters')['account_category']);
        $this->assertSame('', $reset->viewData('filters')['search']);
        $this->assertSame('', $reset->viewData('filters')['membership']);
        $this->assertSame(['Rejected Applicant'], $reset->viewData('consumers')->pluck('full_name')->all());
    }

    public function test_membership_filter_combines_with_the_selected_account_category(): void
    {
        $this->assertSame(
            ['Active Member'],
            $this->consumerNames(['account_category' => 'active', 'membership' => 'member']),
        );
        $this->assertSame(
            ['Reconnection Account'],
            $this->consumerNames(['account_category' => 'inactive', 'membership' => 'non_member']),
        );

        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'membership' => 'member',
            'search' => 'Active',
        ]));

        $response->assertOk()
            ->assertSee('<input type="hidden" name="account_category" value="active">', false)
            ->assertSee('data-auto-filter', false);
    }

    public function test_sidebar_has_one_persistent_toggle_and_accessible_navigation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('consumers.index'));

        $response->assertOk()
            ->assertSee('id="sidebarMenu"', false)
            ->assertSee('id="sidebarCollapseToggle"', false)
            ->assertSee('aria-controls="sidebarMenu"', false)
            ->assertSee('id="sidebarBackdrop"', false)
            ->assertSee('id="sidebarMobileToggle"', false)
            ->assertSee('sidebar-collapsed', false)
            ->assertSee('aria-label="Dashboard"', false)
            ->assertSee('title="Dashboard"', false)
            ->assertDontSee('id="sidebarReopenToggle"', false);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="sidebarCollapseToggle"'));
        $this->assertLessThan(strpos($html, '</aside>'), strpos($html, 'id="sidebarCollapseToggle"'));
    }

    public function test_category_submenu_renders_and_highlights_the_selected_category(): void
    {
        $default = $this->actingAs($this->admin)->get(route('consumers.index'));

        $default->assertOk()
            ->assertSee('All Consumers')
            ->assertSee('Pending Accounts')
            ->assertSee('Rejected Accounts')
            ->assertSee('Active Accounts')
            ->assertSee('Inactive (Disconnected)')
            ->assertSee('id="consumerSubmenu"', false)
            ->assertSee('All Membership Records')
            ->assertSee('>Member</option>', false)
            ->assertSee('>Non-Member</option>', false)
            ->assertDontSee('Verified members')
            ->assertDontSee('Pending membership verification</option>', false);

        $defaultHtml = preg_replace('/\s+/', ' ', $default->getContent());
        $this->assertStringContainsString('data-consumer-category="all" aria-current="page"', $defaultHtml);

        $pending = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'pending',
        ]));

        $pending->assertOk();
        $pendingHtml = preg_replace('/\s+/', ' ', $pending->getContent());
        $this->assertStringContainsString('data-consumer-category="pending" aria-current="page"', $pendingHtml);
        $this->assertStringNotContainsString('data-consumer-category="all" aria-current="page"', $pendingHtml);
    }

    public function test_inactive_account_views_show_two_tabs_and_highlight_the_current_view(): void
    {
        $this->actingAs($this->admin);

        foreach (['', 'pending', 'rejected', 'active'] as $category) {
            $this->get(route('consumers.index', ['account_category' => $category]))
                ->assertOk()
                ->assertDontSee('aria-label="Consumer account views"', false)
                ->assertSee('id="consumerSubmenu"', false);
        }

        foreach (['inactive' => 'Inactive Accounts', 'disconnection_history' => 'Disconnection History'] as $category => $label) {
            $response = $this->get(route('consumers.index', ['account_category' => $category]))->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $tabs = '//nav[@aria-label="Consumer account views"]/a';

            $links = iterator_to_array($xpath->query($tabs));
            $this->assertSame(['Inactive Accounts', 'Disconnection History'], array_map(fn ($tab) => trim($tab->textContent), $links));

            $selected = $xpath->query($tabs.'[@aria-current="page"]');
            $this->assertSame(1, $selected->length);
            $this->assertSame($label, trim($selected->item(0)->textContent));
        }
    }

    public function test_account_category_and_membership_are_preserved_in_pagination_links(): void
    {
        for ($index = 1; $index <= 10; $index++) {
            Consumer::query()->create([
                'full_name' => "Active Extra {$index}",
                'account_number' => "CDF-ACTIVE-EXTRA-{$index}",
                'meter_number' => "CDF-M-ACTIVE-EXTRA-{$index}",
                'status' => 'Active',
                'membership_type' => 'Member',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'account_category' => 'active',
            'membership' => 'member',
        ]));

        $response->assertOk();

        $nextPageUrl = $response->viewData('consumers')->nextPageUrl();

        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('account_category=active', $nextPageUrl);
        $this->assertStringContainsString('membership=member', $nextPageUrl);
    }

    public function test_category_submenu_is_not_marked_current_outside_consumer_management(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringNotContainsString('data-consumer-category="all" aria-current="page"', $html);
        $this->assertStringNotContainsString('data-consumer-category="pending" aria-current="page"', $html);
    }

    public function test_invalid_account_category_falls_back_to_all_consumers(): void
    {
        $names = $this->consumerNames(['account_category' => 'not-a-category']);

        $this->assertEqualsCanonicalizing([
            'Pending Applicant',
            'Rejected Applicant',
            'Active Member',
            'Review Account',
            'Disconnected Member',
            'Reconnection Account',
        ], $names);

        $response = $this->actingAs($this->admin)
            ->get(route('consumers.index', ['account_category' => 'not-a-category']));

        $response->assertOk();
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('data-consumer-category="all" aria-current="page"', $html);
    }

    public function test_search_and_the_legacy_pending_membership_shortcut_still_work(): void
    {
        $this->assertSame(
            ['Rejected Applicant'],
            $this->consumerNames(['search' => 'Rejected Applicant']),
        );

        Consumer::query()->create([
            'full_name' => 'Pending Member Claim',
            'account_number' => 'CDF-MEMBER-CLAIM',
            'meter_number' => 'CDF-M-MEMBER-CLAIM',
            'status' => 'Active',
            'membership_type' => 'Member',
            'requested_membership_type' => 'Member',
            'membership_verification_status' => 'Pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('consumers.index', [
            'membership' => 'pending_member_verification',
        ]));

        $response->assertOk()
            ->assertSee('Showing pending membership verifications')
            ->assertSee('Pending Member Claim')
            ->assertSee('<input type="hidden" name="membership_verification" value="pending">', false)
            ->assertDontSee('Active Member');

        $this->assertSame(
            ['Pending Member Claim'],
            $response->viewData('consumers')->pluck('full_name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['Rejected Applicant', 'Review Account', 'Reconnection Account'],
            $this->consumerNames([
                'membership' => 'non_member',
                'membership_verification' => 'pending',
            ]),
        );
    }

    private function consumerNames(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->get(route('consumers.index', $query))
            ->assertOk()
            ->viewData('consumers')
            ->pluck('full_name')
            ->all();
    }
}
