<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReadableIncomeProof implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) return;
        $path = $value->getPathname();
        $ext = strtolower($value->getClientOriginalExtension());
        if ($ext === 'pdf') {
            $file = @fopen($path, 'rb');
            if (! $file) { $fail('The proof of income could not be read.'); return; }
            $header = fread($file, 8);
            fseek($file, max(0, filesize($path) - 2048));
            $tail = stream_get_contents($file);
            fclose($file);
            if (! str_starts_with($header, '%PDF-') || ! str_contains($tail, '%%EOF') || ! str_contains($tail, 'startxref')) {
                $fail('The PDF is incomplete or unreadable. Please choose a valid PDF.');
            }
            return;
        }
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) return;
        $info = @getimagesize($path);
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 25000000) {
            $fail('The image is unreadable or exceeds the supported image dimensions.');
            return;
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => false,
        };
        if (! $image) { $fail('The image is unreadable or corrupted. Please choose another file.'); return; }
        imagedestroy($image);
    }
}
