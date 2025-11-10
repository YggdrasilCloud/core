<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class UploadPhotoRequest
{
    private function __construct(
        public UploadedFile $file,
        public string $ownerId,
    ) {}

    /**
     * Extract and validate upload request data.
     *
     * @throws InvalidArgumentException if required fields are missing
     */
    public static function fromRequest(Request $request): self
    {
        /** @var null|UploadedFile $file */
        $file = $request->files->get('photo');

        if ($file === null) {
            throw new InvalidArgumentException('Missing required file: photo');
        }

        $ownerId = $request->request->get('ownerId');

        if (!is_string($ownerId) || $ownerId === '') {
            throw new InvalidArgumentException('Missing required field: ownerId');
        }

        return new self($file, $ownerId);
    }
}
