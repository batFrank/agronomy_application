<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\FileSystem;

use App\Domain\Shared\FileSystem\File;
use App\Domain\Shared\FileSystem\FileSystemType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
class FileTest extends TestCase
{
    #[DataProvider('provideGetFullPathCases')]
    public function testGetFullPath(File $file, string $expectedFullPath): void
    {
        $this->assertEquals($expectedFullPath, $file->getFullPath());
    }

    public static function provideGetFullPathCases(): \Generator
    {
        yield 'example 1' => [
            'file' => new File(
                path: '/path/to/file',
                filename: 'file.txt',
                displayName: null,
                mimeType: 'text/plain',
                fileSize: 0,
                fileSystemType: FileSystemType::PrivateLocal,
            ),
            'expectedFullPath' => '/path/to/file/file.txt',
        ];

        yield 'example 2' => [
            'file' => new File(
                path: '/path/to/file/',
                filename: 'file.txt',
                displayName: null,
                mimeType: 'text/plain',
                fileSize: 0,
                fileSystemType: FileSystemType::PrivateLocal,
            ),
            'expectedFullPath' => '/path/to/file/file.txt',
        ];

        yield 'example 3' => [
            'file' => new File(
                path: '/path/to/file//',
                filename: 'file.txt',
                displayName: null,
                mimeType: 'text/plain',
                fileSize: 0,
                fileSystemType: FileSystemType::PrivateLocal,
            ),
            'expectedFullPath' => '/path/to/file/file.txt',
        ];

        yield 'example 4' => [
            'file' => new File(
                path: '/path/to/file////',
                filename: 'file.txt',
                displayName: null,
                mimeType: 'text/plain',
                fileSize: 0,
                fileSystemType: FileSystemType::PrivateLocal,
            ),
            'expectedFullPath' => '/path/to/file/file.txt',
        ];
    }
}
