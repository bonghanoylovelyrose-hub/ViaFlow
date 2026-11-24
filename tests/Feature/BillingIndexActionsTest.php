<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingIndexActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_keeps_record_creation_available_without_the_header_create_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('billing.index'));

        $response->assertOk()
            ->assertSee('Generate Cycle')
            ->assertSee('bi-gear', false)
            ->assertSee('data-bs-target="#billingGenerationModal"', false)
            ->assertDontSee('Create Record')
            ->assertDontSee('bi-file-earmark-plus', false)
            ->assertSee('Create Billing Record')
            ->assertSee('data-bs-target="#billingModal"', false)
            ->assertSee('id="billingModal"', false);
    }

    public function test_billing_page_omits_ready_for_billing_disconnection_watchlist_and_connection_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('billing.index'));

        $response->assertOk()
            ->assertDontSee('Ready for Billing')
            ->assertDontSee('Disconnection Watchlist')
            ->assertDontSee('Connection Requests')
            ->assertDontSee('Log Service Request')
            ->assertDontSee('Review Service Request')
            ->assertDontSee('New Request')
            ->assertDontSee('id="serviceRequestModal"', false)
            ->assertDontSee('id="serviceRequestReviewModal"', false)
            ->assertSee('Billing Center')
            ->assertSee('Generate Cycle')
            ->assertSee('Billing Records')
            ->assertSee('id="billing_filter_status"', false)
            ->assertSee('id="billingGenerationModal"', false)
            ->assertSee('id="billingModal"', false);
    }
}
