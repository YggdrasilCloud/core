<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Infrastructure\Storage;

use App\File\Infrastructure\Storage\StorageConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class StorageConfigTest extends TestCase
{
    #[Test]
    public function itCreatesConfigWithDriverAndOptions(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage', 'permissions' => '0755']);

        self::assertSame('local', $config->driver);
        self::assertSame(['root' => '/var/storage', 'permissions' => '0755'], $config->options);
    }

    #[Test]
    public function itCreatesConfigWithDriverOnly(): void
    {
        $config = new StorageConfig('s3');

        self::assertSame('s3', $config->driver);
        self::assertSame([], $config->options);
    }

    #[Test]
    public function itThrowsExceptionForEmptyDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage driver cannot be empty');

        new StorageConfig('');
    }

    #[Test]
    public function itGetsOptionValue(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage']);

        self::assertSame('/var/storage', $config->get('root'));
    }

    #[Test]
    public function itReturnsNullForMissingOption(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage']);

        self::assertNull($config->get('nonexistent'));
    }

    #[Test]
    public function itReturnsDefaultValueForMissingOption(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage']);

        self::assertSame('/default/path', $config->get('nonexistent', '/default/path'));
    }

    #[Test]
    public function itReturnsOptionValueOverDefaultWhenBothExist(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage']);

        // Should return the option value, not the default
        self::assertSame('/var/storage', $config->get('root', '/default/path'));
    }

    #[Test]
    public function itChecksIfOptionExists(): void
    {
        $config = new StorageConfig('local', ['root' => '/var/storage']);

        self::assertTrue($config->has('root'));
        self::assertFalse($config->has('nonexistent'));
    }

    #[Test]
    public function itGetsIntegerOption(): void
    {
        $config = new StorageConfig('s3', ['max_retries' => '5', 'timeout' => '30']);

        self::assertSame(5, $config->getInt('max_retries'));
        self::assertSame(30, $config->getInt('timeout'));
    }

    #[Test]
    public function itReturnsNullForMissingIntOption(): void
    {
        $config = new StorageConfig('s3', ['bucket' => 'my-bucket']);

        self::assertNull($config->getInt('max_retries'));
    }

    #[Test]
    public function itReturnsDefaultIntForMissingOption(): void
    {
        $config = new StorageConfig('s3', ['bucket' => 'my-bucket']);

        self::assertSame(10, $config->getInt('max_retries', 10));
    }

    #[Test]
    public function itCastsStringToInt(): void
    {
        $config = new StorageConfig('local', ['port' => '8080']);

        self::assertSame(8080, $config->getInt('port'));
    }

    #[Test]
    public function itGetsBooleanOptionFromOne(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => '1']);

        self::assertTrue($config->getBool('use_ssl'));
    }

    #[Test]
    public function itGetsBooleanOptionFromTrue(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'true', 'verify' => 'TRUE']);

        self::assertTrue($config->getBool('use_ssl'));
        self::assertTrue($config->getBool('verify'));
    }

    #[Test]
    public function itGetsBooleanOptionFromYes(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'yes', 'verify' => 'YES']);

        self::assertTrue($config->getBool('use_ssl'));
        self::assertTrue($config->getBool('verify'));
    }

    #[Test]
    public function itGetsBooleanOptionFromOn(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'on', 'verify' => 'ON']);

        self::assertTrue($config->getBool('use_ssl'));
        self::assertTrue($config->getBool('verify'));
    }

    #[Test]
    public function itGetsBooleanOptionFromZero(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => '0']);

        self::assertFalse($config->getBool('use_ssl'));
    }

    #[Test]
    public function itGetsBooleanOptionFromFalse(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'false', 'verify' => 'FALSE']);

        self::assertFalse($config->getBool('use_ssl'));
        self::assertFalse($config->getBool('verify'));
    }

    #[Test]
    public function itGetsBooleanOptionFromNo(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'no', 'verify' => 'NO']);

        self::assertFalse($config->getBool('use_ssl'));
        self::assertFalse($config->getBool('verify'));
    }

    #[Test]
    public function itGetsBooleanOptionFromOff(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'off', 'verify' => 'OFF']);

        self::assertFalse($config->getBool('use_ssl'));
        self::assertFalse($config->getBool('verify'));
    }

    #[Test]
    public function itReturnsNullForMissingBoolOption(): void
    {
        $config = new StorageConfig('s3', ['bucket' => 'my-bucket']);

        self::assertNull($config->getBool('use_ssl'));
    }

    #[Test]
    public function itReturnsDefaultBoolForMissingOption(): void
    {
        $config = new StorageConfig('s3', ['bucket' => 'my-bucket']);

        self::assertTrue($config->getBool('use_ssl', true));
        self::assertFalse($config->getBool('verify', false));
    }

    #[Test]
    public function itCastsUnrecognizedStringToBool(): void
    {
        $config = new StorageConfig('s3', ['use_ssl' => 'enabled']);

        // Fallback to PHP's (bool) cast for unrecognized strings
        self::assertTrue($config->getBool('use_ssl'));
    }

    #[Test]
    public function itHandlesMixedCaseInBoolValues(): void
    {
        $config = new StorageConfig('s3', [
            'ssl' => 'TrUe',
            'verify' => 'YeS',
            'debug' => 'On',
            'cache' => 'FaLsE',
        ]);

        self::assertTrue($config->getBool('ssl'));
        self::assertTrue($config->getBool('verify'));
        self::assertTrue($config->getBool('debug'));
        self::assertFalse($config->getBool('cache'));
    }

    #[Test]
    public function itGetsBooleanTrueForEachTruthyValue(): void
    {
        // Test each truthy value individually to catch MatchArmRemoval mutations
        $config1 = new StorageConfig('test', ['flag' => '1']);
        $config2 = new StorageConfig('test', ['flag' => 'true']);
        $config3 = new StorageConfig('test', ['flag' => 'yes']);
        $config4 = new StorageConfig('test', ['flag' => 'on']);

        self::assertTrue($config1->getBool('flag'), 'Failed for "1"');
        self::assertTrue($config2->getBool('flag'), 'Failed for "true"');
        self::assertTrue($config3->getBool('flag'), 'Failed for "yes"');
        self::assertTrue($config4->getBool('flag'), 'Failed for "on"');
    }

    #[Test]
    public function itGetsBooleanFalseForEachFalsyValue(): void
    {
        // Test each falsy value individually to catch MatchArmRemoval mutations
        $config1 = new StorageConfig('test', ['flag' => '0']);
        $config2 = new StorageConfig('test', ['flag' => 'false']);
        $config3 = new StorageConfig('test', ['flag' => 'no']);
        $config4 = new StorageConfig('test', ['flag' => 'off']);

        self::assertFalse($config1->getBool('flag'), 'Failed for "0"');
        self::assertFalse($config2->getBool('flag'), 'Failed for "false"');
        self::assertFalse($config3->getBool('flag'), 'Failed for "no"');
        self::assertFalse($config4->getBool('flag'), 'Failed for "off"');
    }

    #[Test]
    public function itDistinguishesBetweenExistingValueAndDefault(): void
    {
        $config1 = new StorageConfig('test', ['value' => 'exists']);
        $config2 = new StorageConfig('test', []);

        // When value exists, should return the value, not the default
        self::assertSame('exists', $config1->get('value', 'default'));
        // When value doesn't exist, should return the default
        self::assertSame('default', $config2->get('value', 'default'));

        // Test that empty string is still a valid value different from default
        $config3 = new StorageConfig('test', ['value' => '']);
        self::assertSame('', $config3->get('value', 'default'));
    }
}
