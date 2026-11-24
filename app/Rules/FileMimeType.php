<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileMimeType implements ValidationRule
{
    protected array $allowedMimes;

    public function __construct(string|array $mimes)
    {
        $this->allowedMimes = is_array($mimes) ? $mimes : explode(',', $mimes);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $extensionMimeMap = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'bmp' => ['image/bmp'],
            'webp' => ['image/webp'],
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime', 'video/mp4'],
            'avi' => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
            'webm' => ['video/webm'],
            'pdf' => ['application/pdf'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'txt' => ['text/plain'],
        ];

        $expectedTypes = [];
        foreach ($this->allowedMimes as $mime) {
            if (isset($extensionMimeMap[$mime])) {
                $expectedTypes = array_merge($expectedTypes, $extensionMimeMap[$mime]);
            } else {
                $expectedTypes[] = $mime;
            }
        }

        $actualMime = null;
        if (function_exists('finfo_open')) {
            $finfo = new \finfo(\FILEINFO_MIME_TYPE);
            $actualMime = $finfo->file($value->getPathname());
        }

        if ($actualMime === null || $actualMime === false) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if ($actualMime === 'application/octet-stream' && in_array($extension, ['mp4', 'mov', 'avi', 'webm'], true)) {
            return;
        }

        if ($actualMime === 'application/octet-stream' && in_array($value->getClientMimeType(), $expectedTypes, true)) {
            return;
        }

        if (! in_array($actualMime, $expectedTypes)) {
            $fail('The :attribute must be a file of type: ' . implode(', ', $this->allowedMimes) . '.');
        }
    }
}
