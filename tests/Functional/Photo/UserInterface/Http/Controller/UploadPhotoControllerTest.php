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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversNothing
 */
final class UploadPhotoControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    private const OWNER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testUploadPhotoReturns201WithCreatedPhoto(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        // Create a folder to upload photo to
        $folderId = $this->createTestFolder($container);

        // Create a test image file (1x1 PNG)
        $imageContent = $this->decodeBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $testFile = $this->createTestImageFile($imageContent, 'test-photo.png', 'image/png');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('folderId', $data);
        self::assertArrayHasKey('fileName', $data);
        self::assertArrayHasKey('mimeType', $data);
        self::assertArrayHasKey('size', $data);

        self::assertSame($folderId->toString(), $data['folderId']);
        self::assertSame('test-photo.png', $data['fileName']);
        self::assertSame('image/png', $data['mimeType']);

        // Verify Location header
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString(sprintf('/api/folders/%s/photos', $folderId->toString()), $location);
    }

    public function testUploadPhotoSanitizesFilename(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        $imageContent = $this->decodeBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        // Filename with problematic characters
        $testFile = $this->createTestImageFile($imageContent, 'test<>|photo:*?.png', 'image/png');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());

        // Verify filename was sanitized (problematic chars replaced with _, then trimmed)
        self::assertSame('test_photo.png', $data['fileName']);
    }

    public function testUploadPhotoReturns400WhenFileMissing(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            [], // No file uploaded
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('title', $data);
        self::assertStringContainsString('Missing required file: photo', $data['title']);
    }

    public function testUploadPhotoReturns400WhenOwnerIdMissing(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        $imageContent = $this->decodeBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $testFile = $this->createTestImageFile($imageContent, 'test.png', 'image/png');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            [], // No ownerId
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('title', $data);
        self::assertStringContainsString('Missing required field: ownerId', $data['title']);
    }

    public function testUploadPhotoReturns400WhenFileTooLarge(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        // Create a "fake" large file by setting a large size
        // Note: We can't actually create a 21MB file in memory for tests
        // But we can use a mock or test the validation logic separately
        $imageContent = $this->decodeBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        // Create file that will report a large size
        $testFile = $this->createTestImageFile($imageContent, 'large.png', 'image/png');

        // Override getSize() by using a different approach
        // For now, we'll test this with FileValidator unit tests instead
        // This functional test will focus on valid uploads
        self::markTestSkipped('File size validation is better tested in FileValidatorTest unit tests');
    }

    public function testUploadPhotoReturns400WhenMimeTypeNotAllowed(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        // Create a text file (not an image)
        $testFile = $this->createTestImageFile('not an image', 'test.txt', 'text/plain');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('title', $data);
        self::assertStringContainsString('File type not allowed', $data['title']);
    }

    public function testUploadPhotoReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentFolderId = '550e8400-e29b-41d4-a716-446655440099';

        $imageContent = $this->decodeBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $testFile = $this->createTestImageFile($imageContent, 'test.png', 'image/png');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $nonExistentFolderId),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $data = $this->decodeJsonResponse($client->getResponse());

        self::assertArrayHasKey('title', $data);
    }

    public function testUploadPhotoSupportsJpeg(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        // Minimal valid JPEG
        $jpegContent = $this->decodeBase64('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAIBAQIBAQICAgICAgICAwUDAwMDAwYEBAMFBwYHBwcGBwcICQsJCAgKCAcHCg0KCgsMDAwMBwkODw0MDgsMDAz/2wBDAQICAgMDAwYDAwYMCAcIDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAz/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlbaWmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD9/KKKKAP/2Q==');
        $testFile = $this->createTestImageFile($jpegContent, 'test.jpg', 'image/jpeg');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());
        self::assertSame('image/jpeg', $data['mimeType']);
    }

    public function testUploadPhotoSupportsGif(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $folderId = $this->createTestFolder($container);

        // Minimal valid GIF (1x1 transparent)
        $gifContent = $this->decodeBase64('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $testFile = $this->createTestImageFile($gifContent, 'test.gif', 'image/gif');

        $client->request(
            'POST',
            sprintf('/api/folders/%s/photos', $folderId->toString()),
            ['ownerId' => self::OWNER_ID],
            ['photo' => $testFile],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->decodeJsonResponse($client->getResponse());
        self::assertSame('image/gif', $data['mimeType']);
    }

    /**
     * Create a test folder in the database.
     *
     * @param mixed $container
     */
    private function createTestFolder($container): FolderId
    {
        $folderId = FolderId::generate();
        $folder = Folder::create(
            $folderId,
            FolderName::fromString('Test Upload Folder'),
            UserId::fromString(self::OWNER_ID)
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        return $folderId;
    }

    /**
     * Create a test UploadedFile.
     */
    private function createTestImageFile(string $content, string $originalName, string $mimeType): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        self::assertNotFalse($tempFile, 'Failed to create temporary file');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            $originalName,
            $mimeType,
            null,
            true // test mode
        );
    }

    /**
     * Helper to decode base64 with type safety.
     */
    private function decodeBase64(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        self::assertNotFalse($decoded, 'Failed to decode base64');

        return $decoded;
    }
}
