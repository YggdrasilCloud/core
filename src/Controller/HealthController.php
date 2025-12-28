<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return new JsonResponse([
            'message' => 'Welcome to YggdrasilCloud API',
            'status' => 'ok',
            'timestamp' => time(),
        ]);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): Response
    {
        return new JsonResponse([
            'status' => 'healthy',
            'service' => 'yggdrasilcloud-api',
            'timestamp' => time(),
        ]);
    }
}
