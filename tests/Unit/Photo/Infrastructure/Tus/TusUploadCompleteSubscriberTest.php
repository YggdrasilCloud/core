<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Infrastructure\Tus;

use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use App\Photo\Domain\Service\FileValidator;
use App\Photo\Infrastructure\Tus\TusUploadCompleteSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\TusEvent;
use TusPhp\Events\UploadComplete;
use TusPhp\File;
use TusPhp\Request;
use TusPhp\Response;

final class TusUploadCompleteSubscriberTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private FileValidator $fileValidator;
    private LoggerInterface&MockObject $logger;
    private TusUploadCompleteSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->fileValidator = new FileValidator(5368709120, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new TusUploadCompleteSubscriber(
            $this->commandBus,
            $this->fileValidator,
            $this->logger
        );
    }

    public function testIgnoresNonUploadCompleteEvents(): void
    {
        $event = $this->createMock(TusEvent::class);

        $this->commandBus->expects(self::never())->method('dispatch');

        ($this->subscriber)($event);
    }

    public function testLogsErrorWhenFolderIdIsMissing(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getFilePath')->willReturn('/tmp/test.jpg');
        $file->method('getKey')->willReturn('upload-key-123');
        $file->method('details')->willReturn([
            'metadata' => [
                'ownerId' => 'owner-uuid',
            ],
        ]);

        $event = $this->createUploadCompleteEvent($file);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Tus upload missing required metadata', self::callback(static function ($context) {
                return $context['uploadKey'] === 'upload-key-123';
            }))
        ;

        $this->commandBus->expects(self::never())->method('dispatch');

        ($this->subscriber)($event);
    }

    public function testLogsErrorWhenOwnerIdIsMissing(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getFilePath')->willReturn('/tmp/test.jpg');
        $file->method('getKey')->willReturn('upload-key-123');
        $file->method('details')->willReturn([
            'metadata' => [
                'folderId' => 'folder-uuid',
            ],
        ]);

        $event = $this->createUploadCompleteEvent($file);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Tus upload missing required metadata', self::anything())
        ;

        $this->commandBus->expects(self::never())->method('dispatch');

        ($this->subscriber)($event);
    }

    public function testDispatchesCommandWithCorrectMetadata(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $file = $this->createMock(File::class);
            $file->method('getFilePath')->willReturn($tempFile);
            $file->method('getKey')->willReturn('upload-key-123');
            $file->method('getFileSize')->willReturn(12);
            $file->method('details')->willReturn([
                'metadata' => [
                    'folderId' => 'folder-uuid',
                    'ownerId' => 'owner-uuid',
                    'filename' => 'photo.jpg',
                    'filetype' => 'image/jpeg',
                ],
            ]);

            $event = $this->createUploadCompleteEvent($file);

            $this->commandBus->expects(self::once())
                ->method('dispatch')
                ->with(self::callback(static function ($command) {
                    return $command instanceof UploadPhotoToFolderCommand
                        && $command->folderId === 'folder-uuid'
                        && $command->ownerId === 'owner-uuid'
                        && $command->fileName === 'photo.jpg'
                        && $command->mimeType === 'image/jpeg'
                        && $command->sizeInBytes === 12;
                }))
                ->willReturn(new Envelope(new stdClass()))
            ;

            ($this->subscriber)($event);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUsesAlternativeFilenameMetadataKey(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $file = $this->createMock(File::class);
            $file->method('getFilePath')->willReturn($tempFile);
            $file->method('getKey')->willReturn('upload-key-123');
            $file->method('getFileSize')->willReturn(12);
            $file->method('details')->willReturn([
                'metadata' => [
                    'folderId' => 'folder-uuid',
                    'ownerId' => 'owner-uuid',
                    'fileName' => 'alternative.png',
                    'mimeType' => 'image/png',
                ],
            ]);

            $event = $this->createUploadCompleteEvent($file);

            $this->commandBus->expects(self::once())
                ->method('dispatch')
                ->with(self::callback(static function ($command) {
                    return $command instanceof UploadPhotoToFolderCommand
                        && $command->fileName === 'alternative.png';
                }))
                ->willReturn(new Envelope(new stdClass()))
            ;

            ($this->subscriber)($event);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testDetectsMimeTypeWhenNotProvided(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $file = $this->createMock(File::class);
            $file->method('getFilePath')->willReturn($tempFile);
            $file->method('getKey')->willReturn('upload-key-123');
            $file->method('getFileSize')->willReturn(12);
            $file->method('details')->willReturn([
                'metadata' => [
                    'folderId' => 'folder-uuid',
                    'ownerId' => 'owner-uuid',
                    'filename' => 'file.txt',
                ],
            ]);

            $event = $this->createUploadCompleteEvent($file);

            $this->commandBus->expects(self::once())
                ->method('dispatch')
                ->with(self::callback(static function ($command) {
                    return $command instanceof UploadPhotoToFolderCommand
                        && $command->mimeType === 'text/plain';
                }))
                ->willReturn(new Envelope(new stdClass()))
            ;

            ($this->subscriber)($event);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUsesDefaultFilenameWhenNotProvided(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $file = $this->createMock(File::class);
            $file->method('getFilePath')->willReturn($tempFile);
            $file->method('getKey')->willReturn('upload-key-123');
            $file->method('getFileSize')->willReturn(12);
            $file->method('details')->willReturn([
                'metadata' => [
                    'folderId' => 'folder-uuid',
                    'ownerId' => 'owner-uuid',
                    'filetype' => 'image/jpeg',
                ],
            ]);

            $event = $this->createUploadCompleteEvent($file);

            $this->commandBus->expects(self::once())
                ->method('dispatch')
                ->with(self::callback(static function ($command) {
                    return $command instanceof UploadPhotoToFolderCommand
                        && $command->fileName === 'unnamed';
                }))
                ->willReturn(new Envelope(new stdClass()))
            ;

            ($this->subscriber)($event);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCleansUpTemporaryFileAfterSuccess(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        $file = $this->createMock(File::class);
        $file->method('getFilePath')->willReturn($tempFile);
        $file->method('getKey')->willReturn('upload-key-123');
        $file->method('getFileSize')->willReturn(12);
        $file->method('details')->willReturn([
            'metadata' => [
                'folderId' => 'folder-uuid',
                'ownerId' => 'owner-uuid',
                'filename' => 'photo.jpg',
                'filetype' => 'image/jpeg',
            ],
        ]);

        $event = $this->createUploadCompleteEvent($file);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new stdClass()));

        self::assertFileExists($tempFile);

        ($this->subscriber)($event);

        self::assertFileDoesNotExist($tempFile);
    }

    public function testLogsInfoOnSuccessfulUpload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tus_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $file = $this->createMock(File::class);
            $file->method('getFilePath')->willReturn($tempFile);
            $file->method('getKey')->willReturn('upload-key-123');
            $file->method('getFileSize')->willReturn(12);
            $file->method('details')->willReturn([
                'metadata' => [
                    'folderId' => 'folder-uuid',
                    'ownerId' => 'owner-uuid',
                    'filename' => 'photo.jpg',
                    'filetype' => 'image/jpeg',
                ],
            ]);

            $event = $this->createUploadCompleteEvent($file);

            $this->commandBus->method('dispatch')->willReturn(new Envelope(new stdClass()));

            $this->logger->expects(self::exactly(2))
                ->method('info')
            ;

            ($this->subscriber)($event);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function createUploadCompleteEvent(File $file): UploadComplete
    {
        // TusPhp\Request has a method named "method" which PHPUnit cannot mock
        // We use createConfiguredMock without any method configuration
        $request = new class extends Request {
            public function __construct() {}
        };
        $response = new class extends Response {
            public function __construct() {}
        };

        return new UploadComplete($file, $request, $response);
    }
}
