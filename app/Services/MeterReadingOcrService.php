<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class MeterReadingOcrService
{
    public function extract(UploadedFile $file): array
    {
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)) {
            return [
                'ok' => false,
                'message' => 'Auto-reading is currently supported for JPG and PNG meter photos only.',
                'text' => '',
                'fields' => [],
            ];
        }

        $tempDirectory = base_path('tmp');
        File::ensureDirectoryExists($tempDirectory);

        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.'meter-ocr.'.$file->getClientOriginalExtension();
        File::delete($tempPath);
        File::copy($file->getRealPath(), $tempPath);

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
            base_path('scripts/ocr-meter.ps1'),
            $tempPath,
        ], base_path(), $environment);

        $process->setTimeout(45);
        $process->run();

        try {
            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'Meter OCR failed: '.$error
                        : 'Meter OCR could not be completed on this machine.',
                    'text' => '',
                    'fields' => [],
                ];
            }

            $payload = json_decode($process->getOutput(), true);
        } finally {
            File::delete($tempPath);
        }

        if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $payload['message'] ?? 'No readable text was found in the uploaded meter photo.',
                'text' => $payload['text'] ?? '',
                'fields' => [],
            ];
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $reading = $this->extractReading($payload);

        if ($reading === null) {
            return [
                'ok' => false,
                'message' => 'The meter digits could not be detected clearly. Upload a closer, brighter photo of the meter display.',
                'text' => $text,
                'fields' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Meter reading detected.',
            'text' => $text,
            'fields' => [
                'current_reading' => $reading,
                'notes' => $this->buildNotes(trim(((string) ($payload['register_text'] ?? '')).' '.((string) ($payload['text'] ?? '')))),
            ],
        ];
    }

    private function extractReading(array $payload): ?string
    {
        $hasRegisterEvidence = false;
        $odometerCandidates = [];
        $registerSlotCandidates = is_array($payload['register_slot_candidates'] ?? null)
            ? $payload['register_slot_candidates']
            : [];

        foreach ($registerSlotCandidates as $slotCandidatePayload) {
            if (! is_array($slotCandidatePayload)) {
                continue;
            }

            $hasRegisterEvidence = true;

            $assembled = $this->assembleReadingFromSlots($slotCandidatePayload['slots'] ?? []);

            if ($assembled !== null) {
                $odometerCandidates[] = $this->buildOdometerCandidate(
                    $assembled,
                    $slotCandidatePayload['slots'] ?? [],
                    true
                );
            }

            $registerText = trim((string) ($slotCandidatePayload['text'] ?? ''));
            $merged = $this->mergeSlotsWithRegisterText($slotCandidatePayload['slots'] ?? [], $registerText);

            if ($merged !== null) {
                $odometerCandidates[] = $this->buildOdometerCandidate(
                    $merged,
                    $slotCandidatePayload['slots'] ?? [],
                    $this->extractInlineRegisterDigits($registerText) !== null
                );
            }
        }

        $registerCandidates = is_array($payload['register_candidates'] ?? null) ? $payload['register_candidates'] : [];

        foreach ($registerCandidates as $candidatePayload) {
            if (! is_array($candidatePayload)) {
                continue;
            }

            $registerText = trim((string) ($candidatePayload['text'] ?? ''));

            if ($registerText === '') {
                continue;
            }

            $hasRegisterEvidence = true;

            $digitRuns = $this->extractRegisterCandidates($registerText);

            if ($digitRuns !== []) {
                foreach ($digitRuns as $digitRun) {
                    $odometerCandidates[] = $this->buildOdometerCandidate($digitRun, [], true);
                }
            }
        }

        $registerText = trim((string) ($payload['register_text'] ?? ''));

        if ($registerText !== '') {
            $hasRegisterEvidence = true;
            $registerCandidates = $this->extractRegisterCandidates($registerText);

            if ($registerCandidates !== []) {
                foreach ($registerCandidates as $registerCandidate) {
                    $odometerCandidates[] = $this->buildOdometerCandidate($registerCandidate, [], true);
                }
            }
        }

        if ($odometerCandidates !== []) {
            usort($odometerCandidates, function (array $left, array $right): int {
                return $right['score'] <=> $left['score']
                    ?: strcmp($left['raw'], $right['raw']);
            });

            return $odometerCandidates[0]['value'];
        }

        if ($hasRegisterEvidence) {
            return null;
        }

        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $scoredCandidates = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $width = (float) ($line['width'] ?? 0);
            $height = max((float) ($line['height'] ?? 0), 1.0);
            $ratio = $width / $height;
            $top = (float) ($line['top'] ?? 0);
            $segmentCandidates = $this->extractCandidatesFromSegment((string) ($line['text'] ?? ''));

            foreach ($segmentCandidates as $candidate) {
                $score = 0;

                if ($ratio >= 2.6) {
                    $score += 120;
                } elseif ($ratio >= 1.8) {
                    $score += 60;
                }

                if (strlen($candidate) >= 5) {
                    $score += 30;
                }

                if ($top >= 0 && $top <= 450) {
                    $score += 15;
                }

                $score += min($width, 500) / 10;

                $scoredCandidates[] = [
                    'value' => $candidate,
                    'score' => $score,
                ];
            }

            foreach (($line['words'] ?? []) as $word) {
                if (! is_array($word)) {
                    continue;
                }

                $wordWidth = (float) ($word['width'] ?? 0);
                $wordHeight = max((float) ($word['height'] ?? 0), 1.0);
                $wordRatio = $wordWidth / $wordHeight;

                foreach ($this->extractCandidatesFromSegment((string) ($word['text'] ?? '')) as $candidate) {
                    $score = 0;

                    if ($wordRatio >= 2.2) {
                        $score += 110;
                    } elseif ($wordRatio >= 1.6) {
                        $score += 45;
                    }

                    if (strlen($candidate) >= 5) {
                        $score += 25;
                    }

                    $score += min($wordWidth, 350) / 10;

                    $scoredCandidates[] = [
                        'value' => $candidate,
                        'score' => $score,
                    ];
                }
            }
        }

        if ($scoredCandidates === []) {
            $text = trim((string) ($payload['text'] ?? ''));

            if ($text === '') {
                return null;
            }

            foreach (preg_split('/\R+/', $text) ?: [] as $line) {
                foreach ($this->extractCandidatesFromSegment((string) $line) as $candidate) {
                    $scoredCandidates[] = [
                        'value' => $candidate,
                        'score' => strlen($candidate) * 10,
                    ];
                }
            }
        }

        if ($scoredCandidates === []) {
            return null;
        }

        usort($scoredCandidates, function (array $left, array $right): int {
            return $right['score'] <=> $left['score']
                ?: strlen($right['value']) <=> strlen($left['value'])
                ?: ((int) $right['value'] <=> (int) $left['value']);
        });

        return $scoredCandidates[0]['value'];
    }

    private function assembleReadingFromSlots(array $slots): ?string
    {
        if ($slots === []) {
            return null;
        }

        usort($slots, fn (array $left, array $right): int => ((int) ($left['index'] ?? 0)) <=> ((int) ($right['index'] ?? 0)));

        $digits = '';

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $digit = $this->extractSingleDigit((string) ($slot['text'] ?? ''));

            if ($digit === null) {
                continue;
            }

            $digits .= $digit;
        }

        if (strlen($digits) < 4 || strlen($digits) > 6) {
            return null;
        }

        if (strlen($digits) < 6) {
            $digits = str_pad($digits, 6, '0', STR_PAD_LEFT);
        }

        $trimmed = ltrim(substr($digits, 0, 6), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function extractSingleDigit(string $text): ?string
    {
        $normalized = strtoupper(trim($text));

        if ($normalized === '') {
            return null;
        }

        $normalized = strtr($normalized, [
            'O' => '0',
            'Q' => '0',
            'D' => '0',
            'I' => '1',
            'L' => '1',
            'Z' => '2',
            'S' => '5',
            'B' => '8',
            'G' => '6',
        ]);

        if (preg_match('/[0-9]/', $normalized, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function mergeSlotsWithRegisterText(array $slots, string $registerText): ?string
    {
        $registerDigits = $this->extractInlineRegisterDigits($registerText);

        if ($registerDigits === null) {
            return null;
        }

        $slotDigits = [];

        foreach ($slots as $slot) {
            $slotDigits[] = is_array($slot)
                ? $this->extractSingleDigit((string) ($slot['text'] ?? ''))
                : null;
        }

        if (count($slotDigits) === 0) {
            return null;
        }

        $slotDigits = array_slice(array_pad($slotDigits, 6, null), 0, 6);
        $registerChars = array_slice(str_split(str_pad($registerDigits, 6, '0', STR_PAD_LEFT)), 0, 6);

        foreach ($slotDigits as $index => $digit) {
            if ($digit === null && array_key_exists($index, $registerChars)) {
                $slotDigits[$index] = $registerChars[$index];
            }
        }

        $merged = implode('', array_filter($slotDigits, fn ($digit) => $digit !== null));

        if (strlen($merged) < 4 || strlen($merged) > 6) {
            return null;
        }

        if (strlen($merged) < 6) {
            $merged = str_pad($merged, 6, '0', STR_PAD_LEFT);
        }

        $trimmed = ltrim(substr($merged, 0, 6), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function extractInlineRegisterDigits(string $text): ?string
    {
        $normalized = $this->normalizeOdometerText($text);

        if ($normalized === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';

        if (strlen($digits) < 4) {
            return null;
        }

        if (strlen($digits) > 6) {
            $digits = substr($digits, 0, 6);
        }

        return $digits;
    }

    private function extractRegisterCandidates(string $segment): array
    {
        $normalized = $this->normalizeOdometerText($segment);

        preg_match_all('/[0-9]{4,6}/', preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '', $matches);

        return array_values(array_unique(array_map(function (string $digits): string {
            return strlen($digits) < 6
                ? str_pad($digits, 6, '0', STR_PAD_LEFT)
                : substr($digits, 0, 6);
        }, $matches[0] ?? [])));
    }

    private function extractCandidatesFromSegment(string $segment): array
    {
        preg_match_all('/[A-Z0-9\s\-]{4,18}/i', strtoupper($segment), $matches);
        $candidates = [];

        foreach ($matches[0] ?? [] as $match) {
            $sanitized = strtr($match, [
                'O' => '0',
                'Q' => '0',
                'D' => '0',
                'I' => '1',
                'L' => '1',
                'Z' => '2',
                'S' => '5',
                'B' => '8',
                'G' => '6',
            ]);

            $digits = preg_replace('/\D/', '', $sanitized) ?? '';

            if (strlen($digits) >= 4 && strlen($digits) <= 8) {
                $candidates[] = ltrim($digits, '0') === '' ? '0' : ltrim($digits, '0');
            }
        }

        return array_values(array_unique($candidates));
    }

    private function buildNotes(string $text): ?string
    {
        $snippet = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return $snippet === '' ? null : mb_substr($snippet, 0, 200);
    }

    private function normalizeOdometerText(string $text): string
    {
        $normalized = strtoupper(trim($text));

        return strtr($normalized, [
            'O' => '0',
            'Q' => '0',
            'D' => '0',
            'I' => '1',
            'L' => '1',
            'Z' => '2',
            'S' => '5',
            'B' => '8',
            'G' => '6',
        ]);
    }

    private function buildOdometerCandidate(string $rawDigits, array $slots, bool $hasRegisterText): array
    {
        $normalized = preg_replace('/\D/', '', $rawDigits) ?? '';
        $normalized = substr(str_pad($normalized, 6, '0', STR_PAD_LEFT), 0, 6);
        $recognizedSlots = 0;

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            if ($this->extractSingleDigit((string) ($slot['text'] ?? '')) !== null) {
                $recognizedSlots++;
            }
        }

        $trimmed = ltrim($normalized, '0');
        $score = 0;
        $score += $recognizedSlots * 20;
        $score += $hasRegisterText ? 30 : 0;
        $score += strlen($trimmed === '' ? '0' : $trimmed) * 8;
        $score += preg_match('/^0{2,}/', $normalized) === 1 ? 15 : 0;
        $score += strlen($normalized) === 6 ? 20 : 0;

        return [
            'raw' => $normalized,
            'value' => $trimmed === '' ? '0' : $trimmed,
            'score' => $score,
        ];
    }
}
