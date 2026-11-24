<?php
namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterClearControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_menus_contain_only_real_options_and_have_clear_controls(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $consumerUser->id, 'full_name' => 'Test Consumer', 'account_number' => 'FILTER-1', 'meter_number' => 'FILTER-METER', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        foreach ([
            [$admin, '/payments?view=review'],
            [$admin, '/billing/loan'], [$admin, '/meter-readings'],
            [$admin, '/reports?report=incidents&month=&year='], [$admin, '/consumers?account_category=active'], [$admin, '/incidents'],
            [$consumerUser, '/loans'], [$consumerUser, '/billing/loan'], [$consumerUser, '/payments'], [$consumerUser, '/incidents'],
            [$staff, '/meter-readings'],
        ] as [$user, $url]) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            $dom = new \DOMDocument();
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $selects = $xpath->query('//select[@data-clearable-filter]');
            $this->assertGreaterThan(0, $selects->length, $url);
            foreach ($selects as $select) {
                $this->assertSame(0, $xpath->query('./option[@disabled or @value="" or @value="all" or @value="All"]', $select)->length, $url);
                $this->assertSame(1, $xpath->query('./following-sibling::*[(self::button or self::a) and starts-with(@aria-label, "Clear ")]', $select)->length, $url);
            }
        }
    }

    public function test_consumer_loan_queue_shows_one_contextual_empty_message(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $user->id, 'full_name' => 'Queue Consumer', 'account_number' => 'QUEUE-EMPTY', 'meter_number' => 'QUEUE-METER', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);

        $plain = $this->actingAs($user)->get('/loans')->assertOk()->assertSee('No loan applications yet.')->assertDontSee('No loan applications found.');
        $this->assertSame(1, substr_count($plain->getContent(), 'No loan applications yet.'));
        $filtered = $this->get('/loans?status=Rejected')->assertOk()->assertSee('No loan applications match the selected filters.')->assertDontSee('No loan applications yet.');
        $this->assertSame(1, substr_count($filtered->getContent(), 'No loan applications match the selected filters.'));
    }

    public function test_admin_consumer_and_staff_status_controls_clear_without_removing_other_filters(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $consumerUser->id, 'full_name' => 'Test Consumer', 'account_number' => 'FILTER-1', 'meter_number' => 'FILTER-METER', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);

        foreach ([
            [$admin, '/meter-readings?status=Pending&search=Test', 'meter_filter_status', 'status', '', 'search=Test'],
            [$admin, '/reports?report=billing&status=Paid&month=&year=', 'status', 'status', '', 'report=billing&month=&year='],
            [$admin, '/consumers?account_category=active&notice_status=paid&notice_search=Test', 'notice_status', 'notice_status', '', 'account_category=active&notice_search=Test'],
            [$consumerUser, '/loans?status=Pending%20Review', 'loan_filter_status', 'status', '', ''],
            [$consumerUser, '/billing/loan?status=Upcoming&search=Test', 'loan-billing-status', 'status', '', 'search=Test'],
            [$staff, '/meter-readings?status=Pending&search=Test', 'meter_filter_status', 'status', '', 'search=Test'],
        ] as [$user, $url, $id, $name, $empty, $other]) {
            $selected = $this->actingAs($user)->get($url)->assertOk();
            $html = $selected->getContent();
            $this->assertStringNotContainsString('All Status</option>', $html, $url);
            $this->assertStringNotContainsString('Select Status</option>', $html, $url);
            $this->assertStringContainsString('aria-label="Clear status filter"', $html, $url);
            $path = parse_url($url, PHP_URL_PATH);
            $query = $other === '' ? '' : $other.'&';
            $cleared = $this->actingAs($user)->get($path.'?'.$query.$name.'='.urlencode($empty))->assertOk();
            $dom = new \DOMDocument();
            @$dom->loadHTML($cleared->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//select[@id="'.$id.'"]/option[@selected or @disabled or @value="" or @value="all"]')->length, $url);
            $this->assertSame(1, $xpath->query('//select[@id="'.$id.'"]/following-sibling::button[@aria-label="Clear status filter"]')->length, $url);
        }
    }
}
