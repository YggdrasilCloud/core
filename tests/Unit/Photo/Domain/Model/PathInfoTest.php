<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\PathInfo;
use PHPUnit\Framework\TestCase;

final class PathInfoTest extends TestCase
{
    public function testFromPathParsesStandardPath(): void
    {
        $pathInfo = PathInfo::fromPath('/var/www/uploads/photo.jpg');

        self::assertSame('/var/www/uploads', $pathInfo->getDirname());
        self::assertSame('photo', $pathInfo->getFilename());
        self::assertSame('jpg', $pathInfo->getExtension());
    }

    public function testFromPathHandlesPathWithoutExtension(): void
    {
        $pathInfo = PathInfo::fromPath('/var/www/uploads/document');

        self::assertSame('/var/www/uploads', $pathInfo->getDirname());
        self::assertSame('document', $pathInfo->getFilename());
        self::assertNull($pathInfo->getExtension());
    }

    public function testFromPathHandlesRootDirectory(): void
    {
        $pathInfo = PathInfo::fromPath('/file.txt');

        self::assertSame('/', $pathInfo->getDirname());
        self::assertSame('file', $pathInfo->getFilename());
        self::assertSame('txt', $pathInfo->getExtension());
    }

    public function testFromPathHandlesRelativePath(): void
    {
        $pathInfo = PathInfo::fromPath('uploads/photo.png');

        self::assertSame('uploads', $pathInfo->getDirname());
        self::assertSame('photo', $pathInfo->getFilename());
        self::assertSame('png', $pathInfo->getExtension());
    }

    public function testFromPathHandlesCurrentDirectory(): void
    {
        $pathInfo = PathInfo::fromPath('./photo.gif');

        self::assertSame('.', $pathInfo->getDirname());
        self::assertSame('photo', $pathInfo->getFilename());
        self::assertSame('gif', $pathInfo->getExtension());
    }

    public function testFromPathHandlesFileWithMultipleDots(): void
    {
        $pathInfo = PathInfo::fromPath('/var/www/backup.tar.gz');

        self::assertSame('/var/www', $pathInfo->getDirname());
        self::assertSame('backup.tar', $pathInfo->getFilename());
        self::assertSame('gz', $pathInfo->getExtension());
    }

    public function testFromPathHandlesFilenameOnly(): void
    {
        $pathInfo = PathInfo::fromPath('photo.jpg');

        self::assertSame('.', $pathInfo->getDirname());
        self::assertSame('photo', $pathInfo->getFilename());
        self::assertSame('jpg', $pathInfo->getExtension());
    }

    public function testGetDirnameOrEmptyReturnsEmptyForCurrentDirectory(): void
    {
        $pathInfo = PathInfo::fromPath('photo.jpg');

        self::assertSame('', $pathInfo->getDirnameOrEmpty());
    }

    public function testGetDirnameOrEmptyReturnsPathForOtherDirectories(): void
    {
        $pathInfo = PathInfo::fromPath('/var/www/photo.jpg');

        self::assertSame('/var/www', $pathInfo->getDirnameOrEmpty());
    }

    public function testHasExtensionReturnsTrueWhenExtensionExists(): void
    {
        $pathInfo = PathInfo::fromPath('photo.jpg');

        self::assertTrue($pathInfo->hasExtension());
    }

    public function testHasExtensionReturnsFalseWhenNoExtension(): void
    {
        $pathInfo = PathInfo::fromPath('document');

        self::assertFalse($pathInfo->hasExtension());
    }

    public function testFromPathHandlesEmptyFilename(): void
    {
        $pathInfo = PathInfo::fromPath('/var/www/.htaccess');

        self::assertSame('/var/www', $pathInfo->getDirname());
        self::assertSame('', $pathInfo->getFilename());
        self::assertSame('htaccess', $pathInfo->getExtension());
    }
}
