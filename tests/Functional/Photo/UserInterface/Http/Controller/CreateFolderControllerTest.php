<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CreateFolderControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    public function testCreateFolderReturns201WithCreatedFolder(): void
    {
        $client = self::createClient();

        $ownerId = '550e8400-e29b-41d4-a716-446655440000';
        $folderName = 'Test Folder';

        $client->request(
            'POST',
            '/api/folders',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => $folderName,
                'ownerId' => $ownerId,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
        self::assertSame($folderName, $data['name']);

        // Verify Location header contains the folder ID
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString('/api/folders/', $location);
        self::assertStringContainsString($data['id'], $location);
    }

    public function testCreateFolderWithParentReturns201(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create parent folder
        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);

        $client->request(
            'POST',
            '/api/folders',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Child Folder',
                'ownerId' => $ownerId->toString(),
                'parentId' => $parentId->toString(),
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('id', $data);
        self::assertSame('Child Folder', $data['name']);
    }
}
