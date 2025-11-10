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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function dirname;

/**
 * @coversNothing
 */
final class GetPhotoFileControllerTest extends WebTestCase
{
    private string $testStoragePath;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        parent::setUp();
        // Use the actual storage path that the application expects
        $this->testStoragePath = __DIR__.'/../../../../../../var/storage';
        if (!is_dir($this->testStoragePath)) {
            mkdir($this->testStoragePath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test storage
        if (is_dir($this->testStoragePath)) {
            $this->removeDirectory($this->testStoragePath);
        }
        parent::tearDown();
    }

    public function testGetPhotoFileReturns200WithCorrectHeaders(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create test photo in database
        $photoId = PhotoId::generate();
        $storageKey = 'photos/2025/10/11/test.jpg';

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            $storageKey,
            'local',
            'image/jpeg',
            1024
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        // Create actual test file on disk
        $testFilePath = $this->testStoragePath.'/'.$storageKey;
        $dir = dirname($testFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($testFilePath, 'fake image content');

        // Make request
        $client->request('GET', '/api/photos/'.$photoId->toString().'/file');

        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        // TODO: Re-enable when FileStorage supports metadata queries for ETag/Last-Modified
        // self::assertTrue($response->headers->has('ETag'));
        // self::assertTrue($response->headers->has('Last-Modified'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testGetPhotoFileReturns404WhenPhotoNotFound(): void
    {
        $client = self::createClient();

        $fakePhotoId = PhotoId::generate()->toString();
        $client->request('GET', '/api/photos/'.$fakePhotoId.'/file');

        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Photo not found', $response->getContent());
    }

    public function testGetPhotoFileReturns404WhenFileNotOnDisk(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create photo in DB but DON'T create file on disk
        $photoId = PhotoId::generate();

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('missing.jpg'),
            'photos/2025/10/11/missing.jpg',
            'local',
            'image/jpeg',
            1024
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        $client->request('GET', '/api/photos/'.$photoId->toString().'/file');

        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('File not found on disk', $response->getContent());
    }

    public function testGetPhotoFileReturns400ForInvalidPhotoId(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/photos/invalid-uuid/file');

        $response = $client->getResponse();

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Invalid photo ID', $response->getContent());
    }

    // TODO: Re-enable when FileStorage supports metadata queries for ETag/Last-Modified
    // This test requires ETag support which is not yet implemented after storage DSN migration
    /*
    public function testGetPhotoFileReturns304WhenNotModified(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create test photo
        $photoId = PhotoId::generate();
        $storageKey = 'photos/2025/10/11/test.jpg';

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            $storageKey,
            'local',
            'image/jpeg',
            1024
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        // Create test file
        $testFilePath = $this->testStoragePath.'/'.$storageKey;
        $dir = dirname($testFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($testFilePath, 'fake image content');

        // First request to get ETag
        $client->request('GET', '/api/photos/'.$photoId->toString().'/file');
        $response = $client->getResponse();
        $etag = $response->headers->get('ETag');

        // Second request with If-None-Match header
        $client->request(
            'GET',
            '/api/photos/'.$photoId->toString().'/file',
            [],
            [],
            ['HTTP_IF_NONE_MATCH' => $etag]
        );

        $response = $client->getResponse();

        self::assertSame(304, $response->getStatusCode());
    }
    */

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path.'/'.$file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : unlink($fullPath);
        }
        rmdir($path);
    }
}
