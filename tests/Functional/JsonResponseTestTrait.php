<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

/**
 * Trait to help with JSON response decoding in functional tests.
 */
trait JsonResponseTestTrait
{
    /**
     * Decode JSON response content with proper type assertions.
     *
     * @return array<mixed>
     */
    protected function decodeJsonResponse(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        $data = json_decode($content, true);
        self::assertIsArray($data);

        return $data;
    }
}
