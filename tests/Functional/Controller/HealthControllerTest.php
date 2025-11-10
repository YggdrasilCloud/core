<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    public function testHomeReturns200WithWelcomeMessage(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('message', $data);
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('timestamp', $data);

        self::assertSame('Welcome to YggdrasilCloud API', $data['message']);
        self::assertSame('ok', $data['status']);
        self::assertIsInt($data['timestamp']);
        self::assertGreaterThan(0, $data['timestamp']);
    }

    public function testHealthReturns200WithHealthyStatus(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('service', $data);
        self::assertArrayHasKey('timestamp', $data);

        self::assertSame('healthy', $data['status']);
        self::assertSame('yggdrasilcloud-api', $data['service']);
        self::assertIsInt($data['timestamp']);
        self::assertGreaterThan(0, $data['timestamp']);
    }

    public function testHealthEndpointAcceptsOnlyGetMethod(): void
    {
        $client = self::createClient();

        $client->request('POST', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testHomeEndpointAcceptsOnlyGetMethod(): void
    {
        $client = self::createClient();

        $client->request('POST', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
