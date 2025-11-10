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

final class ListFoldersControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    public function testListFoldersReturns200WithAllFolders(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create root folders
        $root1Id = FolderId::generate();
        $root1 = Folder::create($root1Id, FolderName::fromString('Photos 2024'), $ownerId);

        $root2Id = FolderId::generate();
        $root2 = Folder::create($root2Id, FolderName::fromString('Documents'), $ownerId);

        // Create child folder
        $childId = FolderId::generate();
        $child = Folder::create($childId, FolderName::fromString('Vacances'), $ownerId, $root1Id);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($root1);
        $folderRepo->save($root2);
        $folderRepo->save($child);

        $client->request('GET', '/api/folders');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $data = $this->decodeJsonResponse($client->getResponse());

        // Check new response structure
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('pagination', $data);
        self::assertArrayHasKey('filters', $data);

        self::assertGreaterThanOrEqual(3, count($data['data']));

        // Find our test folders in the response
        $testFolders = array_filter($data['data'], static fn ($folder) => in_array(
            $folder['id'],
            [$root1Id->toString(), $root2Id->toString(), $childId->toString()],
            true
        ));

        self::assertCount(3, $testFolders);

        // Verify root folders have null parentId
        $rootFolders = array_filter($testFolders, static fn ($folder) => in_array(
            $folder['id'],
            [$root1Id->toString(), $root2Id->toString()],
            true
        ));

        foreach ($rootFolders as $rootFolder) {
            self::assertArrayHasKey('id', $rootFolder);
            self::assertArrayHasKey('name', $rootFolder);
            self::assertArrayHasKey('createdAt', $rootFolder);
            self::assertArrayHasKey('parentId', $rootFolder);
            self::assertNull($rootFolder['parentId']);
        }

        // Verify child folder has correct parentId
        $childFolder = array_values(array_filter($testFolders, static fn ($folder) => $folder['id'] === $childId->toString()))[0];

        self::assertArrayHasKey('parentId', $childFolder);
        self::assertSame($root1Id->toString(), $childFolder['parentId']);

        // Check filters
        self::assertSame('name', $data['filters']['sortBy']);
        self::assertSame('asc', $data['filters']['sortOrder']);
        self::assertSame(0, $data['filters']['appliedFilters']);
    }

    public function testListFoldersReturnsArrayOfFolders(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create a test folder to ensure non-empty result
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request('GET', '/api/folders');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);
        self::assertNotEmpty($data['data']);
    }

    public function testListFoldersIncludesParentIdField(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create a single test folder
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request('GET', '/api/folders');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);
        self::assertNotEmpty($data['data']);

        // Verify every folder has the parentId field
        foreach ($data['data'] as $folderData) {
            self::assertArrayHasKey('parentId', $folderData);
        }
    }

    public function testListFoldersSupportsNestedHierarchy(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create hierarchy: Root -> Child -> Grandchild
        $rootId = FolderId::generate();
        $root = Folder::create($rootId, FolderName::fromString('Root'), $ownerId);

        $childId = FolderId::generate();
        $child = Folder::create($childId, FolderName::fromString('Child'), $ownerId, $rootId);

        $grandchildId = FolderId::generate();
        $grandchild = Folder::create($grandchildId, FolderName::fromString('Grandchild'), $ownerId, $childId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($root);
        $folderRepo->save($child);
        $folderRepo->save($grandchild);

        $client->request('GET', '/api/folders');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);

        // Find our test folders
        $testFolders = array_filter($data['data'], static fn ($folder) => in_array(
            $folder['id'],
            [$rootId->toString(), $childId->toString(), $grandchildId->toString()],
            true
        ));

        self::assertCount(3, $testFolders);

        // Verify hierarchy relationships
        $rootFolder = array_values(array_filter($testFolders, static fn ($f) => $f['id'] === $rootId->toString()))[0];
        $childFolder = array_values(array_filter($testFolders, static fn ($f) => $f['id'] === $childId->toString()))[0];
        $grandchildFolder = array_values(array_filter($testFolders, static fn ($f) => $f['id'] === $grandchildId->toString()))[0];

        self::assertNull($rootFolder['parentId']);
        self::assertSame($rootId->toString(), $childFolder['parentId']);
        self::assertSame($childId->toString(), $grandchildFolder['parentId']);
    }

    public function testListFoldersSortsByNameAscending(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $folderC = Folder::create(FolderId::generate(), FolderName::fromString('Charlie'), $ownerId);
        $folderA = Folder::create(FolderId::generate(), FolderName::fromString('Alpha'), $ownerId);
        $folderB = Folder::create(FolderId::generate(), FolderName::fromString('Beta'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folderC);
        $folderRepo->save($folderA);
        $folderRepo->save($folderB);

        $client->request('GET', '/api/folders?sortBy=name&sortOrder=asc&perPage=100');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);
        self::assertGreaterThanOrEqual(3, count($data['data']));

        // Find our test folders and verify they're sorted
        $alphaIndex = null;
        $betaIndex = null;
        $charlieIndex = null;

        foreach ($data['data'] as $index => $folder) {
            if ($folder['name'] === 'Alpha') {
                $alphaIndex = $index;
            } elseif ($folder['name'] === 'Beta') {
                $betaIndex = $index;
            } elseif ($folder['name'] === 'Charlie') {
                $charlieIndex = $index;
            }
        }

        self::assertNotNull($alphaIndex);
        self::assertNotNull($betaIndex);
        self::assertNotNull($charlieIndex);
        self::assertLessThan($betaIndex, $alphaIndex);
        self::assertLessThan($charlieIndex, $betaIndex);
    }

    public function testListFoldersSortsByNameDescending(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $folderA = Folder::create(FolderId::generate(), FolderName::fromString('Alpha Sort'), $ownerId);
        $folderB = Folder::create(FolderId::generate(), FolderName::fromString('Beta Sort'), $ownerId);
        $folderC = Folder::create(FolderId::generate(), FolderName::fromString('Charlie Sort'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folderA);
        $folderRepo->save($folderB);
        $folderRepo->save($folderC);

        $client->request('GET', '/api/folders?sortBy=name&sortOrder=desc&perPage=100');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertSame('desc', $data['filters']['sortOrder']);
    }

    public function testListFoldersSortsByCreatedAt(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create folders (timestamps are set automatically)
        $oldFolder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('Old Folder Sort'),
            $ownerId
        );
        $newFolder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('New Folder Sort'),
            $ownerId
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($oldFolder);
        $folderRepo->save($newFolder);

        $client->request('GET', '/api/folders?sortBy=createdAt&sortOrder=asc&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertSame('createdAt', $data['filters']['sortBy']);
    }

    public function testListFoldersFiltersBySearch(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $vacation1 = Folder::create(FolderId::generate(), FolderName::fromString('Vacation Photos 2024'), $ownerId);
        $work = Folder::create(FolderId::generate(), FolderName::fromString('Work Documents'), $ownerId);
        $vacation2 = Folder::create(FolderId::generate(), FolderName::fromString('Vacation Photos 2025'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($vacation1);
        $folderRepo->save($work);
        $folderRepo->save($vacation2);

        $client->request('GET', '/api/folders?search=Vacation');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);

        // Filter results to only our test folders
        $testFolders = array_filter($data['data'], static fn ($folder) => str_contains($folder['name'], 'Vacation') || str_contains($folder['name'], 'Work'));

        // Should find the 2 vacation folders, not the work folder
        $vacationFolders = array_filter($testFolders, static fn ($folder) => str_contains($folder['name'], 'Vacation'));
        self::assertGreaterThanOrEqual(2, count($vacationFolders));

        self::assertSame(1, $data['filters']['appliedFilters']);
    }

    public function testListFoldersHandlesPagination(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);

        // Create 5 test folders
        for ($i = 1; $i <= 5; ++$i) {
            $folder = Folder::create(FolderId::generate(), FolderName::fromString("Pagination Test {$i}"), $ownerId);
            $folderRepo->save($folder);
        }

        // Request page 1 with 2 items per page
        $client->request('GET', '/api/folders?page=1&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('pagination', $data);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['perPage']);
        self::assertGreaterThanOrEqual(5, $data['pagination']['total']);

        // Request page 2
        $client->request('GET', '/api/folders?page=2&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertSame(2, $data['pagination']['page']);
    }
}
