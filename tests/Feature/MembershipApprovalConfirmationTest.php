<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use App\Services\ConnectionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembershipApprovalConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_approval_requires_confirmation_even_without_a_pending_claim(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $consumer = $this->applicant();
        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), $this->payload('Member'))
            ->assertSessionHasErrors(['membership_verification_confirmed' => "Please confirm that the applicant's association membership has been verified."]);
        $this->assertSame('Pending Verification', $consumer->fresh()->status);
        $this->assertNull($consumer->fresh()->user_id);
        Mail::assertNothingSent();
    }

    public function test_confirmed_member_can_be_approved(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $consumer = $this->applicant();
        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), $this->payload('Member') + ['membership_verification_confirmed' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Active', $consumer->fresh()->status);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_VERIFIED, $consumer->fresh()->membership_verification_status);
    }

    public function test_non_member_can_be_approved_without_confirmation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $consumer = $this->applicant();
        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), $this->payload('Non-Member'))
            ->assertSessionHasNoErrors();
        $this->assertSame('Active', $consumer->fresh()->status);
        $this->assertFalse($consumer->fresh()->isVerifiedViohoaMember());
    }

    private function applicant(): Consumer
    {
        $consumer = Consumer::create([
            'full_name' => 'Applicant', 'email' => 'applicant@example.com', 'status' => 'Pending Verification',
            'membership_type' => 'Non-Member', 'requested_membership_type' => 'Non-Member',
            'ownership_status' => 'Owner',
        ]);
        app(ConnectionRequestService::class)->createForApplication($consumer);
        return $consumer;
    }

    private function payload(string $type): array
    {
        return ['meter_number' => 'MT-TEST-VERIFY', 'membership_type' => $type, 'installation_date' => now()->addWeek()->toDateString()];
    }
}
