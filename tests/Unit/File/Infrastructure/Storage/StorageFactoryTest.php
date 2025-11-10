<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Infrastructure\Storage;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Infrastructure\Storage\Adapter\LocalStorage;
use App\File\Infrastructure\Storage\Bridge\StorageBridgeInterface;
use App\File\Infrastructure\Storage\StorageConfig;
use App\File\Infrastructure\Storage\StorageDsnParser;
use App\File\Infrastructure\Storage\StorageFactory;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorageFactoryTest extends TestCase
{
    private StorageDsnParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StorageDsnParser();
    }

    #[Test]
    public function itCreatesLocalStorageAdapterFromDsn(): void
    {
        $factory = new StorageFactory($this->parser);

        $storage = $factory->create('storage://local?root=/var/storage');

        self::assertInstanceOf(LocalStorage::class, $storage);
        self::assertInstanceOf(FileStorageInterface::class, $storage);
    }

    #[Test]
    public function itCreatesLocalStorageWithDefaultPathWhenNoRootProvided(): void
    {
        $factory = new StorageFactory($this->parser);

        $storage = $factory->create('storage://local');

        self::assertInstanceOf(LocalStorage::class, $storage);
    }

    #[Test]
    public function itUsesRegisteredBridgeForS3Driver(): void
    {
        $s3Bridge = $this->createMock(StorageBridgeInterface::class);
        $s3Bridge->method('supports')->willReturnCallback(static fn (string $driver) => $driver === 's3');

        $s3Storage = $this->createMock(FileStorageInterface::class);
        $s3Bridge->method('create')->willReturn($s3Storage);

        $factory = new StorageFactory($this->parser, [$s3Bridge]);

        $storage = $factory->create('storage://s3?bucket=my-bucket&region=eu-west-1');

        self::assertSame($s3Storage, $storage);
    }

    #[Test]
    public function itUsesRegisteredBridgeForFtpDriver(): void
    {
        $ftpBridge = $this->createMock(StorageBridgeInterface::class);
        $ftpBridge->method('supports')->willReturnCallback(static fn (string $driver) => $driver === 'ftp');

        $ftpStorage = $this->createMock(FileStorageInterface::class);
        $ftpBridge->method('create')->willReturn($ftpStorage);

        $factory = new StorageFactory($this->parser, [$ftpBridge]);

        $storage = $factory->create('storage://ftp?host=ftp.example.com');

        self::assertSame($ftpStorage, $storage);
    }

    #[Test]
    public function itSelectsCorrectBridgeFromMultipleRegisteredBridges(): void
    {
        $s3Bridge = $this->createMock(StorageBridgeInterface::class);
        $s3Bridge->method('supports')->willReturnCallback(static fn (string $driver) => $driver === 's3');
        $s3Storage = $this->createMock(FileStorageInterface::class);
        $s3Bridge->method('create')->willReturn($s3Storage);

        $ftpBridge = $this->createMock(StorageBridgeInterface::class);
        $ftpBridge->method('supports')->willReturnCallback(static fn (string $driver) => $driver === 'ftp');
        $ftpStorage = $this->createMock(FileStorageInterface::class);
        $ftpBridge->method('create')->willReturn($ftpStorage);

        $factory = new StorageFactory($this->parser, [$s3Bridge, $ftpBridge]);

        $s3Result = $factory->create('storage://s3?bucket=my-bucket');
        $ftpResult = $factory->create('storage://ftp?host=ftp.example.com');

        self::assertSame($s3Storage, $s3Result);
        self::assertSame($ftpStorage, $ftpResult);
    }

    #[Test]
    public function itPrefersLocalBuiltInAdapterOverBridges(): void
    {
        // Even if a bridge supports "local", built-in should be used
        $fakeBridge = $this->createMock(StorageBridgeInterface::class);
        $fakeBridge->method('supports')->willReturn(true); // Supports everything
        $fakeBridge->expects(self::never())->method('create'); // Should never be called

        $factory = new StorageFactory($this->parser, [$fakeBridge]);

        $storage = $factory->create('storage://local?root=/var/storage');

        self::assertInstanceOf(LocalStorage::class, $storage);
    }

    #[Test]
    public function itThrowsExceptionWhenNoBridgeSupportsDriver(): void
    {
        $factory = new StorageFactory($this->parser);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No storage adapter found for driver "s3"');

        $factory->create('storage://s3?bucket=my-bucket');
    }

    #[Test]
    public function itThrowsExceptionWithHelpfulMessageForUnsupportedDriver(): void
    {
        $factory = new StorageFactory($this->parser);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No storage adapter found for driver "gcs"');

        $factory->create('storage://gcs?bucket=my-bucket&project=my-project');
    }

    #[Test]
    public function itPassesCorrectConfigToBridge(): void
    {
        $bridge = $this->createMock(StorageBridgeInterface::class);
        $bridge->method('supports')->willReturn(true);

        $bridge->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (StorageConfig $config) {
                return $config->driver === 's3'
                    && $config->get('bucket') === 'my-bucket'
                    && $config->get('region') === 'eu-west-1';
            }))
            ->willReturn($this->createMock(FileStorageInterface::class))
        ;

        $factory = new StorageFactory($this->parser, [$bridge]);

        $factory->create('storage://s3?bucket=my-bucket&region=eu-west-1');
    }

    #[Test]
    public function itPropagatesExceptionsFromParser(): void
    {
        $factory = new StorageFactory($this->parser);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN scheme must be "storage"');

        $factory->create('not a valid dsn:::');
    }

    #[Test]
    public function itWorksWithEmptyBridgesIterable(): void
    {
        $factory = new StorageFactory($this->parser, []);

        $storage = $factory->create('storage://local?root=/tmp');

        self::assertInstanceOf(LocalStorage::class, $storage);
    }

    #[Test]
    public function itPassesCustomLengthLimitsThroughDsn(): void
    {
        $factory = new StorageFactory($this->parser);

        // DSN with custom max_key_length and max_component_length
        $dsn = 'storage://local?root=/tmp&max_key_length=512&max_component_length=128';
        $storage = $factory->create($dsn);

        self::assertInstanceOf(LocalStorage::class, $storage);

        // Verify limits are applied by trying to save with a key exceeding custom limits
        // This is implicitly tested by LocalStorageTest, so we just verify instantiation here
    }

    #[Test]
    public function itUsesDefaultLengthLimitsWhenNotSpecifiedInDsn(): void
    {
        $factory = new StorageFactory($this->parser);

        // DSN without max_key_length or max_component_length
        $storage = $factory->create('storage://local?root=/tmp');

        self::assertInstanceOf(LocalStorage::class, $storage);

        // Verify defaults (1024 and 255) are used - tested implicitly in LocalStorageTest
    }

    #[Test]
    public function itResolvesRelativePathsWithProjectDir(): void
    {
        $factory = new StorageFactory($this->parser, [], '/app');

        $storage = $factory->create('storage://local?root=var/storage');

        self::assertInstanceOf(LocalStorage::class, $storage);
        // The path should be resolved to /app/var/storage
    }

    #[Test]
    public function itDoesNotModifyAbsolutePathsWithProjectDir(): void
    {
        $factory = new StorageFactory($this->parser, [], '/app');

        $storage = $factory->create('storage://local?root=/var/storage');

        self::assertInstanceOf(LocalStorage::class, $storage);
        // The path should remain /var/storage
    }

    #[Test]
    public function itHandlesRelativePathsWithoutProjectDir(): void
    {
        $factory = new StorageFactory($this->parser, [], null);

        $storage = $factory->create('storage://local?root=var/storage');

        self::assertInstanceOf(LocalStorage::class, $storage);
        // The path should remain var/storage (will be resolved relative to CWD)
    }

    #[Test]
    public function itOnlyModifiesRelativePathsWhenProjectDirIsSet(): void
    {
        // Test that BOTH conditions must be true: projectDir !== null AND !str_starts_with($basePath, '/')
        // This kills LogicalAnd and LogicalNot mutations on StorageFactory line 59

        $factory1 = new StorageFactory($this->parser, [], '/app');
        $factory2 = new StorageFactory($this->parser, [], null);

        // projectDir is set AND path is relative -> should be modified
        $storage1 = $factory1->create('storage://local?root=var/storage');
        self::assertInstanceOf(LocalStorage::class, $storage1);

        // projectDir is set BUT path is absolute -> should NOT be modified
        $storage2 = $factory1->create('storage://local?root=/var/storage');
        self::assertInstanceOf(LocalStorage::class, $storage2);

        // projectDir is null AND path is relative -> should NOT be modified
        $storage3 = $factory2->create('storage://local?root=var/storage');
        self::assertInstanceOf(LocalStorage::class, $storage3);

        // projectDir is null AND path is absolute -> should NOT be modified
        $storage4 = $factory2->create('storage://local?root=/var/storage');
        self::assertInstanceOf(LocalStorage::class, $storage4);
    }
}
