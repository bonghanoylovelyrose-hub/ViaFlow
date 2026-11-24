<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsumerInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Installation Admin']);
    }

    private function consumer(array $attributes = []): Consumer
    {
        return Consumer::create(array_merge([
            'full_name' => 'Installation Test Consumer',
            'email' => 'installation@example.test',
            'account_number' => 'INSTALL-1',
            'meter_number' => 'METER-1',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }

    public function test_active_accounts_show_scheduled_pending_and_unscheduled_installation_states(): void
    {
        $admin = $this->admin();
        $scheduled = $this->consumer(['installation_date' => now()->addDays(3)->toDateString()]);
        $unscheduled = $this->consumer(['account_number' => 'INSTALL-2', 'meter_number' => 'METER-2']);

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $response->assertSee('INSTALLATION DETAILS')
            ->assertSee('Pending Installation')
            ->assertSee('Mark Complete')
            ->assertSee('Complete Installation?')
            ->assertSee('Confirm that the meter has been physically installed.')
            ->assertSee('Confirm Complete')
            ->assertSee('Consumer Name')
            ->assertSee('Account Number')
            ->assertSee('Scheduled Date')
            ->assertSee('Current Status')
            ->assertSee('Not Scheduled');

        $html = $response->getContent();
        $start = strpos($html, 'id="consumer-accounts-table"');
        $end = strpos($html, '</table>', $start);
        $table = substr($html, $start, $end - $start);
        $this->assertStringContainsString('Scheduled Date: '.now()->addDays(3)->format('M d, Y'), $table);
        $this->assertStringContainsString('col.consumer-col-installation { width: 21%; }', $html);
        $this->assertStringContainsString('overflow-wrap: break-word;', $html);
        $this->assertStringContainsString('white-space: nowrap;', $html);
        $this->assertStringContainsString('max-width: 767.98px', $html);
        $this->assertStringNotContainsString("confirm('Mark this installation as complete?')", $html);
        $this->assertStringContainsString('data-installation-complete-trigger', $html);
        $scheduledRowStart = strpos($table, 'INSTALL-1');
        $unscheduledRowStart = strpos($table, 'INSTALL-2');
        $scheduledRowEnd = strpos($table, '</tr>', $scheduledRowStart);
        $unscheduledRowEnd = strpos($table, '</tr>', $unscheduledRowStart);
        $this->assertStringContainsString('Pending Installation', substr($table, $scheduledRowStart, $scheduledRowEnd - $scheduledRowStart));
        $this->assertStringContainsString('Mark Complete', substr($table, $scheduledRowStart, $scheduledRowEnd - $scheduledRowStart));
        $this->assertStringContainsString('Not Scheduled', substr($table, $unscheduledRowStart, $unscheduledRowEnd - $unscheduledRowStart));
        $this->assertStringNotContainsString('Mark Complete', substr($table, $unscheduledRowStart, $unscheduledRowEnd - $unscheduledRowStart));
        $this->assertNull($scheduled->fresh()->installation_completed_at);
    }

    public function test_scheduled_installation_can_be_completed_and_repeat_request_preserves_original_audit(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer(['installation_date' => now()->addDays(3)->toDateString()]);
        $originalSchedule = $consumer->installation_date->toDateString();
        $route = route('consumers.installation-complete', $consumer);

        $this->actingAs($admin)->post($route)->assertSessionHas('success');
        $completedAt = $consumer->fresh()->installation_completed_at;
        $this->assertNotNull($completedAt);
        $this->assertSame($admin->id, $consumer->fresh()->installation_completed_by);
        $this->assertSame($originalSchedule, $consumer->fresh()->installation_date->toDateString());
        $this->assertSame('Active', $consumer->fresh()->status);

        $this->actingAs($admin)->post($route)->assertSessionHas('success');
        $this->assertSame($completedAt->timestamp, $consumer->fresh()->installation_completed_at->timestamp);
        $this->assertSame($admin->id, $consumer->fresh()->installation_completed_by);
        $this->assertSame($originalSchedule, $consumer->fresh()->installation_date->toDateString());
        $this->assertSame('Active', $consumer->fresh()->status);

        auth()->logout();
        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active', 'installation_status' => 'completed']))->assertOk()
            ->assertSee('Completed')
            ->assertSee('Installation Completed')
            ->assertSee('Completed By')
            ->assertSee('Completed Date: '.$completedAt->format('M d, Y'));
        $html = $response->getContent();
        $start = strpos($html, 'id="consumer-accounts-table"');
        $end = strpos($html, '</table>', $start);
        $table = substr($html, $start, $end - $start);
        $rowStart = strpos($table, 'INSTALL-1');
        $rowEnd = strpos($table, '</tr>', $rowStart);
        $row = substr($table, $rowStart, $rowEnd - $rowStart);
        $this->assertStringContainsString('Completed', $row);
        $this->assertStringNotContainsString('Mark Complete', $row);
        $this->assertSame(1, preg_match("/data-consumer-details='([^']+)'/", $table, $matches));
        $payload = json_decode(base64_decode(html_entity_decode($matches[1])), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($completedAt->format('F d, Y g:i A'), $payload['installation_completed_at']);
        $this->assertSame('Installation Admin', $payload['installation_completed_by']);
    }

    public function test_unscheduled_installation_cannot_be_completed(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();

        $this->actingAs($admin)->post(route('consumers.installation-complete', $consumer))
            ->assertSessionHasErrors('installation');
        $this->assertNull($consumer->fresh()->installation_completed_at);
        $this->assertNull($consumer->fresh()->installation_completed_by);
    }

    public function test_non_admin_cannot_complete_an_installation(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $consumer = $this->consumer(['installation_date' => now()->addDays(3)->toDateString()]);

        $this->actingAs($staff)->post(route('consumers.installation-complete', $consumer))->assertForbidden();
        $this->assertNull($consumer->fresh()->installation_completed_at);
        $this->assertSame('Active', $consumer->fresh()->status);
    }
}
