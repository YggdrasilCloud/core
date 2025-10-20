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

/**
 * @internal
 *
 * @coversNothing
 */
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

        self::assertGreaterThanOrEqual(3, count($data));

        // Find our test folders in the response
        $testFolders = array_filter($data, static fn ($folder) => in_array(
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

        self::assertNotEmpty($data);
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

        self::assertNotEmpty($data);

        // Verify every folder has the parentId field
        foreach ($data as $folderData) {
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

        // Find our test folders
        $testFolders = array_filter($data, static fn ($folder) => in_array(
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
}
