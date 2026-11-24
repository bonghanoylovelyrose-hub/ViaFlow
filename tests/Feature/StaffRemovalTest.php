<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_remove_reader_and_preserve_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader', 'remember_token' => 'old-token']);
        $consumer = Consumer::create(['full_name' => 'Test Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $reading = MeterReading::create([
            'consumer_id' => $consumer->id, 'reader_id' => $reader->id,
            'meter_number' => 'MT-1', 'reading_date' => now()->toDateString(),
            'previous_reading' => 0, 'current_reading' => 5, 'consumption' => 5, 'status' => 'Encoded',
        ]);

        $this->actingAs($admin)->get(route('staff.index'))->assertOk()
            ->assertSee($reader->name)->assertSee(route('staff.destroy', $reader), false);
        $this->delete(route('staff.destroy', $reader))->assertRedirect(route('staff.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $reader->id]);
        $this->assertNull(User::withTrashed()->findOrFail($reader->id)->remember_token);
        $this->assertSame($reader->id, $reading->fresh()->reader->id);
        $this->get(route('staff.index'))->assertDontSee($reader->name);
    }

    public function test_only_admin_can_remove_staff_and_admin_accounts_cannot_be_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($staff)->delete(route('staff.destroy', $admin))->assertForbidden();
        $this->actingAs($admin)->delete(route('staff.destroy', $admin))->assertForbidden();
        $this->delete(route('staff.destroy', $otherAdmin))->assertForbidden();
        $this->delete(route('staff.destroy', $consumer))->assertForbidden();
        $this->assertDatabaseCount('users', 4);
    }

    public function test_removed_staff_cannot_log_in_or_resume_a_session(): void
    {
        $reader = User::factory()->create(['role' => 'staff', 'password' => Hash::make('password123')]);
        $id = $reader->id;
        $reader->delete();

        $this->post(route('login.attempt'), ['login' => $reader->email, 'password' => 'password123'])
            ->assertSessionHasErrors('login');
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $id])->get(route('meter-readings.index'))
            ->assertRedirect(route('login'));
    }
}
