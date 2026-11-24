<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_meter_reader_with_reader_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->post(route('staff.store'), [
            'name' => 'Meter Reader', 'email' => 'reader@example.test', 'role' => 'meter_reader',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('staff.index'));

        $reader = User::where('email', 'reader@example.test')->firstOrFail();
        $this->assertSame('meter_reader', $reader->role);
        $this->assertTrue(Hash::check('password123', $reader->password));
        $this->actingAs($reader)->get(route('meter-readings.index'))->assertOk();
        $this->get(route('staff.index'))->assertForbidden();
        $this->get(route('payments.index'))->assertForbidden();
    }

    public function test_only_meter_reader_role_and_matching_passwords_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->post(route('staff.store'), [
            'name' => 'New Reader', 'email' => 'new-reader@example.test', 'role' => 'meter_reader',
            'password' => 'password123', 'password_confirmation' => 'different123',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new-reader@example.test']);

        $this->post(route('staff.store'), [
            'name' => 'New Admin', 'email' => 'new-admin@example.test', 'role' => 'admin',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.test']);

        $this->post(route('staff.store'), [
            'name' => 'Legacy Staff', 'email' => 'legacy@example.test', 'role' => 'staff',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');
    }
}
