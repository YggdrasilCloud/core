<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoFile;

final class FileNotFoundException extends \RuntimeException
{
    public function __construct(string $filePath)
    {
        parent::__construct(sprintf('File not found on disk: "%s"', $filePath));
    }
}
