<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_primary_admin_can_create_meter_readers(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $this->actingAs($admin)->get(route('staff.index'))->assertOk()
            ->assertSee('Meter Reader')->assertDontSee('<option value="admin"', false);
        $payload = ['name' => 'Test Reader', 'email' => 'reader@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->post(route('staff.store'), $payload + ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->post(route('staff.store'), $payload + ['role' => 'meter_reader'])->assertRedirect(route('staff.index'));
        $reader = User::where('email', 'reader@example.test')->firstOrFail();
        $this->assertSame('meter_reader', $reader->role);
        $this->actingAs($reader)->get(route('staff.index'))->assertForbidden();
    }

    public function test_database_rejects_another_active_admin_on_insert_and_update(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        try {
            DB::table('users')->where('id', $reader->id)->update(['role' => 'admin']);
            $this->fail('The database accepted a second administrator.');
        } catch (QueryException $exception) {
            $this->assertSame('meter_reader', $reader->fresh()->role);
        }

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'other@example.test', 'role' => 'admin']);
    }
}
