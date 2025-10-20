<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GetFolderPathControllerTest extends WebTestCase
{
    public function testGetFolderPathReturns200WithFullPath(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create hierarchy: root -> child -> grandchild
        $rootId = FolderId::generate();
        $root = Folder::create($rootId, FolderName::fromString('Root'), $ownerId);

        $childId = FolderId::generate();
        $child = Folder::create($childId, FolderName::fromString('Child'), $ownerId, $rootId);

        $grandchildId = FolderId::generate();
        $grandchild = Folder::create($grandchildId, FolderName::fromString('Grandchild'), $ownerId, $childId);

        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($root);
        $folderRepo->save($child);
        $folderRepo->save($grandchild);

        $client->request('GET', '/api/folders/'.$grandchildId->toString().'/path');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('path', $data);
        self::assertCount(3, $data['path']);

        // Verify path is from root to target
        self::assertSame('Root', $data['path'][0]['name']);
        self::assertSame($rootId->toString(), $data['path'][0]['id']);

        self::assertSame('Child', $data['path'][1]['name']);
        self::assertSame($childId->toString(), $data['path'][1]['id']);

        self::assertSame('Grandchild', $data['path'][2]['name']);
        self::assertSame($grandchildId->toString(), $data['path'][2]['id']);
    }

    public function testGetFolderPathReturnsRootFolderOnly(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create root folder (no parent)
        $rootId = FolderId::generate();
        $root = Folder::create($rootId, FolderName::fromString('Root'), $ownerId);

        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($root);

        $client->request('GET', '/api/folders/'.$rootId->toString().'/path');

        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('path', $data);
        self::assertCount(1, $data['path']);
        self::assertSame('Root', $data['path'][0]['name']);
        self::assertSame($rootId->toString(), $data['path'][0]['id']);
    }

    public function testGetFolderPathReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = FolderId::generate();
        $client->request('GET', '/api/folders/'.$nonExistentId->toString().'/path');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('status', $data);
        self::assertSame(404, $data['status']);
        self::assertArrayHasKey('title', $data);
        self::assertSame('Not Found', $data['title']);
    }
}
