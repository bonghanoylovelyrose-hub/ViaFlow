<?php

namespace Tests\Unit;

use App\Services\ReceiptOcrService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ReceiptOcrServiceTest extends TestCase
{
    public function test_gcash_receipt_ocr_text_parses_payment_fields(): void
    {
        $fields = $this->parseReceiptText('+63 963 143 7522 Sent via GCcsh Amount Total Amount Sent 700.00 p 700, O O Ref Noa 6042 777 234308 Jul 11, 2026 4:53 PM 279g By going digital, you reduce your carbon footprint from transportation, paper, and plastic.');

        $this->assertSame('GCash', $fields['payment_method']);
        $this->assertSame('09631437522', $fields['phone_number']);
        $this->assertSame('700.00', $fields['amount']);
        $this->assertSame('6042777234308', $fields['reference_number']);
        $this->assertSame('2026-07-11', $fields['payment_date']);
    }

    public function test_gcash_receipt_with_fourteen_digit_reference_keeps_every_digit(): void
    {
        $fields = $this->parseReceiptText(implode("\n", [
            '+63 9 0263',
            'Sent via GCash',
            'Amount',
            'Total Amount Sent',
            'Ref No. 7184 2960 508217',
            '279g',
            '2,317.62',
            'P 2,317.62',
            'Oct 11, 2026 2:12 PM',
        ]));

        $this->assertSame('71842960508217', $fields['reference_number']);
        $this->assertSame('2317.62', $fields['amount']);
        $this->assertSame('2026-10-11', $fields['payment_date']);
    }

    public function test_gcash_receipt_with_amount_after_reference_and_date_is_detected(): void
    {
        $fields = $this->parseReceiptText(implode("\n", [
            'GCash',
            'Amount',
            'Total Amount Sent',
            'Ref No. 6046 303 259555',
            '279g',
            '198.00',
            'October 19, 2026, 5:31 PM',
        ]));

        $this->assertSame('GCash', $fields['payment_method']);
        $this->assertSame('6046303259555', $fields['reference_number']);
        $this->assertSame('198.00', $fields['amount']);
        $this->assertSame('2026-10-19', $fields['payment_date']);
        $this->assertSame([], $this->missingRequiredFields($fields));
    }

    public function test_gcash_receipt_with_split_reference_and_ocr_digit_confusions_is_detected(): void
    {
        $fields = $this->parseReceiptText(implode("\n", [
            'GCash',
            'Ref No.',
            '6O46 3O3',
            '259555',
            'Amount Paid: PHP 198.00',
            'Payment Date: October 19, 2026, 5:31 PM',
        ]));

        $this->assertSame('6046303259555', $fields['reference_number']);
        $this->assertSame('198.00', $fields['amount']);
        $this->assertSame('2026-10-19', $fields['payment_date']);
        $this->assertSame([], $this->missingRequiredFields($fields));
    }

    public function test_readable_receipt_with_missing_amount_keeps_other_fields_for_manual_entry(): void
    {
        $fields = $this->parseReceiptText(implode("\n", [
            'GCash',
            'Reference No. 6046 303 259555',
            'October 19, 2026, 5:31 PM',
        ]));

        $this->assertSame('6046303259555', $fields['reference_number']);
        $this->assertNull($fields['amount']);
        $this->assertSame('2026-10-19', $fields['payment_date']);
        $this->assertSame(['Amount Paid'], $this->missingRequiredFields($fields));
    }

    private function parseReceiptText(string $text): array
    {
        $service = new ReceiptOcrService;
        $method = (new ReflectionClass($service))->getMethod('parseReceiptText');
        $method->setAccessible(true);

        return $method->invoke($service, $text);
    }

    private function missingRequiredFields(array $fields): array
    {
        $service = new ReceiptOcrService;
        $method = (new ReflectionClass($service))->getMethod('missingRequiredFields');
        $method->setAccessible(true);

        return $method->invoke($service, $fields);
    }
}
