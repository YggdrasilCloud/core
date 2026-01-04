<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request DTO for folder deletion.
 */
final readonly class DeleteFolderRequest
{
    public function __construct(
        public bool $recursive,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $recursive = $request->query->getBoolean('recursive', false);

        return new self($recursive);
    }
}
