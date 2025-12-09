<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for Tus resumable upload controller.
 *
 * Note: The Tus protocol implementation relies on tus-php library which
 * interacts directly with $_SERVER globals and file system. Full protocol
 * testing is done via E2E tests. These functional tests verify:
 * - Route is correctly configured and accessible
 * - CORS headers are properly set
 * - Tus-Resumable header is returned
 *
 * @see https://tus.io/protocols/resumable-upload.html
 */
final class TusUploadControllerTest extends WebTestCase
{
    public function testTusEndpointRespondsWithTusHeaders(): void
    {
        $client = self::createClient();

        // Send a POST request to the Tus endpoint
        // Note: tus-php library handles internally and returns 404 for invalid requests
        // but should still include Tus headers
        $client->request(
            'POST',
            '/api/uploads/tus',
            [],
            [],
            [
                'HTTP_TUS_RESUMABLE' => '1.0.0',
                'HTTP_UPLOAD_LENGTH' => '100',
            ]
        );

        // The response includes Tus headers even on error
        $headers = $client->getResponse()->headers;
        self::assertSame('1.0.0', $headers->get('Tus-Resumable'));
    }

    public function testTusEndpointStillRespondsWithoutTusResumableHeader(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/uploads/tus',
            [],
            [],
            [
                'HTTP_UPLOAD_LENGTH' => '100',
            ]
        );

        // tus-php library returns Tus-Resumable header even when the request
        // doesn't include it (to inform client of supported version)
        $headers = $client->getResponse()->headers;
        self::assertSame('1.0.0', $headers->get('Tus-Resumable'));
    }

    public function testTusEndpointIncludesCorsHeaders(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/uploads/tus',
            [],
            [],
            [
                'HTTP_TUS_RESUMABLE' => '1.0.0',
            ]
        );

        $headers = $client->getResponse()->headers;

        // Verify CORS-related headers from tus-php are present
        self::assertNotNull($headers->get('Access-Control-Allow-Methods'));
        self::assertNotNull($headers->get('Access-Control-Expose-Headers'));
    }

    public function testOptionsReturnsCorrectMethods(): void
    {
        $client = self::createClient();

        $client->request(
            'OPTIONS',
            '/api/uploads/tus',
            [],
            [],
            [
                'HTTP_TUS_RESUMABLE' => '1.0.0',
            ]
        );

        $headers = $client->getResponse()->headers;

        // tus-php sets these headers on OPTIONS
        $allowMethods = $headers->get('Access-Control-Allow-Methods');
        self::assertNotNull($allowMethods);
        self::assertStringContainsString('POST', $allowMethods);
        self::assertStringContainsString('PATCH', $allowMethods);
        self::assertStringContainsString('HEAD', $allowMethods);
        self::assertStringContainsString('DELETE', $allowMethods);
    }
}
