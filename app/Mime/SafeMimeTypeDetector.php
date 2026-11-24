<?php

declare(strict_types=1);

namespace App\Mime;

use League\MimeTypeDetection\ExtensionLookup;
use League\MimeTypeDetection\ExtensionToMimeTypeMap;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use League\MimeTypeDetection\MimeTypeDetector;

class SafeMimeTypeDetector implements MimeTypeDetector, ExtensionLookup
{
    private $finfo;
    private ExtensionToMimeTypeMap $extensionMap;

    public function __construct(
        string $magicFile = '',
        ?ExtensionToMimeTypeMap $extensionMap = null,
    ) {
        if (class_exists('finfo')) {
            $this->finfo = new \finfo(\FILEINFO_MIME_TYPE, $magicFile ?: null);
        }
        $this->extensionMap = $extensionMap ?: new GeneratedExtensionToMimeTypeMap();
    }

    public function detectMimeType(string $path, $contents): ?string
    {
        $mimeType = null;
        if ($this->finfo !== null && is_string($contents)) {
            $mimeType = @$this->finfo->buffer($contents) ?: null;
        }

        if ($mimeType !== null && !in_array($mimeType, $this->inconclusiveTypes())) {
            return $mimeType;
        }

        return $this->detectMimeTypeFromPath($path);
    }

    public function detectMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        return $this->extensionMap->lookupMimeType($extension);
    }

    public function detectMimeTypeFromFile(string $path): ?string
    {
        if ($this->finfo !== null) {
            return @$this->finfo->file($path) ?: null;
        }
        return $this->detectMimeTypeFromPath($path);
    }

    public function detectMimeTypeFromBuffer(string $contents): ?string
    {
        if ($this->finfo !== null) {
            return @$this->finfo->buffer($contents) ?: null;
        }
        return null;
    }

    public function lookupExtension(string $mimetype): ?string
    {
        return $this->extensionMap instanceof ExtensionLookup
            ? $this->extensionMap->lookupExtension($mimetype)
            : null;
    }

    public function lookupAllExtensions(string $mimetype): array
    {
        return $this->extensionMap instanceof ExtensionLookup
            ? $this->extensionMap->lookupAllExtensions($mimetype)
            : [];
    }

    private function inconclusiveTypes(): array
    {
        return [
            'application/x-empty',
            'text/plain',
            'text/x-asm',
            'application/octet-stream',
            'inode/x-empty',
        ];
    }
}
