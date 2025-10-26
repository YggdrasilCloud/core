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

use function sprintf;

#[AsCommand(
    name: 'app:seed:test',
    description: 'Seeds test data for E2E tests'
)]
final class SeedTestDataCommand extends Command
{
    private const DEFAULT_OWNER_ID = '01936d3e-8f4a-7000-9000-000000000000';
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

        // Step 2: Create test folder hierarchy
        $io->section('Creating test folder hierarchy...');
        $foldersCreated = $this->createFolderHierarchy();
        $io->success(sprintf('Created %d test folders (including nested)', $foldersCreated['total']));

        // Step 3: Upload test photos
        $io->section('Uploading test photos...');
        $photosUploaded = $this->uploadTestPhotosToFolders($foldersCreated['folders']);
        $io->success(sprintf('Uploaded %d test photos to multiple folders', $photosUploaded));

        $io->success('Test data seeded successfully!');
        $io->info([
            'Database: Purged',
            'Folders: '.$foldersCreated['total'].' created ('
            .$foldersCreated['root'].' root, '
            .$foldersCreated['nested'].' nested)',
            "Photos: {$photosUploaded} uploaded",
            '',
            'Folder structure:',
            '  - Vacation Photos 2024',
            '    └─ Summer',
            '       ├─ Beach',
            '       └─ Mountains',
            '    └─ Winter',
            '  - Family Memories',
            '    ├─ Birthdays',
            '    └─ Holidays',
            '  - Work Projects',
            '  - Empty Folder',
            '',
            'You can now run E2E tests.',
        ]);

        return Command::SUCCESS;
    }

    private function purgeDatabase(): void
    {
        // Delete in correct order (foreign keys)
        // Use DELETE FROM instead of TRUNCATE for SQLite compatibility
        $this->connection->executeStatement('DELETE FROM photos');
        $this->connection->executeStatement('DELETE FROM folders');
    }

    private function createFolder(string $name, ?string $parentId = null): string
    {
        $folderId = Uuid::v7()->toString();

        $command = new CreateFolderCommand(
            folderId: $folderId,
            folderName: $name,
            ownerId: self::DEFAULT_OWNER_ID,
            parentId: $parentId,
        );

        $this->messageBus->dispatch($command);

        return $folderId;
    }

    /**
     * @return array{folders: array<string, string>, root: int, nested: int, total: int}
     */
    private function createFolderHierarchy(): array
    {
        $folders = [];
        $rootCount = 0;
        $nestedCount = 0;

        // Vacation Photos 2024 (with nested structure)
        $vacation = $this->createFolder('Vacation Photos 2024');
        $folders['vacation'] = $vacation;
        ++$rootCount;

        $summer = $this->createFolder('Summer', $vacation);
        $folders['summer'] = $summer;
        ++$nestedCount;

        $beach = $this->createFolder('Beach', $summer);
        $folders['beach'] = $beach;
        ++$nestedCount;

        $mountains = $this->createFolder('Mountains', $summer);
        $folders['mountains'] = $mountains;
        ++$nestedCount;

        $winter = $this->createFolder('Winter', $vacation);
        $folders['winter'] = $winter;
        ++$nestedCount;

        // Family Memories (with subfolders)
        $family = $this->createFolder('Family Memories');
        $folders['family'] = $family;
        ++$rootCount;

        $birthdays = $this->createFolder('Birthdays', $family);
        $folders['birthdays'] = $birthdays;
        ++$nestedCount;

        $holidays = $this->createFolder('Holidays', $family);
        $folders['holidays'] = $holidays;
        ++$nestedCount;

        // Work Projects (root only, no children)
        $work = $this->createFolder('Work Projects');
        $folders['work'] = $work;
        ++$rootCount;

        // Empty Folder (root only, no children, no photos)
        $empty = $this->createFolder('Empty Folder');
        $folders['empty'] = $empty;
        ++$rootCount;

        return [
            'folders' => $folders,
            'root' => $rootCount,
            'nested' => $nestedCount,
            'total' => $rootCount + $nestedCount,
        ];
    }

    /**
     * @param array<string, string> $folders
     */
    private function uploadTestPhotosToFolders(array $folders): int
    {
        $totalPhotosUploaded = 0;

        // Upload photos to multiple folders (not the empty one)
        $foldersToPopulate = [
            $folders['vacation'],  // Vacation Photos 2024
            $folders['beach'],     // Beach (nested)
            $folders['family'],    // Family Memories
            $folders['birthdays'], // Birthdays (nested)
            $folders['work'],      // Work Projects
        ];

        foreach ($foldersToPopulate as $folderId) {
            $count = $this->uploadTestPhotos($folderId);
            $totalPhotosUploaded += $count;
        }

        return $totalPhotosUploaded;
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

            $fileStream = fopen($photoPath, 'r');
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
