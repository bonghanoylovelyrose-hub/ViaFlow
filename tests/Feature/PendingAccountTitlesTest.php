<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\InstallationRescheduleRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingAccountTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_types_render_matching_headings_and_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $application = Consumer::create(['full_name' => 'Pending Applicant', 'status' => 'Pending Verification', 'membership_type' => 'Non-Member']);
        $scheduled = Consumer::create(['full_name' => 'Reschedule Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        InstallationRescheduleRequest::create([
            'consumer_id' => $scheduled->id, 'reschedule_type' => 'connection', 'status' => 'pending',
            'reason' => 'Unable to attend', 'original_installation_date' => '2026-11-10',
        ]);

        $this->actingAs($admin);
        foreach ([
            'all' => ['All Pending Accounts', 'View and manage all pending consumer applications and reschedule requests.'],
            'applications' => ['Connection Applications', 'Review and manage pending water connection applications.'],
            'reschedules' => ['Reschedule Requests', 'Review and manage consumer installation reschedule requests.'],
        ] as $type => [$title, $description]) {
            $response = $this->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => $type]));
            $response->assertOk()->assertSee($title)->assertSee($description);
            $ids = $response->viewData('consumers')->pluck('id')->all();
            $this->assertSame($type !== 'reschedules', in_array($application->id, $ids));
            $this->assertSame($type !== 'applications', in_array($scheduled->id, $ids));
        }

        $this->get(route('consumers.index'))->assertOk()->assertSee('Consumer Management');
    }
}
