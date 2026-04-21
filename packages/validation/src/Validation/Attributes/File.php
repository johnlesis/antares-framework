<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;
use Psr\Http\Message\UploadedFileInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class File
{
    public function __construct(
        public readonly int $maxSize = 0,
        public readonly array $mimes = [],
        public readonly array $extensions = [],
        public readonly bool $required = true,
    ) {}

    public function validate(UploadedFileInterface $file): ?string
    {
        if ($this->required && $file->getError() === UPLOAD_ERR_NO_FILE) {
            return "File is required.";
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return "File upload failed.";
        }

        if ($this->maxSize > 0 && $file->getSize() > $this->maxSize * 1024) {
            return "File size must not exceed {$this->maxSize}KB.";
        }

        if (!empty($this->mimes)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $stream = $file->getStream();
            $stream->rewind();
            $mime = $finfo->buffer($stream->getContents());
            if (!in_array($mime, $this->mimes, strict: true)) {
                $allowed = implode(', ', $this->mimes);
                return "File must be one of: {$allowed}.";
            }
        }

        if (!empty($this->extensions)) {
            $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $this->extensions, strict: true)) {
                $allowed = implode(', ', $this->extensions);
                return "File extension must be one of: {$allowed}.";
            }
        }

        return null;
    }
}