<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DssAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_both_dss_pages_and_loan_report_filters_render(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('dss.incidents'))
            ->assertOk()
            ->assertSee('Incident DSS')
            ->assertDontSee('Automatic filters')
            ->assertDontSee('dss_year', false);

        $this->get(route('dss.loans'))
            ->assertOk()
            ->assertSee('Loan DSS')
            ->assertDontSee('Automatic filters')
            ->assertDontSee('loan_dss_year')
            ->assertDontSee('loan_dss_month')
            ->assertDontSee('loan_dss_status')
            ->assertDontSee('loan_dss_risk')
            ->assertDontSee('loan_dss_term')
            ->assertDontSee('Decision year')
            ->assertDontSee('Final decision')
            ->assertDontSee('Clear</a>', false)
            ->assertSee('dss-kpi-strip')
            ->assertDontSee('loan-dss-filters')
            ->assertSee('data-period-month', false)
            ->assertDontSee('>Apply</button>', false)
            ->assertDontSee('>Filter</button>', false);
    }

    public function test_consumer_and_staff_cannot_access_dss_pages(): void
    {
        foreach (['consumer', 'staff', 'meter_reader'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('dss.incidents'))->assertForbidden();
            $this->actingAs($user)->get(route('dss.loans'))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dss.incidents'))->assertRedirect(route('login'));
        $this->get(route('dss.loans'))->assertRedirect(route('login'));
    }
}
