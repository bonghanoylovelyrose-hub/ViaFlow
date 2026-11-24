<?php

namespace App\Mime;

use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class FinfoMimeTypeGuesser implements MimeTypeGuesserInterface
{
    private static $finfoCache = [];

    public function __construct(
        private ?string $magicFile = null,
    ) {
    }

    public function isGuesserSupported(): bool
    {
        return \function_exists('finfo_open');
    }

    public function guessMimeType(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        if (!$this->isGuesserSupported()) {
            return null;
        }

        try {
            $finfo = self::$finfoCache[$this->magicFile ?? ''] ??= new \finfo(\FILEINFO_MIME_TYPE, $this->magicFile);
        } catch (\Exception $e) {
            return null;
        }

        $mimeType = $finfo->file($path);

        return $mimeType ?: null;
    }
}
