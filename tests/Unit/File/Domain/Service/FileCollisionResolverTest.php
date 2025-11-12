<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Domain\Service;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Domain\Service\FileCollisionResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileCollisionResolverTest extends TestCase
{
    private FileStorageInterface $storage;
    private FileCollisionResolver $resolver;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(FileStorageInterface::class);
        $this->resolver = new FileCollisionResolver($this->storage);
    }

    public function testResolveUniquePathReturnsOriginalWhenNoCollision(): void
    {
        $this->storage
            ->expects(self::once())
            ->method('exists')
            ->with('photos/Vacances/plage.jpg')
            ->willReturn(false)
        ;

        $result = $this->resolver->resolveUniquePath('photos/Vacances/plage.jpg');

        self::assertSame('photos/Vacances/plage.jpg', $result);
    }

    public function testResolveUniquePathAddsSuffixWhenCollision(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Vacances/plage.jpg' => true,  // Original exists
                    'photos/Vacances/plage (1).jpg' => false, // First suffix available
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Vacances/plage.jpg');

        self::assertSame('photos/Vacances/plage (1).jpg', $result);
    }

    public function testResolveUniquePathIncrementsUntilAvailable(): void
    {
        $this->storage
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Vacances/plage.jpg' => true,
                    'photos/Vacances/plage (1).jpg' => true,
                    'photos/Vacances/plage (2).jpg' => true,
                    'photos/Vacances/plage (3).jpg' => false, // This one is available
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Vacances/plage.jpg');

        self::assertSame('photos/Vacances/plage (3).jpg', $result);
    }

    public function testResolveUniquePathHandlesFileWithoutExtension(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Documents/README' => true,
                    'photos/Documents/README (1)' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Documents/README');

        self::assertSame('photos/Documents/README (1)', $result);
    }

    public function testResolveUniquePathHandlesMultipleDots(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Archives/backup.tar.gz' => true,
                    'photos/Archives/backup.tar (1).gz' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Archives/backup.tar.gz');

        self::assertSame('photos/Archives/backup.tar (1).gz', $result);
    }

    public function testResolveUniquePathHandlesRootPath(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photo.jpg' => true,
                    'photo (1).jpg' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photo.jpg');

        self::assertSame('photo (1).jpg', $result);
    }

    public function testResolveUniquePathHandlesNestedDirectories(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/2024/Vacances/Été/beach.jpg' => true,
                    'photos/2024/Vacances/Été/beach (1).jpg' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/2024/Vacances/Été/beach.jpg');

        self::assertSame('photos/2024/Vacances/Été/beach (1).jpg', $result);
    }

    public function testResolveUniquePathHandlesUnicodeFilenames(): void
    {
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Été/plage-🏖️.jpg' => true,
                    'photos/Été/plage-🏖️ (1).jpg' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Été/plage-🏖️.jpg');

        self::assertSame('photos/Été/plage-🏖️ (1).jpg', $result);
    }

    public function testResolveUniquePathThrowsExceptionAfterMaxAttempts(): void
    {
        // Mock storage to always return true (file always exists)
        $this->storage
            ->method('exists')
            ->willReturn(true)
        ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find unique filename after 1000 attempts for: photos/Vacances/plage.jpg');

        $this->resolver->resolveUniquePath('photos/Vacances/plage.jpg');
    }

    public function testResolveUniquePathHandlesExistingSuffixInFilename(): void
    {
        // Test that files already containing "(1)" in the name work correctly
        $this->storage
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(static function ($key) {
                return match ($key) {
                    'photos/Version (1).jpg' => true,
                    'photos/Version (1) (1).jpg' => false,
                    default => false,
                };
            })
        ;

        $result = $this->resolver->resolveUniquePath('photos/Version (1).jpg');

        self::assertSame('photos/Version (1) (1).jpg', $result);
    }

    public function testResolveUniquePathUsesExactlyMaxAttempts(): void
    {
        // Test that we attempt exactly MAX_ATTEMPTS (1000) times
        // This ensures the <= vs < boundary is correctly tested
        $callCount = 0;

        $this->storage
            ->method('exists')
            ->willReturnCallback(static function () use (&$callCount) {
                ++$callCount;

                // Return false on the 1001st call (original + 1000 attempts)
                return $callCount <= 1000;
            })
        ;

        $result = $this->resolver->resolveUniquePath('photo.jpg');

        // Should succeed with "photo (1000).jpg" at the 1000th attempt
        self::assertSame('photo (1000).jpg', $result);
        self::assertSame(1001, $callCount); // 1 original + 1000 attempts
    }
}
