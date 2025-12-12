<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Process;

use App\Shared\Infrastructure\Process\ProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests use `php -r` commands for portability across different OS/shells.
 */
#[CoversClass(ProcessRunner::class)]
final class ProcessRunnerTest extends TestCase
{
    private ProcessRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new ProcessRunner();
    }

    public function testRunSuccessfulCommand(): void
    {
        $result = $this->runner->run('php -r "fwrite(STDOUT, \'hello world\');"');

        self::assertTrue($result->isSuccessful());
        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('hello world', $result->stdout);
    }

    public function testRunFailingCommand(): void
    {
        $result = $this->runner->run('php -r "exit(42);"');

        self::assertFalse($result->isSuccessful());
        self::assertSame(42, $result->exitCode);
    }

    public function testRunCapturesStderr(): void
    {
        $result = $this->runner->run('php -r "fwrite(STDERR, \'error message\');"');

        self::assertTrue($result->isSuccessful());
        self::assertStringContainsString('error message', $result->stderr);
    }

    public function testRunTimesOut(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        // Sleep for 5 seconds but timeout after 1 second
        $this->runner->run('php -r "sleep(5);"', 1);
    }

    public function testGetOutputCombinesStdoutAndStderr(): void
    {
        $result = $this->runner->run('php -r "fwrite(STDOUT, \'out\'); fwrite(STDERR, \'err\');"');

        $output = $result->getOutput();
        self::assertStringContainsString('out', $output);
        self::assertStringContainsString('err', $output);
    }
}
