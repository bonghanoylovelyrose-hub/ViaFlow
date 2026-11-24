<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateUploadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_proof_is_available_only_to_its_owner_and_admin(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proofs/private.txt', 'private proof');
        $owner = User::factory()->create(['role' => 'consumer']);
        $other = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $staff = User::factory()->create(['role' => 'staff']);
        $consumer = Consumer::create([
            'user_id' => $owner->id, 'full_name' => 'Owner', 'account_number' => 'OWN-1',
            'meter_number' => 'MT-OWN-1', 'status' => 'Active', 'membership_type' => 'Non-Member',
        ]);
        $bill = Bill::create([
            'consumer_id' => $consumer->id, 'billing_period' => '2026-01-01', 'due_date' => '2026-01-31',
            'prn' => 'PRIVATE-PRN', 'amount' => 20, 'status' => 'Pending', 'months_unpaid' => 0,
        ]);
        Payment::create([
            'bill_id' => $bill->id, 'consumer_id' => $consumer->id, 'reference_number' => 'PRIVATE-PAYMENT',
            'amount' => 20, 'payment_date' => now(),
            'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Pending',
            'proof_path' => 'proofs/private.txt',
        ]);
        $url = route('uploads.serve', ['path' => 'proofs/private.txt']);
        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($staff)->get($url)->assertForbidden();
    }

    public function test_consumer_cannot_read_an_unrelated_private_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/id.txt', 'identity document');
        $consumer = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($consumer)->get(route('uploads.serve', ['path' => 'documents/id.txt']))->assertForbidden();
    }
}
