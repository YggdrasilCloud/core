<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UserInterface\Http\Responder;

use App\Shared\UserInterface\Http\Responder\JsonResponder;
use App\Tests\Functional\JsonResponseTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversNothing
 */
final class JsonResponderTest extends TestCase
{
    use JsonResponseTestTrait;

    private JsonResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new JsonResponder();
    }

    public function testSuccessResponse(): void
    {
        $data = ['id' => '123', 'name' => 'Test'];
        $response = $this->responder->success($data);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(json_encode($data, JSON_THROW_ON_ERROR), $response->getContent());
    }

    public function testCreatedResponse(): void
    {
        $data = ['id' => '123'];
        $location = '/api/folders/123';
        $response = $this->responder->created($data, $location);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame($location, $response->headers->get('Location'));
        self::assertSame(json_encode($data, JSON_THROW_ON_ERROR), $response->getContent());
    }

    public function testErrorResponseWithoutDetail(): void
    {
        $response = $this->responder->error('Not Found', Response::HTTP_NOT_FOUND);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $this->decodeJsonResponse($response);
        self::assertSame('about:blank', $content['type']);
        self::assertSame('Not Found', $content['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $content['status']);
        self::assertArrayNotHasKey('detail', $content);
    }

    public function testErrorResponseWithDetail(): void
    {
        $response = $this->responder->error('Not Found', Response::HTTP_NOT_FOUND, 'Folder ID 123 does not exist');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $this->decodeJsonResponse($response);
        self::assertSame('about:blank', $content['type']);
        self::assertSame('Not Found', $content['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $content['status']);
        self::assertSame('Folder ID 123 does not exist', $content['detail']);
    }

    public function testNotFoundResponse(): void
    {
        $response = $this->responder->notFound('Resource not found', 'The requested resource was not found');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $this->decodeJsonResponse($response);
        self::assertSame('Resource not found', $content['title']);
        self::assertSame('The requested resource was not found', $content['detail']);
    }

    public function testBadRequestResponse(): void
    {
        $response = $this->responder->badRequest('Invalid input', 'Name field is required');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $this->decodeJsonResponse($response);
        self::assertSame('Invalid input', $content['title']);
        self::assertSame('Name field is required', $content['detail']);
    }

    public function testServerErrorResponse(): void
    {
        $response = $this->responder->serverError();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $this->decodeJsonResponse($response);
        self::assertSame('Internal server error', $content['title']);
    }
}
