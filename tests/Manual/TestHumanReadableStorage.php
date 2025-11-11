<?php

declare(strict_types=1);

namespace App\Tests\Manual;

use App\Photo\Application\Command\CreateFolder\CreateFolderCommand;
use App\Photo\Application\Command\RenameFolder\RenameFolderCommand;
use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Manual test to verify human-readable file storage structure.
 *
 * Run with: vendor/bin/phpunit tests/Manual/TestHumanReadableStorage.php
 */
final class TestHumanReadableStorage extends KernelTestCase
{
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $container = self::getContainer();
        $this->commandBus = $container->get(MessageBusInterface::class);
    }

    public function testCompleteWorkflow(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();

        // 1. Create root folder "Vacances"
        $vacancesFolderId = Uuid::v7()->toRfc4122();
        $this->commandBus->dispatch(new CreateFolderCommand(
            folderId: $vacancesFolderId,
            folderName: 'Vacances',
            ownerId: $ownerId,
            parentId: null,
        ));
        echo "\n✓ Created folder: Vacances\n";

        // 2. Create subfolder "Été 2024"
        $eteFolderId = Uuid::v7()->toRfc4122();
        $this->commandBus->dispatch(new CreateFolderCommand(
            folderId: $eteFolderId,
            folderName: 'Été 2024',
            ownerId: $ownerId,
            parentId: $vacancesFolderId,
        ));
        echo "✓ Created subfolder: Vacances/Été 2024\n";

        // 3. Upload first photo "plage.jpg"
        $photo1Id = Uuid::v7()->toRfc4122();
        [$stream1, $size1] = $this->createTestImage();
        $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
            photoId: $photo1Id,
            folderId: $eteFolderId,
            ownerId: $ownerId,
            fileName: 'plage.jpg',
            fileStream: $stream1,
            mimeType: 'image/jpeg',
            sizeInBytes: $size1,
        ));
        echo "✓ Uploaded photo: Vacances/Été 2024/plage.jpg\n";

        // 4. Upload second photo with same name to test collision
        $photo2Id = Uuid::v7()->toRfc4122();
        [$stream2, $size2] = $this->createTestImage();
        $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
            photoId: $photo2Id,
            folderId: $eteFolderId,
            ownerId: $ownerId,
            fileName: 'plage.jpg',
            fileStream: $stream2,
            mimeType: 'image/jpeg',
            sizeInBytes: $size2,
        ));
        echo "✓ Uploaded photo with collision: Vacances/Été 2024/plage (1).jpg\n";

        // 5. Upload third photo with same name
        $photo3Id = Uuid::v7()->toRfc4122();
        [$stream3, $size3] = $this->createTestImage();
        $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
            photoId: $photo3Id,
            folderId: $eteFolderId,
            ownerId: $ownerId,
            fileName: 'plage.jpg',
            fileStream: $stream3,
            mimeType: 'image/jpeg',
            sizeInBytes: $size3,
        ));
        echo "✓ Uploaded photo with collision: Vacances/Été 2024/plage (2).jpg\n";

        // 6. Rename folder
        $this->commandBus->dispatch(new RenameFolderCommand(
            folderId: $eteFolderId,
            newFolderName: 'Été 2025',
        ));
        echo "✓ Renamed folder: Vacances/Été 2024 → Vacances/Été 2025\n";

        // 7. Verify physical structure
        echo "\n📁 Physical structure:\n";
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $storagePath = $projectDir.'/var/storage/photos';

        $this->printDirectoryTree($storagePath, '');

        // Assertions
        self::assertDirectoryExists($storagePath.'/Vacances');
        self::assertDirectoryExists($storagePath.'/Vacances/Été 2025');
        self::assertFileExists($storagePath.'/Vacances/Été 2025/plage.jpg');
        self::assertFileExists($storagePath.'/Vacances/Été 2025/plage (1).jpg');
        self::assertFileExists($storagePath.'/Vacances/Été 2025/plage (2).jpg');

        echo "\n✅ All assertions passed!\n";
    }

    /**
     * @return array{resource, int} [stream, sizeInBytes]
     */
    private function createTestImage(): array
    {
        $image = imagecreatetruecolor(100, 100);
        imagefilledrectangle($image, 0, 0, 100, 100, imagecolorallocate($image, 255, 0, 0));

        $stream = fopen('php://memory', 'r+b');
        imagejpeg($image, $stream);
        imagedestroy($image);

        // Get actual size before rewinding
        $size = (int) ftell($stream);
        rewind($stream);

        return [$stream, $size];
    }

    private function printDirectoryTree(string $path, string $indent): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path.'/'.$item;
            if (is_dir($fullPath)) {
                echo $indent."📁 $item/\n";
                $this->printDirectoryTree($fullPath, $indent.'  ');
            } else {
                $size = filesize($fullPath);
                echo $indent."📄 $item (".number_format($size)." bytes)\n";
            }
        }
    }
}
