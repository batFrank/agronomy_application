<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\FileSystem\File;
use App\Domain\Shared\FileSystem\FileSystemException;
use App\Domain\Shared\FileSystem\FileSystemType;
use App\Infrastructure\Shared\FileSystem\Factory\InMemoryFileSystemFactory;
use App\Infrastructure\Shared\FileSystem\FileSystemImplementation;
use App\Infrastructure\Shared\FileSystem\TmpFile;
use App\Infrastructure\Shared\FileSystem\TmpFileException;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSystemImplementation::class)]
class FileSystemImplementationTest extends TestCase
{
    #[DataProvider('provideMoveWhenDirectoryTransversalCases')]
    public function testMoveWhenDirectoryTransversal(File $source, File $destination): void
    {
        $service = $this->getFileSystemImplementation();
        $this->expectExceptionObject(FileSystemException::directoryTransversalIsNotAllowed());
        $service->move($source, $destination);
    }

    public static function provideMoveWhenDirectoryTransversalCases(): \Generator
    {
        yield 'when source has directory transversal' => [
            'source' => new File(
                path: '/test/directory/../another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
            'destination' => new File(
                path: '/test/directory/another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
        ];

        yield 'when destination has directory transversal' => [
            'source' => new File(
                path: '/test/directory/another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
            'destination' => new File(
                path: '/test/directory/../another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
        ];
    }

    public function testMoveWhenFileSystemIsNotAvailable(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $destination = new File(
            path: 'test/directory',
            filename: 'example.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PublicLocal,
        );

        $this->expectExceptionObject(FileSystemException::fileSystemNotFound(FileSystemType::PublicLocal->name));
        $service->move($file, $destination);
    }

    /**
     * @throws TmpFileException
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testMoveWhenSourceIsTmpFile(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $tmpFile = TmpFile::createFileFromContent('test file content');
        $destination = new File(
            path: 'test/directory',
            filename: 'example.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $result = $service->move($tmpFile, $destination);
        $this->assertCount(1, $fileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertEquals('test file content', $fileSystem->read('test/directory/example.txt'));

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(17, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testMoveWhenSourceAndDestinationIsInSameFileSystem(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $fileSystem->write('/test/directory/file1.txt', 'test content file in same filesystem');
        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 36,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $this->assertCount(1, $fileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertTrue($fileSystem->fileExists($file->getFullPath()));

        $destination = new File(
            path: '/test/another-directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $result = $service->move($file, $destination);

        $this->assertCount(0, $fileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertCount(1, $fileSystem->listContents(location: 'test/another-directory')->toArray());
        $this->assertFalse($fileSystem->fileExists($file->getFullPath()));
        $this->assertTrue($fileSystem->fileExists($destination->getFullPath()));

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(36, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testMoveWhenSourceAndDestinationAreInDifferentFileSystems(): void
    {
        $publicFileSystem = $this->getFileSystem();
        $privateFileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $privateFileSystem,
                FileSystemType::PublicLocal->toString() => $publicFileSystem,
            ]
        );

        $source = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 100,
            fileSystemType: FileSystemType::PublicLocal,
        );
        $publicFileSystem->write($source->getFullPath(), 'test content file');

        $this->assertCount(1, $publicFileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertEquals('test content file', $publicFileSystem->read($source->getFullPath()));

        $this->assertCount(0, $privateFileSystem->listContents(location: 'test/directory')->toArray());

        $destination = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $result = $service->move($source, $destination);

        $this->assertCount(0, $publicFileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertCount(1, $privateFileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertEquals('test content file', $privateFileSystem->read($destination->getFullPath()));

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(17, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    #[DataProvider('provideCopyWhenDirectoryTransversalCases')]
    public function testCopyWhenDirectoryTransversal(File $source, File $destination): void
    {
        $service = $this->getFileSystemImplementation();
        $this->expectExceptionObject(FileSystemException::directoryTransversalIsNotAllowed());
        $service->copy($source, $destination);
    }

    public static function provideCopyWhenDirectoryTransversalCases(): \Generator
    {
        yield 'when source has directory transversal' => [
            'source' => new File(
                path: '/test/directory/../another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
            'destination' => new File(
                path: '/test/directory/another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
        ];

        yield 'when destination has directory transversal' => [
            'source' => new File(
                path: '/test/directory/another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
            'destination' => new File(
                path: '/test/directory/../another-directory',
                filename: 'file.pdf',
                displayName: null,
                mimeType: 'application/pdf',
                fileSize: 100,
            ),
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     */
    public function testCopyWhenDestinationIsTmpFileSystem(): void
    {
        $service = $this->getFileSystemImplementation();

        $source = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::TmpLocal,
        );

        $destination = new File(
            path: '/test/directory',
            filename: 'file2.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::TmpLocal,
        );

        $this->expectExceptionObject(FileSystemException::destinationCannotBeTmpFileSystem());
        $service->copy($source, $destination);
    }

    /**
     * @throws FileSystemException
     */
    public function testCopyWhenIsSameSourceAndDestination(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $source = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $destination = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $this->expectException(InfrastructureException::class);
        $service->copy($source, $destination);
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testCopyInSameFileSystem(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $source = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 17,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $fileSystem->write($source->getFullPath(), 'test content file');
        $this->assertCount(1, $fileSystem->listContents(location: 'test')->toArray());
        $this->assertTrue($fileSystem->fileExists($source->getFullPath()));

        $destination = new File(
            path: '/test/another-directory',
            filename: 'file2.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $result = $service->copy($source, $destination);

        $this->assertCount(2, $fileSystem->
        listContents(location: 'test')->toArray());
        $this->assertTrue($fileSystem->fileExists($source->getFullPath()));
        $this->assertTrue($fileSystem->fileExists($destination->getFullPath()));
        $this->assertEquals('test content file', $fileSystem->read($source->getFullPath()));

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(17, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testCopyBetweenDifferentFileSystems(): void
    {
        $privateFileSystem = $this->getFileSystem();
        $publicFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $privateFileSystem,
                FileSystemType::PublicLocal->toString() => $publicFileSystem,
            ]
        );

        $this->assertCount(0, $privateFileSystem->listContents(location: 'test')->toArray());
        $this->assertCount(0, $publicFileSystem->listContents(location: 'test')->toArray());

        $source = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 17,
            fileSystemType: FileSystemType::PrivateLocal,
        );
        $privateFileSystem->write($source->getFullPath(), 'test content file');
        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());
        $this->assertTrue($privateFileSystem->fileExists($source->getFullPath()));

        $destination = new File(
            path: '/test/another-directory',
            filename: 'file2.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PublicLocal,
        );
        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());

        $result = $service->copy($source, $destination);

        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());
        $this->assertTrue($privateFileSystem->fileExists($source->getFullPath()));

        $this->assertCount(1, $publicFileSystem->listContents(location: 'test')->toArray());
        $this->assertTrue($publicFileSystem->fileExists($destination->getFullPath()));

        $this->assertEquals(
            $privateFileSystem->read($source->getFullPath()),
            $publicFileSystem->read($destination->getFullPath())
        );

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(17, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    /**
     * @throws TmpFileException
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testCopyWhenSourceIsTmpFile(): void
    {
        $fileSystem = $this->getFileSystem();
        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $fileSystem,
            ]
        );

        $tmpFile = TmpFile::createFileFromContent('test file content');
        $destination = new File(
            path: 'test/directory',
            filename: 'example.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        $result = $service->copy($tmpFile, $destination);
        $this->assertCount(1, $fileSystem->listContents(location: 'test/directory')->toArray());
        $this->assertEquals('test file content', $fileSystem->read('test/directory/example.txt'));

        $this->assertEquals($destination->getPath(), $result->getPath());
        $this->assertEquals($destination->getFilename(), $result->getFilename());
        $this->assertEquals($destination->getDisplayName(), $result->getDisplayName());
        $this->assertEquals('text/plain', $result->getMimeType());
        $this->assertEquals(17, $result->getFileSize());
        $this->assertEquals($destination->getFileSystemType(), $result->getFileSystemType());
    }

    public function testDeleteWhenDirectoryTransversal(): void
    {
        $service = $this->getFileSystemImplementation();

        $file = new File(
            path: '/test/directory/../another-directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 17,
        );

        $this->expectExceptionObject(FileSystemException::directoryTransversalIsNotAllowed());
        $service->delete($file);
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    public function testDeleteWhenFileDoesNotExist(): void
    {
        $privateFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $privateFileSystem,
            ]
        );

        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );
        $privateFileSystem->write($file->getFullPath(), 'test content file');

        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());

        $toDelete = new File(
            path: '/test/directory',
            filename: 'file2.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );

        try {
            $service->delete($toDelete);
            $this->fail('Expected FileSystemException was not thrown.');
        } catch (\Throwable $e) {
            $this->assertEquals(FileSystemException::pathDoesNotExist(path: $toDelete->getFullPath()), $e);
        }

        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testDeleteWhenFileExists(): void
    {
        $privateFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::PrivateLocal->toString() => $privateFileSystem,
            ]
        );

        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );
        $privateFileSystem->write($file->getFullPath(), 'test content file');

        $this->assertCount(1, $privateFileSystem->listContents(location: 'test')->toArray());
        $this->assertTrue($privateFileSystem->fileExists($file->getFullPath()));

        $service->delete($file);

        $this->assertCount(0, $privateFileSystem->listContents(location: 'test')->toArray());
        $this->assertFalse($privateFileSystem->fileExists($file->getFullPath()));
    }

    public function testPathExistsWhenDirectoryTransversal(): void
    {
        $service = $this->getFileSystemImplementation();

        $this->expectExceptionObject(FileSystemException::directoryTransversalIsNotAllowed());
        $service->pathExists('/test/directory/../another-directory/file.txt', FileSystemType::PrivateLocal);
    }

    /**
     * @throws InfrastructureException
     * @throws FileSystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function testPathExists(): void
    {
        $chunkFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $chunkFileSystem,
            ]
        );

        $chunkFileSystem->write('test/chunk/part1.txt', 'test content file');
        $this->assertCount(1, $chunkFileSystem->listContents(location: 'test')->toArray());

        // Test if file exists
        $this->assertTrue($service->pathExists('test/chunk/part1.txt', FileSystemType::ChunksLocal));

        // Test if directory exists
        $this->assertTrue($service->pathExists('test/', FileSystemType::ChunksLocal));

        // Test if file exists
        $this->assertFalse($service->pathExists('test/chunk/part2.txt', FileSystemType::ChunksLocal));

        $this->expectExceptionObject(FileSystemException::fileSystemNotFound(FileSystemType::PrivateLocal->name));
        $this->assertFalse($service->pathExists('test/chunk/part2.txt', FileSystemType::PrivateLocal));
    }

    public function testCopyToStreamWhenDirectoryTransversal(): void
    {
        $service = $this->getFileSystemImplementation();

        $file = new File(
            path: '/test/directory/../another-directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 17,
        );

        $output = @fopen('php://temp', 'w+b'); // In-Memory like file wrapper
        if (false === $output) {
            $this->fail('Failed to open temp resource.');
        }

        $this->expectExceptionObject(FileSystemException::directoryTransversalIsNotAllowed());
        $service->copyToStream($file, $output);
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     * @throws InfrastructureException
     * @throws FileSystemException
     */
    #[DataProvider('provideCopyToStreamCases')]
    public function testCopyToStreamWhenOutputIsNotAResource(mixed $output): void
    {
        $chunkFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $chunkFileSystem,
            ]
        );
        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::PrivateLocal,
        );
        $chunkFileSystem->write($file->getFullPath(), 'example content');

        $this->expectExceptionObject(new FileSystemException('$outputStream must be a resource.'));
        $service->copyToStream($file, $output);
    }

