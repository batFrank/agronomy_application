<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\FileSystem\ChunkFile;
use App\Domain\Shared\FileSystem\ChunkUploadException;
use App\Domain\Shared\FileSystem\FileSystemInterface;
use App\Domain\Shared\FileSystem\FileSystemType;
use App\Infrastructure\Shared\FileSystem\ChunkUploadImplementation;
use App\Infrastructure\Shared\FileSystem\Factory\InMemoryFileSystemFactory;
use App\Infrastructure\Shared\FileSystem\FileSystemImplementation;
use App\Infrastructure\Shared\FileSystem\TmpFile;
use App\Infrastructure\Shared\FileSystem\TmpFileException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChunkUploadImplementation::class)]
class ChunkUploadImplementationTest extends TestCase
{
    /**
     * @throws TmpFileException
     * @throws \App\Domain\Shared\FileSystem\FileSystemException
     * @throws FilesystemException
     */
    public function testHandleWhenIsFirstPart(): void
    {
        $fileSystem = $this->getFileSystem();
        $fileSystemImplementation = new FileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $fileSystem,
            ]
        );
        $service = $this->getService(
            fileSystemInterface: $fileSystemImplementation,
            fileSystem: $fileSystem
        );

        $directory = md5('test-directory');

        $this->assertCount(0, $fileSystem->listContents($directory)->toArray());

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('test content'),
            currentPart: 1,
            totalParts: 3,
            identifier: 'test-directory'
        );

        $service->handle($file);

        $uploadedParts = $fileSystem->listContents($directory)->toArray();
        $this->assertCount(1, $uploadedParts);
        $this->assertEquals("$directory/part_001", $uploadedParts[0]['path']);
        $this->assertEquals('test content', $fileSystem->read($uploadedParts[0]['path']));
    }

    public function testHandleWhenIsSecondPartButNoFirstPartHasBeenUploaded(): void
    {
        $fileSystem = $this->getFileSystem();
        $fileSystemImplementation = new FileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $fileSystem,
            ]
        );
        $service = $this->getService(
            fileSystemInterface: $fileSystemImplementation,
            fileSystem: $fileSystem
        );

        $directory = md5('test-directory');

        $this->assertCount(0, $fileSystem->listContents($directory)->toArray());

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('test content'),
            currentPart: 2,
            totalParts: 3,
            identifier: 'test-directory'
        );

        $this->expectExceptionObject(ChunkUploadException::uploadedPartsInconsistency());
        $service->handle($file);
    }

    public function testUploadFirstPartButDirectoryContainsOtherParts(): void
    {
        $fileSystem = $this->getFileSystem();
        $fileSystemImplementation = new FileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $fileSystem,
            ]
        );
        $service = $this->getService(
            fileSystemInterface: $fileSystemImplementation,
            fileSystem: $fileSystem
        );

        $directory = md5('test-directory');

        $fileSystem->write("$directory/2", 'part2');
        $fileSystem->write("$directory/3", 'part3');

        $this->assertCount(2, $fileSystem->listContents($directory)->toArray());

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('part1'),
            currentPart: 1,
            totalParts: 3,
            identifier: 'test-directory'
        );

        $service->handle($file);

        $uploadedParts = $fileSystem->listContents($directory)->toArray();
        $this->assertCount(1, $uploadedParts);
        $this->assertEquals("$directory/part_001", $uploadedParts[0]['path']);
        $this->assertEquals('part1', $fileSystem->read($uploadedParts[0]['path']));
    }

    public function testHandleSecondPart(): void
    {
        $fileSystem = $this->getFileSystem();
        $fileSystemImplementation = new FileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $fileSystem,
            ]
        );
        $service = $this->getService(
            fileSystemInterface: $fileSystemImplementation,
            fileSystem: $fileSystem
        );

        $directory = md5('test-directory');

        $fileSystem->write("$directory/part_001", 'part1');

        $this->assertCount(1, $fileSystem->listContents($directory)->toArray());

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('part2'),
            currentPart: 2,
            totalParts: 3,
            identifier: 'test-directory'
        );

        $service->handle($file);

        $uploadedParts = $fileSystem->listContents($directory)->toArray();
        $this->assertCount(2, $uploadedParts);
        $this->assertEquals("$directory/part_001", $uploadedParts[0]['path']);
        $this->assertEquals('part1', $fileSystem->read($uploadedParts[0]['path']));
        $this->assertEquals("$directory/part_002", $uploadedParts[1]['path']);
        $this->assertEquals('part2', $fileSystem->read($uploadedParts[1]['path']));
    }

    /**
     * @throws TmpFileException
     * @throws InfrastructureException
     * @throws \App\Domain\Shared\FileSystem\FileSystemException
     * @throws ChunkUploadException
     */
    public function testMergeWhenNoUploadedParts(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getService(fileSystem: $fileSystem);

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('test'),
            currentPart: 5,
            totalParts: 5,
            identifier: 'test-directory'
        );

        $this->expectExceptionObject(ChunkUploadException::noUploadedParts());
        $service->merge($file);
    }

    /**
     * @throws TmpFileException
     * @throws InfrastructureException
     * @throws \App\Domain\Shared\FileSystem\FileSystemException
     * @throws FilesystemException
     * @throws ChunkUploadException
     */
    public function testMerge(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getService(fileSystem: $fileSystem);

        $directory = md5('test-directory');

        $fileSystem->write("$directory/2", 'part2');
        $fileSystem->write("$directory/1", 'part1');
        $fileSystem->write("$directory/5", 'part5');
        $fileSystem->write("$directory/3", 'part3');
        $fileSystem->write("$directory/4", 'part4');

        $file = new ChunkFile(
            file: TmpFile::createFileFromContent('test'),
            currentPart: 5,
            totalParts: 5,
            identifier: 'test-directory'
        );

        $result = $service->merge($file);

        $resource = fopen($result->getPath(), 'rb');
        if (false === $resource) {
            $this->fail('Failed to open stream');
        }
        $content = @stream_get_contents($resource);
        $this->assertEquals('part1part2part3part4part5', $content);
    }

    /**
     * @throws \ReflectionException
     * @throws FilesystemException
     */
    public function testGetUploadedParts(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getService(fileSystem: $fileSystem);

        $directory = 'test';

        $reflection = new \ReflectionClass(ChunkUploadImplementation::class);
        $method = $reflection->getMethod('getUploadedParts');
        $uploadedParts = $method->invoke($service, $directory);

        $this->assertCount(0, $uploadedParts);

        $fileSystem->write("$directory/part_02", 'part2');
        $fileSystem->write("$directory/part_01", 'part1');
        $fileSystem->write("$directory/part_11", 'part1');
        $fileSystem->write("$directory/part_03", 'part3');

        $uploadedParts = $method->invoke($service, $directory);

        $this->assertCount(4, $uploadedParts);
        $this->assertEquals("$directory/part_01", $uploadedParts[0]['path']);
        $this->assertEquals("$directory/part_02", $uploadedParts[1]['path']);
        $this->assertEquals("$directory/part_03", $uploadedParts[2]['path']);
        $this->assertEquals("$directory/part_11", $uploadedParts[3]['path']);
    }

    private function getService(
        ?FileSystemInterface $fileSystemInterface = null,
        ?Filesystem $fileSystem = null,
    ): ChunkUploadImplementation {
        if (null === $fileSystemInterface) {
            $fileSystemInterface = $this->createMock(FileSystemInterface::class);
            $fileSystemInterface->expects($this->never())
                ->method($this->anything());
        }

        return new ChunkUploadImplementation(
            fileSystemInterface: $fileSystemInterface,
            chunkFileSystem: $fileSystem ?? $this->getFileSystem(),
        );
    }

    private function getFileSystem(): Filesystem
    {
        return new InMemoryFileSystemFactory()(true);
    }
}
