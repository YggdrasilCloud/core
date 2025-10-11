<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use Symfony\Component\HttpFoundation\Request;

final readonly class PaginationParams
{
    private function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * Extract and normalize pagination parameters from HTTP request.
     *
     * @param int $maxPerPage Maximum allowed items per page (default: 100)
     * @param int $defaultPerPage Default items per page if not specified (default: 20)
     */
    public static function fromRequest(Request $request, int $maxPerPage = 100, int $defaultPerPage = 20): self
    {
        // Ensure page is at least 1
        $page = max(1, (int) $request->query->get('page', 1));

        // Ensure perPage is between 1 and maxPerPage
        $perPage = min($maxPerPage, max(1, (int) $request->query->get('perPage', $defaultPerPage)));

        return new self($page, $perPage);
    }
}
