<?php

declare(strict_types=1);

namespace App\File\Domain\Service;

use InvalidArgumentException;

use function mb_strlen;
use function preg_replace;
use function str_replace;
use function strtoupper;
use function trim;

/**
 * Sanitizes file and folder names for filesystem compatibility.
 *
 * Handles:
 * - Forbidden characters (Windows/Linux)
 * - Reserved names (Windows: CON, PRN, AUX, etc.)
 * - Length limits (255 chars per component)
 * - Empty names after sanitization
 */
final readonly class FileNameSanitizer
{
    /**
     * Characters forbidden on Windows filesystems.
     * Also includes null byte (\0) forbidden on Unix.
     */
    private const FORBIDDEN_CHARS = ['<', '>', ':', '"', '/', '\\', '|', '?', '*', "\0"];

    /**
     * Reserved names on Windows filesystems.
     * These cannot be used as filenames, even with extensions.
     */
    private const RESERVED_NAMES = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /**
     * Maximum length for a filename component (filesystem limit).
     */
    private const MAX_LENGTH = 255;

    /**
     * Replacement character for forbidden characters.
     */
    private const REPLACEMENT = '_';

    /**
     * Sanitizes a filename or folder name for filesystem compatibility.
     *
     * @param string $name The original name to sanitize
     *
     * @throws InvalidArgumentException If the name becomes empty after sanitization
     */
    public function sanitize(string $name): string
    {
        // Trim whitespace
        $sanitized = trim($name);

        if ($sanitized === '') {
            throw new InvalidArgumentException('Name cannot be empty or whitespace only');
        }

        // Replace forbidden characters
        $sanitized = str_replace(self::FORBIDDEN_CHARS, self::REPLACEMENT, $sanitized);

        // Remove control characters (ASCII 0-31 and 127)
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);
        assert($sanitized !== null);

        // Trim again after replacements
        $sanitized = trim($sanitized);

        if ($sanitized === '') {
            throw new InvalidArgumentException('Name contains only forbidden characters');
        }

        // Check for Windows reserved names
        $sanitized = $this->handleReservedNames($sanitized);

        // Trim trailing dots and spaces (forbidden on Windows)
        $sanitized = rtrim($sanitized, '. ');

        if ($sanitized === '') {
            throw new InvalidArgumentException('Name cannot consist only of dots and spaces');
        }

        // Enforce length limit
        if (mb_strlen($sanitized) > self::MAX_LENGTH) {
            $sanitized = mb_substr($sanitized, 0, self::MAX_LENGTH);
            // Trim again in case we cut in the middle of spaces/dots
            $sanitized = rtrim($sanitized, '. ');
        }

        return $sanitized;
    }

    /**
     * Handles Windows reserved names by prefixing them.
     */
    private function handleReservedNames(string $name): string
    {
        // Extract name without extension
        $dotPos = mb_strrpos($name, '.');
        $baseName = $dotPos !== false ? mb_substr($name, 0, $dotPos) : $name;
        $extension = $dotPos !== false ? mb_substr($name, $dotPos) : '';

        // Check if base name is reserved (case-insensitive)
        $upperBaseName = strtoupper($baseName);
        if (in_array($upperBaseName, self::RESERVED_NAMES, true)) {
            return self::REPLACEMENT.$name;
        }

        return $name;
    }
}
