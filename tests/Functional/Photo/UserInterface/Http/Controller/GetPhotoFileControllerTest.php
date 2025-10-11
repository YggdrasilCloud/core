<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\StoredFile;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetPhotoFileControllerTest extends WebTestCase
{
    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStoragePath = sys_get_temp_dir() . '/test_photos_' . uniqid();
        mkdir($this->testStoragePath, 0755, true);
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
        $client = static::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create test photo in database
        $photoId = PhotoId::generate();
        $relativePath = '2025/10/11/test.jpg';
        $storedFile = StoredFile::create($relativePath, 'image/jpeg', 1024);

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            $storedFile
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        // Create actual test file on disk
        $testFilePath = $this->testStoragePath . '/' . $relativePath;
        mkdir(dirname($testFilePath), 0755, true);
        file_put_contents($testFilePath, 'fake image content');

        // Override storage path parameter for this test
        $container->set('photo.storage_path', $this->testStoragePath);

        // Make request
        $client->request('GET', '/api/photos/' . $photoId->toString() . '/file');

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('Last-Modified'));
        $this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testGetPhotoFileReturns404WhenPhotoNotFound(): void
    {
        $client = static::createClient();

        $fakePhotoId = PhotoId::generate()->toString();
        $client->request('GET', '/api/photos/' . $fakePhotoId . '/file');

        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Photo not found', $response->getContent());
    }

    public function testGetPhotoFileReturns404WhenFileNotOnDisk(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create photo in DB but DON'T create file on disk
        $photoId = PhotoId::generate();
        $storedFile = StoredFile::create('2025/10/11/missing.jpg', 'image/jpeg', 1024);

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('missing.jpg'),
            $storedFile
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        $container->set('photo.storage_path', $this->testStoragePath);

        $client->request('GET', '/api/photos/' . $photoId->toString() . '/file');

        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('File not found on disk', $response->getContent());
    }

    public function testGetPhotoFileReturns400ForInvalidPhotoId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/photos/invalid-uuid/file');

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid photo ID', $response->getContent());
    }

    public function testGetPhotoFileReturns304WhenNotModified(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        // Create test folder
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        // Create test photo
        $photoId = PhotoId::generate();
        $relativePath = '2025/10/11/test.jpg';
        $storedFile = StoredFile::create($relativePath, 'image/jpeg', 1024);

        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            $storedFile
        );

        $photoRepo = $container->get(PhotoRepositoryInterface::class);
        $photoRepo->save($photo);

        // Create test file
        $testFilePath = $this->testStoragePath . '/' . $relativePath;
        mkdir(dirname($testFilePath), 0755, true);
        file_put_contents($testFilePath, 'fake image content');

        $container->set('photo.storage_path', $this->testStoragePath);

        // First request to get ETag
        $client->request('GET', '/api/photos/' . $photoId->toString() . '/file');
        $response = $client->getResponse();
        $etag = $response->headers->get('ETag');

        // Second request with If-None-Match header
        $client->request(
            'GET',
            '/api/photos/' . $photoId->toString() . '/file',
            [],
            [],
            ['HTTP_IF_NONE_MATCH' => $etag]
        );

        $response = $client->getResponse();

        $this->assertSame(304, $response->getStatusCode());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . '/' . $file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : unlink($fullPath);
        }
        rmdir($path);
    }
}
