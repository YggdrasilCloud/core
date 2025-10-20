<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Responder;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class JsonResponder
{
    /**
     * Create a successful JSON response with data.
     */
    public function success(mixed $data, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Create a created response (201) with data and Location header.
     */
    public function created(mixed $data, string $location): JsonResponse
    {
        $response = new JsonResponse($data, Response::HTTP_CREATED);
        $response->headers->set('Location', $location);

        return $response;
    }

    /**
     * Create an error response with RFC 7807 Problem Details structure.
     */
    public function error(string $title, int $status, ?string $detail = null, array $additional = []): JsonResponse
    {
        $problem = [
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== null) {
            $problem['detail'] = $detail;
        }

        $response = new JsonResponse(array_merge($problem, $additional), $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }

    /**
     * Create a bad request error response (400).
     */
    public function badRequest(string $message, ?string $detail = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_BAD_REQUEST, $detail);
    }

    /**
     * Create a not found error response (404).
     */
    public function notFound(string $message, ?string $detail = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND, $detail);
    }

    /**
     * Create an internal server error response (500).
     */
    public function serverError(string $message = 'Internal server error', ?string $detail = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR, $detail);
    }
}
