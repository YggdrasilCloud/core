<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Service\ThumbnailGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetThumbnailInfoController
{
    public function __construct(
        private ThumbnailGenerator $thumbnailGenerator,
    ) {}

    #[Route('/api/thumbnails/info', name: 'get_thumbnail_info', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new JsonResponse([
            'method' => $this->thumbnailGenerator->getMethod(),
            'status' => 'operational',
        ]);
    }
}
