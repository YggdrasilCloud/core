<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DeleteFolderControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    private UserId $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testDeleteEmptyFolderReturns200(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = FolderId::generate();
        $folderName = 'EmptyFolder_'.$folderId->toString();
        $folder = Folder::create($folderId, FolderName::fromString($folderName), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create physical directory
        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);
        $folderPath = $pathBuilder->buildFolderPath($folder);
        $folderStorage->createDirectory($folderPath);

        $client->request('DELETE', '/api/folders/'.$folderId->toString());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('deleted', $data);
        self::assertIsArray($data['deleted']);
        self::assertSame($folderId->toString(), $data['deleted']['folderId']);
        self::assertSame(0, $data['deleted']['photosDeleted']);
        self::assertSame(0, $data['deleted']['subfoldersDeleted']);

        // Verify folder is deleted from database
        self::assertNull($folderRepo->findById($folderId));
    }

    public function testDeleteNonEmptyFolderWithoutRecursiveReturns400(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = FolderId::generate();
        $folderName = 'FolderWithPhotos_'.$folderId->toString();
        $folder = Folder::create($folderId, FolderName::fromString($folderName), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create a photo in the folder
        $photo = Photo::upload(
            PhotoId::generate(),
            $folderId,
            $this->ownerId,
            FileName::fromString('test.jpg'),
            'storage-path/test.jpg',
            'local',
            'image/jpeg',
            1024,
        );

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        $client->request('DELETE', '/api/folders/'.$folderId->toString());

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('detail', $data);
        self::assertIsString($data['detail']);
        self::assertStringContainsString('1 photo(s)', $data['detail']);
        self::assertStringContainsString('recursive=true', $data['detail']);
    }

    public function testDeleteNonEmptyFolderWithRecursiveReturns200(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = FolderId::generate();
        $folderName = 'RecursiveDelete_'.$folderId->toString();
        $folder = Folder::create($folderId, FolderName::fromString($folderName), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create physical directory
        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);
        $folderPath = $pathBuilder->buildFolderPath($folder);
        $folderStorage->createDirectory($folderPath);

        // Create photos in the folder
        $photo1 = Photo::upload(
            PhotoId::generate(),
            $folderId,
            $this->ownerId,
            FileName::fromString('photo1.jpg'),
            'storage-path/photo1.jpg',
            'local',
            'image/jpeg',
            1024,
        );
        $photo2 = Photo::upload(
            PhotoId::generate(),
            $folderId,
            $this->ownerId,
            FileName::fromString('photo2.jpg'),
            'storage-path/photo2.jpg',
            'local',
            'image/jpeg',
            2048,
        );

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);

        $client->request('DELETE', '/api/folders/'.$folderId->toString().'?recursive=true');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('deleted', $data);
        self::assertIsArray($data['deleted']);
        self::assertSame($folderId->toString(), $data['deleted']['folderId']);
        self::assertSame(2, $data['deleted']['photosDeleted']);
        self::assertSame(0, $data['deleted']['subfoldersDeleted']);

        // Verify folder is deleted
        self::assertNull($folderRepo->findById($folderId));

        // Verify photos are deleted
        self::assertNull($photoRepo->findById($photo1->id()));
        self::assertNull($photoRepo->findById($photo2->id()));
    }

    public function testDeleteFolderWithSubfoldersRecursively(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create parent folder
        $parentId = FolderId::generate();
        $parentName = 'ParentFolder_'.$parentId->toString();
        $parentFolder = Folder::create($parentId, FolderName::fromString($parentName), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parentFolder);

        // Create physical directory
        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);
        $parentPath = $pathBuilder->buildFolderPath($parentFolder);
        $folderStorage->createDirectory($parentPath);

        // Create child folder
        $childId = FolderId::generate();
        $childName = 'ChildFolder_'.$childId->toString();
        $childFolder = Folder::create(
            $childId,
            FolderName::fromString($childName),
            $this->ownerId,
            $parentId
        );
        $folderRepo->save($childFolder);

        // Create physical child directory
        $childPath = $pathBuilder->buildFolderPath($childFolder);
        $folderStorage->createDirectory($childPath);

        // Create a photo in the child folder
        $photo = Photo::upload(
            PhotoId::generate(),
            $childId,
            $this->ownerId,
            FileName::fromString('child-photo.jpg'),
            'storage-path/child-photo.jpg',
            'local',
            'image/jpeg',
            1024,
        );

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        $client->request('DELETE', '/api/folders/'.$parentId->toString().'?recursive=true');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('deleted', $data);
        self::assertIsArray($data['deleted']);
        self::assertSame($parentId->toString(), $data['deleted']['folderId']);
        self::assertSame(1, $data['deleted']['photosDeleted']);
        self::assertSame(1, $data['deleted']['subfoldersDeleted']);

        // Verify both folders are deleted
        self::assertNull($folderRepo->findById($parentId));
        self::assertNull($folderRepo->findById($childId));

        // Verify photo is deleted
        self::assertNull($photoRepo->findById($photo->id()));
    }

    public function testDeleteNonExistentFolderReturns404(): void
    {
        $client = self::createClient();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440099';

        $client->request('DELETE', '/api/folders/'.$nonExistentId);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteFolderWithSubfoldersWithoutRecursiveReturns400(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create parent folder
        $parentId = FolderId::generate();
        $parentName = 'ParentWithChild_'.$parentId->toString();
        $parentFolder = Folder::create($parentId, FolderName::fromString($parentName), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parentFolder);

        // Create child folder
        $childId = FolderId::generate();
        $childName = 'Child_'.$childId->toString();
        $childFolder = Folder::create(
            $childId,
            FolderName::fromString($childName),
            $this->ownerId,
            $parentId
        );
        $folderRepo->save($childFolder);

        $client->request('DELETE', '/api/folders/'.$parentId->toString());

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('detail', $data);
        self::assertIsString($data['detail']);
        self::assertStringContainsString('1 subfolder(s)', $data['detail']);
    }
}
