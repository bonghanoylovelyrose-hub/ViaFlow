<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\User;
use App\Services\ReceiptOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostNewPaymentModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_new_payment_modal_has_clickable_and_interactive_elements(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Jane Consumer',
            'account_number' => 'ACC-1234',
            'meter_number' => 'MTR-1234',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'prn' => 'PRN-123456',
            'amount' => 500.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $response = $this->actingAs($consumerUser)->get(route('payments.index'));

        $response->assertOk();

        // 1. Modal structure & title
        $response->assertSee('id="paymentModal"', false);
        $response->assertSee('Add Payment');

        // 2. All form fields are present and not blocked by readonly
        $response->assertSee('id="sender_name"', false);
        $response->assertSee('id="phone_number"', false);
        $response->assertSee('id="payment_method"', false);
        $response->assertSee('id="gateway_reference"', false);
        $response->assertSee('id="amount"', false);
        $response->assertSee('id="payment_date"', false);

        // Ensure gateway_reference, amount, payment_date do NOT have the readonly attribute
        $response->assertDontSee('id="gateway_reference" name="gateway_reference" value="" readonly', false);
        $response->assertDontSee('id="amount" name="amount" step="0.01" min="0.01" value="" readonly', false);
        $response->assertDontSee('id="payment_date" name="payment_date" value="" readonly', false);

        // 3. Receipt dropzone and accessible file picker
        $response->assertSee('id="proof_dropzone"', false);
        $response->assertSee('role="button"', false);
        $response->assertSee('id="proof"', false);
        $response->assertSee('class="visually-hidden"', false);
        $response->assertDontSee('class="d-none" id="proof"', false);

        // 4. Close (X) button
        $response->assertSee('class="btn-close" data-bs-dismiss="modal"', false);

        // 5. Submit Payment button is clickable (NOT disabled when balance > 0)
        $response->assertSee('id="submit_payment_button"', false);
        $response->assertDontSee('id="submit_payment_button" disabled', false);

        // 6. Confirmation modal & buttons
        $response->assertSee('id="paymentSubmitConfirmModal"', false);
        $response->assertSee('Confirm Payment Submission');
        $response->assertSee('id="cancel_submit_payment_button"', false);
        $response->assertSee('Cancel');
        $response->assertSee('id="confirm_submit_payment_button"', false);
        $response->assertSee('Yes, Submit for Confirmation');

        // 7. Proper CSS stacking order (modal is higher than backdrop 1055 > 1050)
        $response->assertSee('#paymentModal {', false);
        $response->assertSee('z-index: 1055;', false);
        $response->assertSee('#paymentSubmitConfirmModal {', false);
        $response->assertSee('z-index: 1070;', false);
        $response->assertSee('.modal-backdrop {', false);
        $response->assertSee('z-index: 1050;', false);
        $response->assertSee('.modal-backdrop ~ .modal-backdrop', false);
        $response->assertSee('z-index: 1065;', false);

        // Buggy backdrop selector must be absent
        $response->assertDontSee('#paymentSubmitConfirmModal ~ .modal-backdrop');
    }

    public function test_complete_payment_submission_flow_with_receipt(): void
    {
        Storage::fake('local');

        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Jane Consumer',
            'account_number' => 'ACC-1234',
            'meter_number' => 'MTR-1234',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'prn' => 'PRN-123456',
            'amount' => 500.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        // Mock ReceiptOcrService to simulate successful OCR scan
        $mockOcr = $this->createMock(ReceiptOcrService::class);
        $mockOcr->method('extract')->willReturn([
            'ok' => true,
            'fields' => [
                'reference_number' => 'GCASH-99887766',
                'amount' => 500.00,
                'payment_date' => '2026-08-20',
                'payment_method' => 'GCash',
            ],
            'missing_fields' => [],
        ]);
        $this->app->instance(ReceiptOcrService::class, $mockOcr);

        $file = UploadedFile::fake()->image('receipt.png');

        $response = $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_date' => '2026-08-20',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'GCASH-99887766',
            'sender_name' => 'Jane Consumer',
            'sender_phone' => '09171234567',
            'proof' => $file,
        ]);

        $response->assertRedirect(route('payments.index'));
        $response->assertSessionHas('success', 'Payment has been submitted and is Pending Verification by the administrator.');

        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'gateway_reference' => 'GCASH-99887766',
            'amount' => 500.00,
            'status' => 'Pending',
        ]);
    }

    public function test_post_new_payment_modal_shows_required_amount_under_disconnection_notice(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Flagged Consumer',
            'account_number' => 'ACC-FLAGGED',
            'meter_number' => 'MTR-FLAGGED',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-05-01',
            'due_date' => '2026-05-31',
            'prn' => 'PRN-MAY',
            'amount' => 300.00,
            'status' => 'Unpaid',
            'months_unpaid' => 3,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-06-01',
            'due_date' => '2026-06-30',
            'prn' => 'PRN-JUNE',
            'amount' => 300.00,
            'status' => 'Unpaid',
            'months_unpaid' => 2,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-07-01',
            'due_date' => '2026-07-31',
            'prn' => 'PRN-JULY',
            'amount' => 300.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $consumer->syncAccountStanding();

        $response = $this->actingAs($consumerUser)->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee('Disconnection Notice: <strong>Active</strong>', false);
        $response->assertSee('Full payment is required. Partial payment is not allowed.');
        $response->assertSee('id="paymentModal"', false);
        $response->assertSee('id="submit_payment_button"', false);
    }

    public function test_parse_receipt_endpoint_returns_json_fields_for_client_autofill(): void
    {
        Storage::fake('local');

        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $mockOcr = $this->createMock(ReceiptOcrService::class);
        $mockOcr->method('extract')->willReturn([
            'ok' => true,
            'fields' => [
                'reference_number' => 'GCASH-11223344',
                'amount' => 750.50,
                'payment_date' => '2026-08-22',
                'payment_method' => 'GCash',
                'phone_number' => '09181234567',
            ],
            'missing_fields' => [],
        ]);
        $this->app->instance(ReceiptOcrService::class, $mockOcr);

        $file = UploadedFile::fake()->image('my_receipt.jpg');

        $response = $this->actingAs($consumerUser)
            ->post(route('payments.parse-receipt'), [
                'proof' => $file,
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'fields' => [
                'reference_number' => 'GCASH-11223344',
                'amount' => 750.50,
                'payment_date' => '2026-08-22',
                'payment_method' => 'GCash',
                'phone_number' => '09181234567',
            ],
        ]);
    }

    public function test_parse_receipt_endpoint_returns_partial_fields_for_manual_entry(): void
    {
        Storage::fake('local');

        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $mockOcr = $this->createMock(ReceiptOcrService::class);
        $mockOcr->method('extract')->willReturn([
            'ok' => true,
            'fields' => [
                'reference_number' => '6046303259555',
                'payment_date' => '2026-10-19',
                'payment_method' => 'GCash',
            ],
            'missing_fields' => ['Amount Paid'],
        ]);
        $this->app->instance(ReceiptOcrService::class, $mockOcr);

        $response = $this->actingAs($consumerUser)
            ->post(route('payments.parse-receipt'), [
                'proof' => UploadedFile::fake()->image('partial_receipt.jpg'),
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'fields' => [
                'reference_number' => '6046303259555',
                'payment_date' => '2026-10-19',
            ],
            'missing_fields' => ['Amount Paid'],
            'manual_entry_required' => true,
        ]);
    }

    public function test_payment_submission_uses_manual_details_when_ocr_cannot_read_receipt(): void
    {
        Storage::fake('local');

        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Manual Entry Consumer',
            'account_number' => 'ACC-MANUAL',
            'meter_number' => 'MTR-MANUAL',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'prn' => 'PRN-MANUAL',
            'amount' => 500.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $mockOcr = $this->createMock(ReceiptOcrService::class);
        $mockOcr->method('extract')->willReturn([
            'ok' => false,
            'fields' => [],
            'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
            'manual_entry_required' => true,
        ]);
        $this->app->instance(ReceiptOcrService::class, $mockOcr);

        $response = $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_date' => '2026-10-19',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'MANUAL-6046303259555',
            'sender_name' => 'Manual Entry Consumer',
            'sender_phone' => '09171234567',
            'proof' => UploadedFile::fake()->image('unreadable_receipt.png'),
        ]);

        $response->assertRedirect(route('payments.index'));
        $response->assertSessionHas('success', 'Payment has been submitted and is Pending Verification by the administrator.');

        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'gateway_reference' => 'MANUAL-6046303259555',
            'amount' => 500.00,
            'payment_date' => '2026-10-19 00:00:00',
            'status' => 'Pending',
        ]);
    }

    public function test_payment_submission_combines_detected_fields_with_manual_missing_fields(): void
    {
        Storage::fake('local');

        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Partial OCR Consumer',
            'account_number' => 'ACC-PARTIAL',
            'meter_number' => 'MTR-PARTIAL',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31',
            'prn' => 'PRN-PARTIAL',
            'amount' => 500.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $mockOcr = $this->createMock(ReceiptOcrService::class);
        $mockOcr->method('extract')->willReturn([
            'ok' => true,
            'fields' => [
                'reference_number' => '6046303259555',
                'payment_date' => '2026-10-19',
                'payment_method' => 'GCash',
            ],
            'missing_fields' => ['Amount Paid'],
            'manual_entry_required' => true,
        ]);
        $this->app->instance(ReceiptOcrService::class, $mockOcr);

        $response = $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_date' => '2026-10-20',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'MANUAL-WRONG-REFERENCE',
            'proof' => UploadedFile::fake()->image('partial_receipt.png'),
        ]);

        $response->assertRedirect(route('payments.index'));
        $response->assertSessionHas('success', 'Payment has been submitted and is Pending Verification by the administrator.');

        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'gateway_reference' => '6046303259555',
            'amount' => 500.00,
            'payment_date' => '2026-10-19 00:00:00',
            'status' => 'Pending',
        ]);
    }
}
