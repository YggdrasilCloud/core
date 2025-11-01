<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class ListPhotosControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    private FolderId $folderId;
    private UserId $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folderId = FolderId::generate();
        $this->ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testListPhotosReturns200WithDefaultParameters(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create folder
        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create test photos
        $photo1 = $this->createPhoto('photo1.jpg', 'image/jpeg', 1024);
        $photo2 = $this->createPhoto('photo2.png', 'image/png', 2048);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('pagination', $data);
        self::assertArrayHasKey('filters', $data);

        // Check pagination structure
        self::assertArrayHasKey('page', $data['pagination']);
        self::assertArrayHasKey('perPage', $data['pagination']);
        self::assertArrayHasKey('total', $data['pagination']);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['total']);

        // Check filters structure
        self::assertArrayHasKey('sortBy', $data['filters']);
        self::assertArrayHasKey('sortOrder', $data['filters']);
        self::assertArrayHasKey('appliedFilters', $data['filters']);
        self::assertSame('uploadedAt', $data['filters']['sortBy']);
        self::assertSame('desc', $data['filters']['sortOrder']);
        self::assertSame(0, $data['filters']['appliedFilters']);

        // Should have 2 photos
        self::assertCount(2, $data['data']);
    }

    public function testListPhotosSortsByFileName(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create photos with different filenames
        $photoB = $this->createPhoto('beta.jpg', 'image/jpeg', 1024);
        $photoA = $this->createPhoto('alpha.jpg', 'image/jpeg', 1024);
        $photoC = $this->createPhoto('charlie.jpg', 'image/jpeg', 1024);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photoB);
        $photoRepo->save($photoA);
        $photoRepo->save($photoC);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?sortBy=fileName&sortOrder=asc');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(3, $data['data']);
        self::assertSame('alpha.jpg', $data['data'][0]['fileName']);
        self::assertSame('beta.jpg', $data['data'][1]['fileName']);
        self::assertSame('charlie.jpg', $data['data'][2]['fileName']);
    }

    public function testListPhotosSortsBySize(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('photo1.jpg', 'image/jpeg', 3000);
        $photo2 = $this->createPhoto('photo2.jpg', 'image/jpeg', 1000);
        $photo3 = $this->createPhoto('photo3.jpg', 'image/jpeg', 2000);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?sortBy=sizeInBytes&sortOrder=asc');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(3, $data['data']);
        self::assertSame(1000, $data['data'][0]['sizeInBytes']);
        self::assertSame(2000, $data['data'][1]['sizeInBytes']);
        self::assertSame(3000, $data['data'][2]['sizeInBytes']);
    }

    public function testListPhotosFiltersBySearch(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('vacation-beach.jpg', 'image/jpeg', 1024);
        $photo2 = $this->createPhoto('work-meeting.jpg', 'image/jpeg', 1024);
        $photo3 = $this->createPhoto('vacation-mountain.jpg', 'image/jpeg', 1024);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?search=vacation');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertStringContainsString('vacation', $data['data'][0]['fileName']);
        self::assertStringContainsString('vacation', $data['data'][1]['fileName']);
        self::assertSame(1, $data['filters']['appliedFilters']);
    }

    public function testListPhotosFiltersByMimeType(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('photo1.jpg', 'image/jpeg', 1024);
        $photo2 = $this->createPhoto('photo2.png', 'image/png', 1024);
        $photo3 = $this->createPhoto('photo3.jpg', 'image/jpeg', 1024);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?mimeType=image/jpeg');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame('image/jpeg', $data['data'][0]['mimeType']);
        self::assertSame('image/jpeg', $data['data'][1]['mimeType']);
        self::assertSame(1, $data['filters']['appliedFilters']);
    }

    public function testListPhotosFiltersByMultipleMimeTypes(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('photo1.jpg', 'image/jpeg', 1024);
        $photo2 = $this->createPhoto('photo2.png', 'image/png', 1024);
        $photo3 = $this->createPhoto('photo3.gif', 'image/gif', 1024);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?mimeType=image/jpeg,image/png');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
    }

    public function testListPhotosFiltersByExtension(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('photo1.jpg', 'image/jpeg', 1024);
        $photo2 = $this->createPhoto('photo2.png', 'image/png', 1024);
        $photo3 = $this->createPhoto('photo3.jpeg', 'image/jpeg', 1024);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?extension=png');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(1, $data['data']);
        self::assertStringEndsWith('.png', $data['data'][0]['fileName']);
    }

    public function testListPhotosFiltersBySizeRange(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('small.jpg', 'image/jpeg', 500);
        $photo2 = $this->createPhoto('medium.jpg', 'image/jpeg', 1500);
        $photo3 = $this->createPhoto('large.jpg', 'image/jpeg', 3000);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);

        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?sizeMin=1000&sizeMax=2000');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(1, $data['data']);
        self::assertSame(1500, $data['data'][0]['sizeInBytes']);
        self::assertSame(1, $data['filters']['appliedFilters']);
    }

    public function testListPhotosHandlesPagination(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);

        // Create 5 photos
        for ($i = 1; $i <= 5; ++$i) {
            $photo = $this->createPhoto("photo{$i}.jpg", 'image/jpeg', 1024);
            $photoRepo->save($photo);
        }

        // Request page 1 with 2 items per page
        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?page=1&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame(1, $data['pagination']['page']);
        self::assertSame(2, $data['pagination']['perPage']);
        self::assertSame(5, $data['pagination']['total']);

        // Request page 2
        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?page=2&perPage=2');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertCount(2, $data['data']);
        self::assertSame(2, $data['pagination']['page']);
    }

    public function testListPhotosReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = FolderId::generate();
        $client->request('GET', '/api/folders/'.$nonExistentId->toString().'/photos');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('type', $data);
        self::assertSame('about:blank', $data['type']);
        self::assertArrayHasKey('status', $data);
        self::assertSame(Response::HTTP_NOT_FOUND, $data['status']);
    }

    public function testListPhotosCombinesMultipleFilters(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folder = Folder::create($this->folderId, FolderName::fromString('Test Folder'), $this->ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $photo1 = $this->createPhoto('vacation-beach.jpg', 'image/jpeg', 1500);
        $photo2 = $this->createPhoto('vacation-mountain.png', 'image/png', 1500);
        $photo3 = $this->createPhoto('work-meeting.jpg', 'image/jpeg', 1500);
        $photo4 = $this->createPhoto('vacation-city.jpg', 'image/jpeg', 500);

        /** @var PhotoRepositoryInterface $photoRepo */
        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo1);
        $photoRepo->save($photo2);
        $photoRepo->save($photo3);
        $photoRepo->save($photo4);

        // Filter by search + mimeType + size
        $client->request('GET', '/api/folders/'.$this->folderId->toString().'/photos?search=vacation&mimeType=image/jpeg&sizeMin=1000');

        self::assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse($client->getResponse());

        // Should only match: vacation-beach.jpg (has vacation, is jpeg, size 1500 >= 1000)
        self::assertCount(1, $data['data']);
        self::assertSame('vacation-beach.jpg', $data['data'][0]['fileName']);
        self::assertSame(3, $data['filters']['appliedFilters']); // search + mimeType + size
    }

    private function createPhoto(string $fileName, string $mimeType, int $sizeInBytes): Photo
    {
        return Photo::upload(
            PhotoId::generate(),
            $this->folderId,
            $this->ownerId,
            FileName::fromString($fileName),
            'storage-path/'.$fileName,
            'local',
            $mimeType,
            $sizeInBytes,
            null, // thumbnailKey
            null  // takenAt
        );
    }
}
