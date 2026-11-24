<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveAccountsScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function account(string $status): Consumer
    {
        $sequence = Consumer::query()->count() + 1;

        return Consumer::query()->create([
            'full_name' => 'Scope '.$status.' '.$sequence,
            'account_number' => 'SCOPE-'.$sequence,
            'meter_number' => 'SCOPE-METER-'.$sequence,
            'status' => $status,
            'membership_type' => 'Non-Member',
        ]);
    }

    public function test_only_exact_active_status_is_returned_and_accounts_without_notices_stay_in_the_lower_table(): void
    {
        $active = $this->account('Active');
        foreach (['For Review', 'Disconnected', 'For Reconnection', 'Inactive', 'Pending Verification', 'Rejected'] as $status) {
            $this->account($status);
        }

        foreach (['', 'paid', 'disconnected', 'invalid'] as $filter) {
            $response = $this->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => $filter]))->assertOk();
            $this->assertSame([$active->id], $response->viewData('consumers')->pluck('id')->all());
            $this->assertSame([], $response->viewData('disconnectionNotices')->pluck('consumer.id')->all());
            $response->assertDontSee('Scope For Review')->assertDontSee('Scope Disconnected')
                ->assertDontSee('Scope For Reconnection')->assertDontSee('Scope Inactive');
        }
    }

    public function test_active_accounts_hide_all_fee_controls_without_altering_fees_or_other_modules(): void
    {
        $active = $this->account('Active');
        $active->update(['reconnection_fee' => 75.25]);
        $inactive = $this->account('Disconnected');
        Bill::query()->create([
            'consumer_id' => $inactive->id, 'prn' => 'SCOPE-BILL', 'billing_period' => today()->startOfMonth(),
            'due_date' => today()->addDays(10), 'amount' => 100, 'status' => 'Unpaid',
        ]);
        $this->patch(route('consumers.reconnection-fee', $inactive), ['reconnection_fee' => 45.50])->assertSessionHasErrors('reconnection_fee');
        $feeBillId = $inactive->refresh()->reconnection_fee_bill_id;

        $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk()
            ->assertDontSee('Reconnection Fee')->assertDontSee('Save fee')->assertDontSee('name="reconnection_fee"', false)
            ->assertDontSee('<th>Fee</th>', false)->assertDontSee('75.25');

        $this->assertSame('75.25', $active->refresh()->reconnection_fee);
        $this->assertNull($inactive->refresh()->reconnection_fee);
        $this->assertSame($feeBillId, $inactive->reconnection_fee_bill_id);
        $this->assertSame(100.0, app(ConsumerBalanceService::class)->totalOutstandingFor($inactive->id));
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()
            ->assertDontSee('Reconnection Fee (PHP)')->assertDontSee('name="reconnection_fee"', false);
        $this->get(route('payments.index'))->assertOk()->assertDontSee('RECON-FEE-');
        $this->assertSame(100.0, app(ConsumerBalanceService::class)->totalOutstandingFor($inactive->id));
    }

    public function test_search_and_pagination_never_include_inactive_accounts(): void
    {
        for ($index = 0; $index < 11; $index++) {
            $this->account('Active');
            $this->account('Disconnected');
        }

        $response = $this->get(route('consumers.index', ['account_category' => 'active', 'search' => 'Scope']));
        $records = $response->assertOk()->viewData('consumers');
        $this->assertSame(11, $records->total());
        $this->assertCount(10, $records->items());
        $this->assertStringContainsString('search=Scope', $records->nextPageUrl());
        $second = $this->get($records->nextPageUrl())->assertOk()->viewData('consumers');
        $this->assertCount(1, $second->items());
        $this->assertSame(['Active'], $second->pluck('status')->unique()->all());
    }
}
