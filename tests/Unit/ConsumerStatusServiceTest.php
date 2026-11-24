<?php

namespace Tests\Unit;

use App\Models\Consumer;
use App\Services\ConsumerStatusService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumerStatusServiceTest extends TestCase
{
    private ConsumerStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConsumerStatusService;
    }

    public function test_approved_connected_account_is_active(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Active',
            'account_number' => 'AC-100',
            'meter_number' => 'MT-100',
        ]));

        $this->assertSame(ConsumerStatusService::ACTIVE, $display['key']);
        $this->assertSame('Active', $display['label']);
        $this->assertSame('Connected', $display['sub_label']);
        $this->assertSame('badge-soft-success', $display['badge_class']);
        $this->assertSame('consumer-status-sub-success', $display['sub_class']);
        $this->assertSame('bi-check-circle-fill', $display['icon']);
    }

    public function test_for_review_account_keeps_connected_service_status(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'For Review',
            'account_number' => 'AC-101',
            'meter_number' => 'MT-101',
        ]));

        $this->assertSame(ConsumerStatusService::ACTIVE, $display['key']);
    }

    public function test_pending_application_is_awaiting_approval(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Pending Verification',
        ]));

        $this->assertSame(ConsumerStatusService::PENDING, $display['key']);
        $this->assertSame('Pending', $display['label']);
        $this->assertSame('For Approval', $display['sub_label']);
        $this->assertSame('badge-soft-warning', $display['badge_class']);
        $this->assertSame('consumer-status-sub-warning', $display['sub_class']);
        $this->assertSame('bi-clock-fill', $display['icon']);
    }

    public function test_disconnected_and_for_reconnection_accounts_are_inactive(): void
    {
        foreach (['Disconnected', 'For Reconnection'] as $status) {
            $display = $this->service->present($this->consumer([
                'status' => $status,
                'account_number' => 'AC-102',
                'meter_number' => 'MT-102',
            ]));

            $this->assertSame(ConsumerStatusService::INACTIVE, $display['key'], $status);
            $this->assertSame('Inactive', $display['label']);
            $this->assertSame('Disconnected', $display['sub_label']);
            $this->assertSame('badge-soft-neutral', $display['badge_class']);
            $this->assertSame('consumer-status-sub-neutral', $display['sub_class']);
            $this->assertSame('bi-slash-circle-fill', $display['icon']);
        }
    }

    public function test_rejected_application_keeps_its_own_state(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Rejected',
        ]));

        $this->assertSame(ConsumerStatusService::REJECTED, $display['key']);
        $this->assertSame('Rejected', $display['label']);
        $this->assertSame('Not Activated', $display['sub_label']);
        $this->assertSame('badge-soft-danger', $display['badge_class']);
        $this->assertSame('consumer-status-sub-danger', $display['sub_class']);
        $this->assertSame('bi-x-circle-fill', $display['icon']);
    }

    public function test_active_label_without_activation_records_is_pending(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Active',
            'account_number' => 'AC-103',
            'meter_number' => null,
        ]));

        $this->assertSame(ConsumerStatusService::PENDING, $display['key']);
    }

    public function test_disconnection_record_overrides_a_stale_active_label(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Active',
            'account_number' => 'AC-104',
            'meter_number' => 'MT-104',
            'disconnected_at' => Carbon::parse('2026-10-01 08:00:00'),
            'reconnected_at' => null,
        ]));

        $this->assertSame(ConsumerStatusService::INACTIVE, $display['key']);
    }

    public function test_completed_reconnection_overrides_the_for_reconnection_label(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'For Reconnection',
            'account_number' => 'AC-105',
            'meter_number' => 'MT-105',
            'disconnected_at' => Carbon::parse('2026-09-01 08:00:00'),
            'reconnected_at' => Carbon::parse('2026-09-05 08:00:00'),
        ]));

        $this->assertSame(ConsumerStatusService::ACTIVE, $display['key']);
    }

    public function test_a_disconnection_after_reconnection_is_inactive_again(): void
    {
        $display = $this->service->present($this->consumer([
            'status' => 'Active',
            'account_number' => 'AC-106',
            'meter_number' => 'MT-106',
            'disconnected_at' => Carbon::parse('2026-10-01 08:00:00'),
            'reconnected_at' => Carbon::parse('2026-09-05 08:00:00'),
        ]));

        $this->assertSame(ConsumerStatusService::INACTIVE, $display['key']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function consumer(array $attributes): Consumer
    {
        return new Consumer($attributes);
    }
}
