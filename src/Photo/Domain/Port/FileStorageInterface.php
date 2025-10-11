<?php

declare(strict_types=1);

namespace App\Photo\Domain\Port;

interface FileStorageInterface
{
    /**
     * Store a file and return the storage path.
     *
     * @param resource $fileStream The file stream to store
     * @param string   $fileName   Original file name
     *
     * @return string The storage path where the file was stored
     */
    public function store($fileStream, string $fileName): string;

    /**
     * Delete a file from storage.
     *
     * @param string $storagePath The storage path of the file to delete
     */
    public function delete(string $storagePath): void;

    /**
     * Check if a file exists in storage.
     *
     * @param string $storagePath The storage path to check
     */
    public function exists(string $storagePath): bool;
}
