<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service\ThumbnailStrategy;

use App\Photo\Domain\Service\ThumbnailStrategy\StlThumbnailStrategy;
use App\Shared\Infrastructure\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class StlThumbnailStrategyTest extends TestCase
{
    public function testSupportsStlMimeTypes(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $strategy = new StlThumbnailStrategy($processRunner, new NullLogger());

        self::assertTrue($strategy->supports('model/stl'));
        self::assertTrue($strategy->supports('application/sla'));
        self::assertTrue($strategy->supports('application/vnd.ms-pki.stl'));
        self::assertTrue($strategy->supports('application/x-navistyle'));
    }

    public function testDoesNotSupportOtherMimeTypes(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $strategy = new StlThumbnailStrategy($processRunner, new NullLogger());

        self::assertFalse($strategy->supports('image/jpeg'));
        self::assertFalse($strategy->supports('video/mp4'));
        self::assertFalse($strategy->supports('application/pdf'));
        self::assertFalse($strategy->supports('model/obj'));
    }

    /**
     * Strategy is always unavailable due to OSMesa rendering bugs in headless Docker.
     *
     * @see https://github.com/unlimitedbacon/stl-thumb - complex models produce noise
     */
    public function testIsAlwaysUnavailableDueToOsMesaBugs(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $strategy = new StlThumbnailStrategy($processRunner, new NullLogger());

        self::assertFalse($strategy->isAvailable());
    }

    public function testGenerateThrowsExceptionWhenNotAvailable(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $strategy = new StlThumbnailStrategy($processRunner, new NullLogger());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stl-thumb is not available');

        $strategy->generate('/path/to/input.stl', '/path/to/output.jpg');
    }
}
