<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class LoanIncomeOcrService
{
    public function extract(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $this->unreadable('Unsupported image format. Upload a JPG or PNG proof of income.');
        }

        $directory = base_path('tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.DIRECTORY_SEPARATOR.'loan-income-'.bin2hex(random_bytes(12)).'.'.$extension;

        try {
            if (! copy($file->getRealPath(), $path)) {
                return $this->unreadable('The uploaded image could not be prepared for OCR.');
            }

            $process = new Process([
                'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
                '-NoProfile', '-NoLogo', '-NonInteractive', '-Sta', '-ExecutionPolicy', 'Bypass',
                '-File', base_path('scripts/ocr-receipt.ps1'), $path,
            ], base_path(), $this->windowsEnvironment());
            $process->setTimeout(60);
            $process->run();

            $payload = json_decode($process->getOutput(), true);
            if (! $process->isSuccessful() || ! is_array($payload) || ! ($payload['ok'] ?? false)) {
                return $this->unreadable('OCR could not read this image. Staff can inspect the original proof and enter verified details.');
            }

            return $this->parseText((string) ($payload['text'] ?? ''));
        } catch (\Throwable) {
            return $this->unreadable('OCR is unavailable on this server. Staff review is required.');
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function parseText(string $text): array
    {
        $text = trim(preg_replace('/[\t ]+/', ' ', $text) ?? '');
        $lines = preg_split('/\R/u', $text) ?: [];
        $name = null;
        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:employee\s+name|applicant\s+name|full\s+name|employee|applicant|name)\s*[:\-]\s*(.{2,100}?)\s*$/iu', $line, $matches)) {
                $candidate = trim($matches[1], " \t:;,-");
                if ($candidate !== '' && ! preg_match('/^(?:employer|company|business)\b/i', $candidate)) {
                    $name = $candidate;
                    break;
                }
            }
        }

        $incomeCandidates = [];
        foreach ($lines as $line) {
            if (preg_match('/(?:monthly\s+income|gross\s+(?:monthly\s+)?(?:salary|pay|income)|net\s+(?:salary|pay)|basic\s+salary|salary|income|earnings|wages)(?:\s+amount)?\s*[:\-]?\s*(?:PHP|₱|P)?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/iu', $line, $matches)) {
                $incomeCandidates[] = round((float) str_replace(',', '', $matches[1]), 2);
            }
        }
        $incomeCandidates = array_values(array_unique($incomeCandidates));
        $amount = count($incomeCandidates) === 1 ? $incomeCandidates[0] : null;

        $period = $this->payPeriod($text);
        $monthlyAmount = $amount === null || $period === null
            ? null
            : match ($period) {
                'monthly' => $amount,
                'annual' => round($amount / 12, 2),
                'weekly' => round($amount * 52 / 12, 2),
                'biweekly' => round($amount * 26 / 12, 2),
                'semi_monthly' => round($amount * 2, 2),
            };

        $missing = [];
        if ($name === null) {
            $missing[] = 'Applicant name';
        }
        if ($amount === null) {
            $missing[] = count($incomeCandidates) > 1 ? 'One unambiguous income amount' : 'Income amount';
        }
        if ($period === null) {
            $missing[] = 'Clear pay period';
        }
        $readability = (int) round(((3 - count($missing)) / 3) * 100);

        return [
            'ocr_status' => $text === '' ? 'Unreadable' : ($missing === [] ? 'Readable' : 'Incomplete'),
            'extracted_name' => $name,
            'extracted_income_amount' => $amount,
            'extracted_pay_period' => $period,
            'extracted_monthly_income' => $monthlyAmount,
            'income_amount_candidates' => $incomeCandidates,
            'missing_fields' => $missing,
            'ocr_confidence' => $readability,
            'confidence_basis' => 'Readability field extraction only; this score does not verify document authenticity or income source.',
        ];
    }

    public function compare(array $extracted, string $applicantName, float $declaredMonthlyIncome): array
    {
        $normalize = fn (?string $value): string => preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value))) ?? '';
        $nameMatch = filled($extracted['extracted_name'] ?? null)
            ? $normalize($extracted['extracted_name']) === $normalize($applicantName)
            : null;
        $income = $extracted['extracted_monthly_income'] ?? null;
        $incomeMatch = is_numeric($income)
            ? round((float) $income, 2) === round($declaredMonthlyIncome, 2)
            : null;

        return [
            ...$extracted,
            'applicant_name_match' => $nameMatch,
            'declared_monthly_income' => round($declaredMonthlyIncome, 2),
            'declared_income_match' => $incomeMatch,
            'comparison_status' => $nameMatch === true && $incomeMatch === true ? 'Matched' : 'Needs Review',
        ];
    }

    private function payPeriod(string $text): ?string
    {
        $matches = [];
        if (preg_match('/\b(?:semi[\s-]?monthly|twice\s+(?:a|per)\s+month)\b/iu', $text)) {
            $matches[] = 'semi_monthly';
            $text = preg_replace('/\b(?:semi[\s-]?monthly|twice\s+(?:a|per)\s+month)\b/iu', ' ', $text) ?? $text;
        }
        foreach ([
            'semi_monthly' => '/\b(?:semi[\s-]?monthly|twice\s+(?:a|per)\s+month)\b/iu',
            'biweekly' => '/\b(?:bi[\s-]?weekly|every\s+two\s+weeks|fortnightly)\b/iu',
            'weekly' => '/\b(?:weekly|per\s+week|each\s+week)\b/iu',
            'annual' => '/\b(?:annual(?:ly)?|yearly|per\s+annum)\b/iu',
            'monthly' => '/\b(?:monthly|per\s+month|each\s+month)\b/iu',
        ] as $period => $pattern) {
            if (preg_match($pattern, $text)) {
                $matches[] = $period;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function unreadable(string $message): array
    {
        return [
            'ocr_status' => 'Unreadable',
            'extracted_name' => null,
            'extracted_income_amount' => null,
            'extracted_pay_period' => null,
            'extracted_monthly_income' => null,
            'income_amount_candidates' => [],
            'missing_fields' => ['Applicant name', 'Income amount', 'Clear pay period'],
            'ocr_confidence' => 0,
            'confidence_basis' => 'Readability field extraction only; this score does not verify document authenticity or income source.',
            'message' => $message,
            'applicant_name_match' => null,
            'declared_income_match' => null,
            'comparison_status' => 'Needs Review',
        ];
    }

    private function windowsEnvironment(): array
    {
        return array_filter([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            'USERPROFILE' => getenv('USERPROFILE') ?: null,
            'APPDATA' => getenv('APPDATA') ?: null,
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: null,
            'Path' => getenv('Path') ?: null,
            'PATHEXT' => getenv('PATHEXT') ?: null,
            'PSModulePath' => getenv('PSModulePath') ?: null,
            'COMSPEC' => getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe',
        ], fn ($value): bool => filled($value));
    }
}
