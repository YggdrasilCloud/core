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
final class GetFolderChildrenControllerTest extends WebTestCase
{
    public function testGetFolderChildrenReturns200WithChildren(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create parent folder
        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent Folder'), $ownerId);

        // Create child folders
        $child1Id = FolderId::generate();
        $child1 = Folder::create($child1Id, FolderName::fromString('Child 1'), $ownerId, $parentId);

        $child2Id = FolderId::generate();
        $child2 = Folder::create($child2Id, FolderName::fromString('Child 2'), $ownerId, $parentId);

        // Create a folder that is not a child (should not be in results)
        $otherId = FolderId::generate();
        $other = Folder::create($otherId, FolderName::fromString('Other Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);
        $folderRepo->save($child1);
        $folderRepo->save($child2);
        $folderRepo->save($other);

        $client->request('GET', '/api/folders/'.$parentId->toString().'/children');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);

        self::assertArrayHasKey('children', $data);
        self::assertCount(2, $data['children']);

        // Verify children are sorted alphabetically by name
        self::assertSame('Child 1', $data['children'][0]['name']);
        self::assertSame('Child 2', $data['children'][1]['name']);
        self::assertSame($child1Id->toString(), $data['children'][0]['id']);
        self::assertSame($child2Id->toString(), $data['children'][1]['id']);
    }

    public function testGetFolderChildrenReturnsEmptyArrayWhenNoChildren(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create folder with no children
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Empty Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request('GET', '/api/folders/'.$folderId->toString().'/children');

        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);

        self::assertArrayHasKey('children', $data);
        self::assertCount(0, $data['children']);
    }

    public function testGetFolderChildrenReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = FolderId::generate();
        $client->request('GET', '/api/folders/'.$nonExistentId->toString().'/children');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);

        self::assertArrayHasKey('status', $data);
        self::assertSame(404, $data['status']);
        self::assertArrayHasKey('title', $data);
        self::assertSame('Not Found', $data['title']);
    }
}
