<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Storage;

use App\Photo\Domain\Port\FileStorageInterface;
use DateTimeImmutable;
use RuntimeException;

use function sprintf;

final readonly class LocalFileSystemStorage implements FileStorageInterface
{
    /**
     * @param string $storagePath Base storage path for photos
     *                            Future: consider adding folder-based subdirectories (e.g., photos/{folderId}/)
     */
    public function __construct(
        private string $storagePath,
    ) {}

    /**
     * @param resource $fileStream
     */
    public function store($fileStream, string $fileName): string
    {
        // Créer une structure de dossiers par date (YYYY/MM/DD)
        $date = new DateTimeImmutable();
        $relativePath = sprintf(
            '%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
        );

        $targetDirectory = $this->storagePath.'/'.$relativePath;

        // Créer le dossier s'il n'existe pas
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $targetDirectory));
        }

        // Générer un nom de fichier unique
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = sprintf(
            '%s_%s.%s',
            uniqid('', true),
            bin2hex(random_bytes(8)),
            $extension,
        );

        $targetPath = $targetDirectory.'/'.$uniqueFileName;

        // Copier le fichier
        $targetStream = fopen($targetPath, 'w');
        if ($targetStream === false) {
            throw new RuntimeException(sprintf('Failed to open target file: %s', $targetPath));
        }

        try {
            if (stream_copy_to_stream($fileStream, $targetStream) === false) {
                throw new RuntimeException('Failed to copy file stream');
            }
        } finally {
            fclose($targetStream);
        }

        // Retourner le chemin relatif
        return $relativePath.'/'.$uniqueFileName;
    }

    public function delete(string $storagePath): void
    {
        $fullPath = $this->storagePath.'/'.$storagePath;

        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                throw new RuntimeException(sprintf('Failed to delete file: %s', $fullPath));
            }
        }
    }

    public function exists(string $storagePath): bool
    {
        $fullPath = $this->storagePath.'/'.$storagePath;

        return file_exists($fullPath);
    }
}
