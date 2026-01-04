<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

final class RenameFolderControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    public function testRenameFolderReturns200WithUpdatedFolder(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        // Use unique folder names with UUID suffix to avoid conflicts between test runs
        $originalName = 'Original_'.$folderId->toString();
        $folder = Folder::create($folderId, FolderName::fromString($originalName), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create the physical directory (normally done by CreateFolderHandler)
        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);
        $originalPath = $pathBuilder->buildFolderPath($folder);
        $folderStorage->createDirectory($originalPath);

        $newName = 'Renamed_'.$folderId->toString();

        try {
            $client->request(
                'PATCH',
                '/api/folders/'.$folderId->toString(),
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => $newName], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);

            $data = $this->decodeJsonResponse($client->getResponse());

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('name', $data);
            self::assertSame($folderId->toString(), $data['id']);
            self::assertSame($newName, $data['name']);

            // Verify folder was actually renamed in the database
            $renamedFolder = $folderRepo->findById($folderId);
            self::assertNotNull($renamedFolder);
            self::assertSame($newName, $renamedFolder->name()->toString());
        } finally {
            // Clean up physical directories
            $renamedPath = 'photos/'.$newName;
            if ($folderStorage->directoryExists($renamedPath)) {
                $folderStorage->removeEmptyDirectory($renamedPath);
            }
            if ($folderStorage->directoryExists($originalPath)) {
                $folderStorage->removeEmptyDirectory($originalPath);
            }
        }
    }

    public function testRenameFolderReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440099';

        $client->request(
            'PATCH',
            '/api/folders/'.$nonExistentId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'New Name'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRenameFolderReturns400WhenNameIsEmpty(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Original Name'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request(
            'PATCH',
            '/api/folders/'.$folderId->toString(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => ''], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRenameFolderReturns400WhenBodyIsEmpty(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Original Name'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request(
            'PATCH',
            '/api/folders/'.$folderId->toString(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            ''
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRenameFolderReturns400WhenJsonIsInvalid(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Original Name'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request(
            'PATCH',
            '/api/folders/'.$folderId->toString(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
