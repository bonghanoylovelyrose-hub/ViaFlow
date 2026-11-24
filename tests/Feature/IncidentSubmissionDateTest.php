<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncidentSubmissionDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_roles_display_actual_submission_times_at_manila_midnight(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => $consumerUser->name,
            'account_number' => 'INCIDENT-DATE-ACCOUNT',
            'meter_number' => 'INCIDENT-DATE-METER',
            'status' => 'Active',
            'membership_type' => 'Member',
            'service_purok' => 'Purok 1-A',
        ]);
        $payload = [
            'consumer_id' => $consumer->id,
            'meter_number' => $consumer->meter_number,
            'reported_by' => $admin->name,
            'category' => 'Water Leak',
            'location' => 'Via Vida',
            'purok' => 'Purok 1-A',
            'reported_at' => '2026-11-23T10:00',
        ];

        $this->travelTo(Carbon::parse('2026-11-23 23:59:59', 'Asia/Manila'));
        $this->actingAs($consumerUser)->post(route('incidents.store'), $payload + ['description' => 'Submitted before midnight.'])
            ->assertRedirect(route('incidents.index'))->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-24 00:00:00', 'Asia/Manila'));
        $this->actingAs($admin)->post(route('incidents.store'), $payload + ['description' => 'Submitted at midnight.'])
            ->assertRedirect(route('incidents.index'))->assertSessionHasNoErrors();

        [$beforeMidnight, $atMidnight] = Incident::query()->orderBy('id')->get();
        $this->assertSame('2026-11-23 15:59:59', $beforeMidnight->getRawOriginal('reported_at'));
        $this->assertSame('2026-11-23 15:59:59', $beforeMidnight->getRawOriginal('created_at'));
        $this->assertSame('2026-11-23 16:00:00', $atMidnight->getRawOriginal('reported_at'));
        $this->assertSame('2026-11-23 16:00:00', $atMidnight->getRawOriginal('created_at'));

        // Simulate a legacy report timestamp overwritten by a later status update.
        $beforeMidnight->update(['reported_at' => '2026-11-24 04:15:00']);

        foreach ([$admin, $consumerUser] as $user) {
            $this->actingAs($user)->get(route('incidents.index'))->assertOk()
                ->assertSee('data-incident-reported-at="Nov 23, 2026 11:59 PM"', false)
                ->assertSee('data-incident-reported-at="Nov 24, 2026 12:00 AM"', false)
                ->assertDontSee('name="reported_at"', false);
        }

        $this->assertSame('2026-11-24 04:15:00', $beforeMidnight->refresh()->getRawOriginal('reported_at'));
    }
}
