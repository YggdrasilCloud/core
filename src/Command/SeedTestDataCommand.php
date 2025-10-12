<?php

declare(strict_types=1);

namespace App\Command;

use App\Photo\Application\Command\CreateFolder\CreateFolderCommand;
use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:seed:test',
    description: 'Seeds test data for E2E tests'
)]
final class SeedTestDataCommand extends Command
{
    private const DEFAULT_OWNER_ID = 'default-owner-uuid';
    private const FIXTURES_DIR = '/app/fixtures/photos';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Only allow in test environment
        if ($_ENV['APP_ENV'] !== 'test') {
            $io->error('This command can only run in test environment (APP_ENV=test)');

            return Command::FAILURE;
        }

        $io->title('Seeding E2E Test Data');

        // Step 1: Purge database
        $io->section('Purging database...');
        $this->purgeDatabase();
        $io->success('Database purged');

        // Step 2: Create test folders
        $io->section('Creating test folders...');
        $folder1Id = $this->createFolder('Vacation Photos 2024');
        $folder2Id = $this->createFolder('Family Memories');
        $folder3Id = $this->createFolder('Empty Folder');
        $io->success('Created 3 test folders');

        // Step 3: Upload test photos
        $io->section('Uploading test photos...');
        $photosUploaded = $this->uploadTestPhotos($folder1Id);
        $io->success(sprintf('Uploaded %d test photos to "%s"', $photosUploaded, 'Vacation Photos 2024'));

        $io->success('Test data seeded successfully!');
        $io->info([
            'Database: Purged',
            'Folders: 3 created',
            "Photos: {$photosUploaded} uploaded",
            '',
            'You can now run E2E tests.',
        ]);

        return Command::SUCCESS;
    }

    private function purgeDatabase(): void
    {
        // Delete in correct order (foreign keys)
        $this->connection->executeStatement('TRUNCATE TABLE photo CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE folder CASCADE');
    }

    private function createFolder(string $name): string
    {
        $folderId = Uuid::v7()->toString();

        $command = new CreateFolderCommand(
            folderId: $folderId,
            folderName: $name,
            ownerId: self::DEFAULT_OWNER_ID,
        );

        $this->messageBus->dispatch($command);

        return $folderId;
    }

    private function uploadTestPhotos(string $folderId): int
    {
        $fixturesDir = self::FIXTURES_DIR;

        // Fallback to project dir if Docker volume not mounted
        if (!is_dir($fixturesDir)) {
            $fixturesDir = $this->projectDir.'/tests/e2e/fixtures/photos';
        }

        if (!is_dir($fixturesDir)) {
            return 0;
        }

        $photos = glob("{$fixturesDir}/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG}", GLOB_BRACE) ?: [];
        $photosUploaded = 0;

        foreach ($photos as $photoPath) {
            $fileName = basename($photoPath);

            // Skip README files
            if (str_contains(strtolower($fileName), 'readme')) {
                continue;
            }

            $fileStream = fopen($photoPath, 'rb');
            if ($fileStream === false) {
                continue;
            }

            $mimeType = mime_content_type($photoPath) ?: 'application/octet-stream';
            $sizeInBytes = filesize($photoPath) ?: 0;

            $command = new UploadPhotoToFolderCommand(
                photoId: Uuid::v7()->toString(),
                folderId: $folderId,
                ownerId: self::DEFAULT_OWNER_ID,
                fileName: $fileName,
                fileStream: $fileStream,
                mimeType: $mimeType,
                sizeInBytes: $sizeInBytes,
            );

            $this->messageBus->dispatch($command);

            fclose($fileStream);
            ++$photosUploaded;
        }

        return $photosUploaded;
    }
}