    public static function provideCopyToStreamCases(): \Generator
    {
        yield 'integer' => [
            'output' => 1,
        ];

        yield 'string' => [
            'output' => 'example',
        ];

        yield 'object' => [
            'output' => new \stdClass(),
        ];
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     * @throws InfrastructureException
     * @throws FileSystemException
     */
    public function testCopyToStream(): void
    {
        $chunkFileSystem = $this->getFileSystem();

        $service = $this->getFileSystemImplementation(
            fileSystems: [
                FileSystemType::ChunksLocal->toString() => $chunkFileSystem,
            ]
        );
        $file = new File(
            path: '/test/directory',
            filename: 'file1.txt',
            displayName: null,
            mimeType: 'text/plain',
            fileSize: 0,
            fileSystemType: FileSystemType::ChunksLocal,
        );
        $chunkFileSystem->write($file->getFullPath(), 'example content');

        $output = @fopen('php://temp', 'w+b'); // In-Memory like file wrapper
        if (false === $output) {
            $this->fail('Failed to open temp resource.');
        }

        $service->copyToStream($file, $output);

        // Validate if content is in stream
        rewind($output); // Reset pointer of the stream
        $content = @stream_get_contents($output);
        if (false === $content) {
            $this->fail('Failed to read temp resource.');
        }
        $this->assertEquals('example content', $content);
    }

    /**
     * @param array<string, Filesystem> $fileSystems
     */
    private function getFileSystemImplementation(
        array $fileSystems = [],
    ): FileSystemImplementation {
        return new FileSystemImplementation(
            fileSystems: $fileSystems,
        );
    }

    private function getFileSystem(): Filesystem
    {
        return new InMemoryFileSystemFactory()();
    }
}
