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

/**
 * @internal
 *
 * @coversNothing
 */
final class GetFolderChildrenControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

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

        $data = $this->decodeJsonResponse($client->getResponse());

        // Check new response structure
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('pagination', $data);
        self::assertArrayHasKey('filters', $data);

        // Check data
        self::assertCount(2, $data['data']);

        // Verify children are sorted alphabetically by name (default)
        self::assertSame('Child 1', $data['data'][0]['name']);
        self::assertSame('Child 2', $data['data'][1]['name']);
        self::assertSame($child1Id->toString(), $data['data'][0]['id']);
        self::assertSame($child2Id->toString(), $data['data'][1]['id']);

        // Check pagination
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['total']);

        // Check filters
        self::assertSame('name', $data['filters']['sortBy']);
        self::assertSame('asc', $data['filters']['sortOrder']);
        self::assertSame(0, $data['filters']['appliedFilters']);
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

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);
        self::assertCount(0, $data['data']);
        self::assertSame(0, $data['pagination']['total']);
    }

    public function testGetFolderChildrenReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = FolderId::generate();
        $client->request('GET', '/api/folders/'.$nonExistentId->toString().'/children');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('type', $data);
        self::assertSame('about:blank', $data['type']);
        self::assertArrayHasKey('status', $data);
        self::assertSame(Response::HTTP_NOT_FOUND, $data['status']);
        self::assertArrayHasKey('title', $data);
        self::assertSame('Not Found', $data['title']);
    }

    public function testGetFolderChildrenSortsByNameDescending(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent'), $ownerId);

        $childA = Folder::create(FolderId::generate(), FolderName::fromString('Alpha'), $ownerId, $parentId);
        $childB = Folder::create(FolderId::generate(), FolderName::fromString('Beta'), $ownerId, $parentId);
        $childC = Folder::create(FolderId::generate(), FolderName::fromString('Charlie'), $ownerId, $parentId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);
        $folderRepo->save($childA);
        $folderRepo->save($childB);
        $folderRepo->save($childC);

        $client->request('GET', '/api/folders/'.$parentId->toString().'/children?sortBy=name&sortOrder=desc');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(3, $data['data']);
        self::assertSame('Charlie', $data['data'][0]['name']);
        self::assertSame('Beta', $data['data'][1]['name']);
        self::assertSame('Alpha', $data['data'][2]['name']);
        self::assertSame('desc', $data['filters']['sortOrder']);
    }

    public function testGetFolderChildrenSortsByCreatedAt(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent'), $ownerId);

        // Create folders (timestamps are set automatically)
        $oldChild = Folder::create(
            FolderId::generate(),
            FolderName::fromString('Old Folder'),
            $ownerId,
            $parentId
        );
        $newChild = Folder::create(
            FolderId::generate(),
            FolderName::fromString('New Folder'),
            $ownerId,
            $parentId
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);
        $folderRepo->save($oldChild);
        $folderRepo->save($newChild);

        $client->request('GET', '/api/folders/'.$parentId->toString().'/children?sortBy=createdAt&sortOrder=asc');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame('Old Folder', $data['data'][0]['name']);
        self::assertSame('New Folder', $data['data'][1]['name']);
        self::assertSame('createdAt', $data['filters']['sortBy']);
    }

    public function testGetFolderChildrenFiltersBySearch(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent'), $ownerId);

        $vacation1 = Folder::create(FolderId::generate(), FolderName::fromString('Vacation 2024'), $ownerId, $parentId);
        $work = Folder::create(FolderId::generate(), FolderName::fromString('Work Files'), $ownerId, $parentId);
        $vacation2 = Folder::create(FolderId::generate(), FolderName::fromString('Vacation 2025'), $ownerId, $parentId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);
        $folderRepo->save($vacation1);
        $folderRepo->save($work);
        $folderRepo->save($vacation2);

        $client->request('GET', '/api/folders/'.$parentId->toString().'/children?search=vacation');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertStringContainsStringIgnoringCase('vacation', $data['data'][0]['name']);
        self::assertStringContainsStringIgnoringCase('vacation', $data['data'][1]['name']);
        self::assertSame(1, $data['filters']['appliedFilters']);
    }

    public function testGetFolderChildrenHandlesPagination(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $parentId = FolderId::generate();
        $parent = Folder::create($parentId, FolderName::fromString('Parent'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parent);

        // Create 5 child folders
        for ($i = 1; $i <= 5; ++$i) {
            $child = Folder::create(FolderId::generate(), FolderName::fromString("Child {$i}"), $ownerId, $parentId);
            $folderRepo->save($child);
        }

        // Request page 1 with 2 items per page
        $client->request('GET', '/api/folders/'.$parentId->toString().'/children?page=1&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['perPage']);
        self::assertSame(5, $data['pagination']['total']);

        // Request page 2
        $client->request('GET', '/api/folders/'.$parentId->toString().'/children?page=2&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame(2, $data['pagination']['page']);
    }
}
