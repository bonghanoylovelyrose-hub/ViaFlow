<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ReceiptOcrService
{
    public function extract(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            return [
                'ok' => false,
                'message' => 'Auto-fill is currently supported for JPG, PNG, and PDF receipts only.',
                'text' => '',
                'fields' => [],
                'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                'manual_entry_required' => true,
            ];
        }

        $tempDirectory = base_path('tmp');
        File::ensureDirectoryExists($tempDirectory);

        $tempBaseName = 'ocr-'.bin2hex(random_bytes(8));
        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.$tempBaseName.'.'.$extension;
        File::copy($file->getRealPath(), $tempPath);
        $ocrImagePath = $tempPath;

        if ($extension === 'pdf') {
            $ocrImagePath = $tempDirectory.DIRECTORY_SEPARATOR.$tempBaseName.'.png';
            $renderResult = $this->renderPdfFirstPageToImage($tempPath, $ocrImagePath);

            if (! ($renderResult['ok'] ?? false)) {
                File::delete($tempPath);

                return [
                    'ok' => false,
                    'message' => $renderResult['message'] ?? 'Unable to prepare the PDF receipt for scanning.',
                    'text' => '',
                    'fields' => [],
                    'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                    'manual_entry_required' => true,
                ];
            }
        }

        $environment = array_filter([
            'SystemRoot' => env('SystemRoot', getenv('SystemRoot') ?: 'C:\\Windows'),
            'WINDIR' => env('WINDIR', getenv('WINDIR') ?: 'C:\\Windows'),
            'TEMP' => env('TEMP', getenv('TEMP') ?: sys_get_temp_dir()),
            'TMP' => env('TMP', getenv('TMP') ?: sys_get_temp_dir()),
            'USERPROFILE' => env('USERPROFILE', getenv('USERPROFILE') ?: null),
            'APPDATA' => env('APPDATA', getenv('APPDATA') ?: null),
            'LOCALAPPDATA' => env('LOCALAPPDATA', getenv('LOCALAPPDATA') ?: null),
            'HOMEDRIVE' => env('HOMEDRIVE', getenv('HOMEDRIVE') ?: null),
            'HOMEPATH' => env('HOMEPATH', getenv('HOMEPATH') ?: null),
            'Path' => env('Path', getenv('Path') ?: null),
            'PATHEXT' => env('PATHEXT', getenv('PATHEXT') ?: null),
            'PSModulePath' => env('PSModulePath', getenv('PSModulePath') ?: null),
            'COMSPEC' => env('COMSPEC', getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe'),
        ], fn ($value) => filled($value));

        $process = new Process([
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            '-NoProfile',
            '-NoLogo',
            '-NonInteractive',
            '-Sta',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            base_path('scripts/ocr-receipt.ps1'),
            $ocrImagePath,
        ], base_path(), $environment);

        $process->setTimeout(60);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            File::delete($tempPath);
            if ($ocrImagePath !== $tempPath) {
                File::delete($ocrImagePath);
            }

            Log::warning('Receipt OCR process could not complete', [
                'original_name' => $file->getClientOriginalName(),
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Receipt OCR could not complete automatically. You can still enter the reference number, amount paid, and payment date manually.',
                'text' => '',
                'fields' => [],
                'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                'manual_entry_required' => true,
            ];
        }

        try {
            Log::debug('Receipt OCR process completed', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'extension' => $extension,
                'source_real_path' => $file->getRealPath(),
                'temp_path' => $tempPath,
                'ocr_image_path' => $ocrImagePath,
                'command' => $process->getCommandLine(),
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);

            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'Receipt OCR failed: '.$error
                        : 'Receipt OCR could not be completed on this machine. You can enter the payment details manually.',
                    'text' => '',
                    'fields' => [],
                    'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                    'manual_entry_required' => true,
                ];
            }

            $payload = json_decode($process->getOutput(), true);
        } finally {
            File::delete($tempPath);
            if ($ocrImagePath !== $tempPath) {
                File::delete($ocrImagePath);
            }
        }

        if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $payload['message'] ?? 'No readable text was found in the uploaded receipt. You can enter the payment details manually.',
                'text' => $payload['text'] ?? '',
                'fields' => [],
                'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                'manual_entry_required' => true,
            ];
        }

        $text = (string) ($payload['text'] ?? '');

        Log::debug('Receipt OCR raw result before parsing', [
            'original_name' => $file->getClientOriginalName(),
            'text' => $text,
            'payload' => $payload,
        ]);

        if (trim($text) === '') {
            return [
                'ok' => false,
                'message' => 'Receipt OCR executed successfully, but the OCR engine returned empty text. You can enter the reference number, amount paid, and payment date manually.',
                'text' => '',
                'fields' => [],
                'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                'manual_entry_required' => true,
            ];
        }

        $fields = $this->parseReceiptText($text);
        $missingFields = $this->missingRequiredFields($fields);

        Log::debug('Receipt OCR raw output', [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'text' => $text,
            'fields' => $fields,
            'missing_fields' => $missingFields,
        ]);

        return [
            'ok' => true,
            'message' => $missingFields === []
                ? 'Receipt details detected.'
                : 'Receipt was readable, but these details need confirmation: '.implode(', ', $missingFields).'. You can enter them manually.',
            'text' => $text,
            'fields' => $fields,
            'missing_fields' => $missingFields,
            'manual_entry_required' => $missingFields !== [],
        ];
    }

    private function renderPdfFirstPageToImage(string $pdfPath, string $imagePath): array
    {
        $chromePath = $this->findChromePath();

        if ($chromePath === null) {
            return [
                'ok' => false,
                'message' => 'Unable to scan PDF receipts because Chrome is not available to render the PDF page.',
            ];
        }

        $profileDirectory = dirname($imagePath).DIRECTORY_SEPARATOR.pathinfo($imagePath, PATHINFO_FILENAME).'-chrome';
        File::ensureDirectoryExists($profileDirectory);

        $process = new Process([
            $chromePath,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--user-data-dir='.$profileDirectory,
            '--window-size=1200,1800',
            '--screenshot='.$imagePath,
            'file:///'.str_replace('\\', '/', $pdfPath),
        ], base_path());

        $process->setTimeout(45);
        $process->run();
        File::deleteDirectory($profileDirectory);

        if (! $process->isSuccessful() || ! File::exists($imagePath) || File::size($imagePath) <= 0) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            return [
                'ok' => false,
                'message' => $error !== ''
                    ? 'PDF receipt rendering failed: '.$error
                    : 'Unable to render the PDF receipt for OCR.',
            ];
        }

        return ['ok' => true];
    }

    private function findChromePath(): ?string
    {
        $candidates = [
            env('CHROME_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            (getenv('LOCALAPPDATA') ?: '').'\\Google\\Chrome\\Application\\chrome.exe',
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate) && File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function parseReceiptText(string $text): array
    {
        $prepared = $this->normalizeReceiptText($text);
        $normalized = preg_replace('/[ \t]+/', ' ', $prepared) ?? $prepared;
        $lines = $this->receiptLines($prepared);

        return [
            'payment_method' => $this->detectPaymentMethod($normalized),
            'reference_number' => $this->extractReferenceNumber($prepared, $normalized, $lines),
            'phone_number' => $this->extractPhoneNumber($prepared, $normalized),
            'amount' => $this->extractAmount($normalized, $lines),
            'payment_date' => $this->extractPaymentDate($normalized, $lines),
            'notes' => $this->buildNotes($normalized),
        ];
    }

    private function missingRequiredFields(array $fields): array
    {
        $missing = [];

        if (! is_numeric($fields['amount'] ?? null) || (float) $fields['amount'] <= 0) {
            $missing[] = 'Amount Paid';
        }

        if (! $this->isValidReferenceNumber($fields['reference_number'] ?? null)) {
            $missing[] = 'Reference Number';
        }

        if (! $this->isValidPaymentDate($fields['payment_date'] ?? null)) {
            $missing[] = 'Payment Date';
        }

        return $missing;
    }

    private function isValidReferenceNumber(mixed $referenceNumber): bool
    {
        if (! is_string($referenceNumber)) {
            return false;
        }

        $compact = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $referenceNumber) ?? '');

        return strlen($compact) >= 8 && strlen($compact) <= 40;
    }

    private function isValidPaymentDate(mixed $paymentDate): bool
    {
        if (! is_string($paymentDate) || trim($paymentDate) === '') {
            return false;
        }

        try {
            now()->parse($paymentDate)->toDateString();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function receiptLines(string $text): array
    {
        return collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }

    private function normalizeReceiptText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = str_replace(['₱', 'â‚±', 'Ã¢â€šÂ±'], 'PHP ', $text);
        $text = str_replace(['PHP:', 'Php:', 'php:'], 'PHP ', $text);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        // GCash places a carbon-footprint weight next to the reference number.
        // OCR can otherwise treat those digits as part of the reference.
        return preg_replace('/\b\d{1,4}\s*g\b(?=\s*(?:By\s+going\s+digital|$))/iu', '', $text) ?? $text;
    }

    private function referenceLabelPattern(): string
    {
        return '(?:\bref(?:erence)?\.?(?:\s*(?:noa|no\.?|num(?:ber)?))?(?![A-Za-z])|\btransaction\s*(?:id|no\.?|number)(?![A-Za-z])|\bconfirmation\s*(?:no\.?|number)(?![A-Za-z])|\btrace\s*(?:no\.?|number)(?![A-Za-z])|\brrn\b|\bpayment\s*reference\b|\bgateway\s*reference\b)';
    }

    private function looksLikeReferenceStopLine(string $value): bool
    {
        return preg_match('/^(?:amount|total|payment|date|time|sender|receiver|received|sent\s+via|status|fee|note|reference|ref|by\s+going\s+digital)\b/i', trim($value)) === 1;
    }

    private function extractReferenceNumber(string $rawText, string $normalizedText, array $lines): ?string
    {
        $labelOnlyPattern = '/^\s*'.$this->referenceLabelPattern().'\s*[:#.\-]?\s*$/i';

        foreach ($lines as $index => $line) {
            if (preg_match($labelOnlyPattern, $line) !== 1) {
                continue;
            }

            $fragments = [];

            for ($offset = 1; $offset <= 4; $offset++) {
                $candidateLine = trim((string) ($lines[$index + $offset] ?? ''));

                if ($candidateLine === '') {
                    continue;
                }

                if ($this->looksLikeDate($candidateLine)) {
                    break;
                }

                if ($this->looksLikeAmount($candidateLine) || $this->looksLikeReferenceStopLine($candidateLine)) {
                    continue;
                }

                $fragments[] = $candidateLine;

                foreach ([$candidateLine, implode(' ', $fragments)] as $candidate) {
                    $referenceNumber = $this->normalizeReferenceNumber($candidate);

                    if ($referenceNumber !== null) {
                        return $referenceNumber;
                    }
                }
            }
        }

        foreach ($lines as $line) {
            $referenceNumber = $this->extractReferenceNumberFromLabeledLine($line);

            if ($referenceNumber !== null) {
                return $referenceNumber;
            }
        }

        foreach (preg_split('/\n+/', $rawText) ?: [] as $line) {
            $referenceNumber = $this->extractReferenceNumberFromDateTimeLine((string) $line);

            if ($referenceNumber !== null) {
                return $referenceNumber;
            }
        }

        if (preg_match('/\b(\d{13,16})\b(?=.*\b(?:\d{1,2}:\d{2}\s*(?:AM|PM)?|[A-Z][a-z]{2,8}\s+\d{1,2},?\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{2,4})\b)/i', $normalizedText, $matches) === 1) {
            return $this->normalizeReferenceNumber($matches[1]);
        }

        // GCash normally groups references as 4-4-6 or 4-3-6. Matching the
        // whole format stops OCR from appending the carbon-footprint value.
        if (preg_match('/(?<!\d)(\d{4}[\s-]?\d{3,4}[\s-]?\d{6})(?!\d)/', $normalizedText, $matches) === 1) {
            return $this->normalizeReferenceNumber($matches[1]);
        }

        if (preg_match('/\b((?:\d[\s-]*){13,16})\b/', $normalizedText, $matches) === 1) {
            return $this->normalizeReferenceNumber($matches[1]);
        }

        return null;
    }

    private function extractReferenceNumberFromLabeledLine(string $line): ?string
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        if (preg_match('/'.$this->referenceLabelPattern().'\s*[:#.\-]?\s*(.+)$/i', $line, $matches) !== 1) {
            return null;
        }

        $candidate = trim($matches[1]);

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/(?<!\d)(\d{4}[\s-]?\d{3,4}[\s-]?\d{6})(?!\d)/', $candidate, $referenceMatches) === 1) {
            return $this->normalizeReferenceNumber($referenceMatches[1]);
        }

        if (preg_match('/^\s*((?:\d[\s-]*){8,20})/', $candidate, $referenceMatches) === 1) {
            $referenceNumber = $this->normalizeReferenceNumber($referenceMatches[1]);

            if ($referenceNumber !== null) {
                return $referenceNumber;
            }
        }

        if (preg_match('/^\s*([A-Z0-9][A-Z0-9-]*(?:\s+[A-Z0-9-]+){0,5})/i', $candidate, $referenceMatches) === 1) {
            return $this->normalizeReferenceNumber($referenceMatches[1]);
        }

        return $this->normalizeReferenceNumber($candidate);
    }

    private function extractReferenceNumberFromDateTimeLine(string $line): ?string
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $hasDateOrTime = preg_match(
            '/\b(?:\d{1,2}:\d{2}\s*(?:AM|PM)?|[A-Z][a-z]{2,8}\s+\d{1,2},?\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{2,4})\b/i',
            $line
        ) === 1;

        if (! $hasDateOrTime) {
            return null;
        }

        if (preg_match('/(?<!\d)(\d{4}[\s-]?\d{3,4}[\s-]?\d{6})(?!\d)/', $line, $matches) === 1) {
            return $this->normalizeReferenceNumber($matches[1]);
        }

        if (preg_match('/\b(\d{13,16})\b/', $line, $matches) === 1) {
            return $this->normalizeReferenceNumber($matches[1]);
        }

        if (preg_match('/\b((?:\d[\s-]*){13,16})\b/', $line, $matches) === 1) {
            return $this->normalizeReferenceNumber(preg_replace('/\D/', '', $matches[1]) ?? '');
        }

        return null;
    }

    private function normalizeReferenceNumber(string $value): ?string
    {
        $candidate = trim(preg_replace('/^\s*'.$this->referenceLabelPattern().'\s*[:#.\-]?\s*/i', '', $value) ?? $value);
        $candidate = trim(preg_replace('/\s+\d{1,4}\s*g\b.*$/i', '', $candidate) ?? $candidate);
        $candidate = trim(preg_replace('/\s+(?:amount\s*(?:paid|sent)?|total\s+amount\s+sent|payment\s+amount|payment\s+date|transaction\s+date|date\s+and\s+time|date\/time|sender|sent\s+via|paid\s+by|received\s+by)\b.*$/i', '', $candidate) ?? $candidate);
        $candidate = trim(preg_replace('/\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}\b.*$/i', '', $candidate) ?? $candidate);
        $candidate = trim(preg_replace('/\s+\d{1,2}:\d{2}\s*(?:AM|PM)\b.*$/i', '', $candidate) ?? $candidate);
        $candidate = trim(preg_replace('/\s+(?:PHP|P|₱)\s*\d.*$/i', '', $candidate) ?? $candidate);
        $candidate = trim(preg_replace('/\s+\d{1,3}(?:,\d{3})*(?:\.\d{1,2})\b.*$/', '', $candidate) ?? $candidate);
        $candidate = trim($candidate, " \t\n\r\0\x0B:#-.");

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/(?<!\d)(\d{4}[\s-]?\d{3,4}[\s-]?\d{6})(?!\d)/', $candidate, $matches) === 1) {
            return preg_replace('/\D/', '', $matches[1]) ?: null;
        }

        if (preg_match('/(?<!\d)(\d{12,20})(?!\d)/', $candidate, $matches) === 1) {
            return $matches[1];
        }

        $compact = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $candidate) ?? '');

        if ($compact === '') {
            return null;
        }

        if (preg_match('/^[0-9OILSBZ]+$/', $compact) === 1) {
            $numeric = strtr($compact, ['O' => '0', 'I' => '1', 'L' => '1', 'S' => '5', 'B' => '8', 'Z' => '2']);

            return strlen($numeric) >= 8 && strlen($numeric) <= 24 ? $numeric : null;
        }

        if (preg_match('/(?<!\d)((?:\d[\s-]*){8,20})(?!\d)/', $candidate, $matches) === 1) {
            $digits = preg_replace('/\D/', '', $matches[1]) ?? '';

            if (strlen($digits) >= 8 && strlen($digits) <= 20) {
                return $digits;
            }
        }

        return strlen($compact) >= 8 && strlen($compact) <= 40 ? $compact : null;
    }

    private function detectPaymentMethod(string $text): ?string
    {
        $lower = strtolower($text);

        return match (true) {
            str_contains($lower, 'gcash') || str_contains($lower, 'gcosh') || str_contains($lower, 'gccsh') => 'GCash',
            str_contains($lower, 'maya') || str_contains($lower, 'paymaya') => 'Maya',
            str_contains($lower, 'bpi') => 'BPI',
            str_contains($lower, 'bdo') => 'BDO',
            str_contains($lower, 'metrobank') => 'Metrobank',
            str_contains($lower, 'unionbank') => 'UnionBank',
            str_contains($lower, 'bank transfer') => 'Bank Transfer',
            default => null,
        };
    }

    private function extractAmount(string $text, array $lines): ?string
    {
        $amountLabelPattern = '/(?:total\s+amount\s+sent|amount\s+sent|amount\s+paid|payment\s+amount|amount|total|you\s+sent|paid|send\s+money)/i';

        foreach ($lines as $index => $line) {
            if (preg_match($amountLabelPattern, $line) !== 1) {
                continue;
            }

            // GCash OCR commonly returns the labels first and the amount after
            // the reference/date because of the receipt's column layout.
            $lastCandidateIndex = min(count($lines) - 1, $index + 8);

            for ($candidateIndex = $index; $candidateIndex <= $lastCandidateIndex; $candidateIndex++) {
                $amount = $this->normalizeAmount($lines[$candidateIndex]);

                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        $patterns = [
            '/(?:total\s+amount\s+sent|amount\s+sent|amount\s+paid|payment\s+amount|amount|total|you\s+sent|paid|send\s+money)\s*[:\-\n ]+\s*(?:PHP|P)?\s*([0-9][0-9,\s]*(?:\.[0-9]{1,2})?)/i',
            '/(?:PHP|P|₱)\s*([0-9][0-9,\s]*(?:\.[0-9]{1,2})?)/i',
            '/\b([0-9]{1,3}(?:,[0-9]{3})*\.[0-9]{2})\b/',
            '/\b([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?)\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $amount = $this->normalizeAmount($matches[1]);

                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function normalizeAmount(string $value): ?string
    {
        $normalizedValue = preg_replace_callback(
            '/(?=[0-9Oo.,]*\d)[0-9Oo]+(?:[.,][0-9Oo]+)*/',
            fn ($matches) => str_replace(['O', 'o'], '0', $matches[0]),
            $value,
        ) ?? $value;
        $normalizedValue = strtoupper($normalizedValue);

        if (preg_match('/(?:PHP|P|₱)\s*([0-9]{1,3}(?:[,\s][0-9]{3})*(?:\.[0-9]{1,2})?|[0-9]+(?:\.[0-9]{1,2})?)/i', $normalizedValue, $currencyMatches) === 1) {
            return $this->formatAmount($currencyMatches[1]);
        }

        if (preg_match_all('/(?<![:A-Z0-9])([0-9]{1,3}(?:,[0-9]{3})+\.[0-9]{1,2}|[0-9]{1,6}\.[0-9]{1,2})(?![A-Z0-9])/i', $normalizedValue, $decimalMatches) >= 1) {
            return $this->formatAmount(end($decimalMatches[1]) ?: '');
        }

        $numericCandidate = trim($normalizedValue);
        $numericCandidate = preg_replace('/^.*?\b(?:total\s+amount\s+sent|amount\s+sent|amount\s+paid|payment\s+amount|amount|total|you\s+sent|paid|send\s+money)\b\s*[:\-]?\s*/i', '', $numericCandidate) ?? $numericCandidate;

        if (preg_match('/^\s*(?:PHP|P|₱)?\s*([0-9][0-9,\s]*(?:\.[0-9]{1,2})?)\s*(?:PHP|P|₱)?\s*$/i', $numericCandidate, $matches) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $matches[1]) ?? '';

        // A long run of digits is a reference number, not an amount.
        if (strlen($digits) < 1 || strlen($digits) > 7) {
            return null;
        }

        return $this->formatAmount($matches[1]);
    }

    private function formatAmount(string $value): ?string
    {
        $amount = (float) preg_replace('/[^\d.]/', '', $value);

        if ($amount <= 0 || $amount >= 1000000) {
            return null;
        }

        return number_format($amount, 2, '.', '');
    }

    private function extractPhoneNumber(string $rawText, string $normalizedText): ?string
    {
        $labelPatterns = [
            '/(?:phone|mobile|cell(?:phone)?|contact|account)\s*(?:no\.?|number)?\s*[:#-]?\s*((?:\+?63|0)?\s*9(?:[\s.\-()]?\d){9})/i',
            '/(?:sent\s+to|to|recipient|receiver|received\s+by)\s+.*?((?:\+?63|0)?\s*9(?:[\s.\-()]?\d){9})/i',
        ];

        foreach (preg_split('/\n+/', $rawText) ?: [] as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            foreach ($labelPatterns as $pattern) {
                if (preg_match($pattern, $line, $matches) === 1) {
                    $phoneNumber = $this->normalizePhilippinePhoneNumber($matches[1]);

                    if ($phoneNumber !== null) {
                        return $phoneNumber;
                    }
                }
            }
        }

        foreach ($labelPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText, $matches) === 1) {
                $phoneNumber = $this->normalizePhilippinePhoneNumber($matches[1]);

                if ($phoneNumber !== null) {
                    return $phoneNumber;
                }
            }
        }

        if (preg_match('/(?<!\d)((?:\+?63|0)?\s*9(?:[\s.\-()]?\d){9})(?!\d)/', $normalizedText, $matches) === 1) {
            return $this->normalizePhilippinePhoneNumber($matches[1]);
        }

        return null;
    }

    private function normalizePhilippinePhoneNumber(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        return null;
    }

    private function extractPaymentDate(string $text, array $lines): ?string
    {
        $dateLabelPattern = '/(?:payment\s+date|transaction\s+date|date\s+and\s+time|date\/time|date\s*&\s*time|date)/i';

        foreach ($lines as $index => $line) {
            if (preg_match($dateLabelPattern, $line) !== 1 && ! $this->looksLikeDate($line)) {
                continue;
            }

            foreach ([$line, $lines[$index + 1] ?? '', $lines[$index + 2] ?? ''] as $candidateLine) {
                $date = $this->normalizePaymentDate($candidateLine);

                if ($date !== null) {
                    return $date;
                }
            }
        }

        if (preg_match('/\b('.$this->datePattern().')\b/i', $text, $matches) === 1) {
            return $this->normalizePaymentDate($matches[1]);
        }

        return null;
    }

    private function datePattern(): string
    {
        return '(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}(?:\s*,?\s*(?:at\s+)?\d{1,2}:\d{2}\s*(?:AM|PM))?|\d{1,2}\/\d{1,2}\/\d{2,4}(?:\s*,?\s*\d{1,2}:\d{2}\s*(?:AM|PM))?|\d{4}-\d{2}-\d{2}(?:[T\s]\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)?)';
    }

    private function normalizePaymentDate(string $value): ?string
    {
        if (preg_match('/\b('.$this->datePattern().')\b/i', $value, $matches) !== 1) {
            return null;
        }

        try {
            return now()->parse($matches[1])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function looksLikeAmount(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (preg_match('/(?:PHP|P|₱)\s*\d/i', $value) === 1) {
            return true;
        }

        // Bare integers can be reference fragments. Only treat currency-like
        // decimals as an amount when scanning a value after a reference label.
        return preg_match('/\b\d[\d,\s]*\.\d{1,2}\b/', $value) === 1;
    }

    private function looksLikeDate(string $value): bool
    {
        return $this->normalizePaymentDate($value) !== null;
    }

    private function buildNotes(string $text): ?string
    {
        $snippet = trim($text);

        if ($snippet === '') {
            return null;
        }

        return mb_substr($snippet, 0, 200);
    }
}
