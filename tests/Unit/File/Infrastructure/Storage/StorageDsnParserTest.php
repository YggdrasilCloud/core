<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Infrastructure\Storage;

use App\File\Infrastructure\Storage\StorageConfig;
use App\File\Infrastructure\Storage\StorageDsnParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class StorageDsnParserTest extends TestCase
{
    private StorageDsnParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StorageDsnParser();
    }

    #[Test]
    public function itParsesLocalDsnWithRootOption(): void
    {
        $dsn = 'storage://local?root=/var/storage';

        $config = $this->parser->parse($dsn);

        self::assertInstanceOf(StorageConfig::class, $config);
        self::assertSame('local', $config->driver);
        self::assertSame('/var/storage', $config->get('root'));
    }

    #[Test]
    public function itParsesS3DsnWithMultipleOptions(): void
    {
        $dsn = 'storage://s3?bucket=my-bucket&region=eu-west-1';

        $config = $this->parser->parse($dsn);

        self::assertSame('s3', $config->driver);
        self::assertSame('my-bucket', $config->get('bucket'));
        self::assertSame('eu-west-1', $config->get('region'));
    }

    #[Test]
    public function itParsesFtpDsnWithCredentials(): void
    {
        $dsn = 'storage://ftp?host=ftp.example.com&username=user&password=pass&port=21';

        $config = $this->parser->parse($dsn);

        self::assertSame('ftp', $config->driver);
        self::assertSame('ftp.example.com', $config->get('host'));
        self::assertSame('user', $config->get('username'));
        self::assertSame('pass', $config->get('password'));
        self::assertSame('21', $config->get('port'));
    }

    #[Test]
    public function itParsesDsnWithoutQueryParams(): void
    {
        $dsn = 'storage://local';

        $config = $this->parser->parse($dsn);

        self::assertSame('local', $config->driver);
        self::assertEmpty($config->options);
    }

    #[Test]
    public function itParsesMinioDsnWithUrlEncodedEndpoint(): void
    {
        $dsn = 'storage://s3?bucket=my-bucket&region=us-east-1&endpoint=http%3A%2F%2Fminio:9000&pathStyle=true';

        $config = $this->parser->parse($dsn);

        self::assertSame('s3', $config->driver);
        self::assertSame('my-bucket', $config->get('bucket'));
        self::assertSame('http://minio:9000', $config->get('endpoint'));
        self::assertSame('true', $config->get('pathStyle'));
    }

    #[Test]
    public function itThrowsExceptionForEmptyDsn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage DSN cannot be empty');

        $this->parser->parse('');
    }

    #[Test]
    public function itThrowsExceptionForInvalidDsnFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN scheme must be "storage"');

        $this->parser->parse('not a valid url:::');
    }

    #[Test]
    public function itThrowsExceptionForWrongScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN scheme must be "storage"');

        $this->parser->parse('http://local?root=/var/storage');
    }

    #[Test]
    public function itThrowsExceptionForMissingScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN scheme must be "storage"');

        $this->parser->parse('local?root=/var/storage');
    }

    #[Test]
    public function itThrowsExceptionForMissingDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DSN format');

        $this->parser->parse('storage://?root=/var/storage');
    }

    /**
     * @param array<string, string> $expectedOptions
     */
    #[Test]
    #[DataProvider('provideItParsesVariousValidDsnsCases')]
    public function itParsesVariousValidDsns(string $dsn, string $expectedDriver, array $expectedOptions): void
    {
        $config = $this->parser->parse($dsn);

        self::assertSame($expectedDriver, $config->driver);
        foreach ($expectedOptions as $key => $expectedValue) {
            self::assertSame($expectedValue, $config->get($key), "Option '{$key}' mismatch");
        }
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function provideItParsesVariousValidDsnsCases(): iterable
    {
        yield 'local with absolute path' => [
            'storage://local?root=/var/storage',
            'local',
            ['root' => '/var/storage'],
        ];

        yield 'local with relative path' => [
            'storage://local?root=var/storage',
            'local',
            ['root' => 'var/storage'],
        ];

        yield 's3 minimal' => [
            'storage://s3?bucket=my-bucket',
            's3',
            ['bucket' => 'my-bucket'],
        ];

        yield 's3 with region' => [
            'storage://s3?bucket=my-bucket&region=eu-west-1',
            's3',
            ['bucket' => 'my-bucket', 'region' => 'eu-west-1'],
        ];

        yield 'ftp with host only' => [
            'storage://ftp?host=ftp.example.com',
            'ftp',
            ['host' => 'ftp.example.com'],
        ];

        yield 'gcs (future driver)' => [
            'storage://gcs?bucket=my-bucket&project=my-project',
            'gcs',
            ['bucket' => 'my-bucket', 'project' => 'my-project'],
        ];
    }

    #[Test]
    public function itThrowsExceptionWhenSchemeIsSetButWrong(): void
    {
        // This test specifically checks that isset($parsed['scheme']) && $parsed['scheme'] !== 'storage'
        // triggers the exception (kills LogicalOr mutation on line 43)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN scheme must be "storage", got "http"');

        $this->parser->parse('http://local?root=/var/storage');
    }

    #[Test]
    public function itParsesQueryParamsWithIntegerKeys(): void
    {
        // Test that integer keys are cast to strings (kills CastString mutations)
        $dsn = 'storage://local?root=/var/storage&0=value0&1=value1';

        $config = $this->parser->parse($dsn);

        self::assertSame('value0', $config->get('0'));
        self::assertSame('value1', $config->get('1'));
    }

    #[Test]
    public function itHandlesArrayValuesInQueryParams(): void
    {
        // Test that array values are converted to empty strings
        $dsn = 'storage://local?root=/var/storage&tags[]=foo&tags[]=bar';

        $config = $this->parser->parse($dsn);

        // Array values should be converted to empty string as per StorageDsnParser line 67
        self::assertSame('', $config->get('tags'));
    }
}
