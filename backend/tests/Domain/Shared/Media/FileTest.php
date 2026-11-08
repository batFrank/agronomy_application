<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Media;

use App\Domain\Shared\Media\InvalidFileSizeException;
use App\Domain\Shared\Media\InvalidMediaConstraintsException;
use App\Domain\Shared\Media\InvalidMimeTypeException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Media\FileMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws InvalidFileSizeException
     * @throws InvalidMimeTypeException
     * @throws InvalidMediaConstraintsException
     */
    public function testCreateValidFile(): void
    {
        $file = FileMother::create();

        $this->assertInstanceOf(Uuid::class, $file->getId());
        $this->assertEquals('test-document.pdf', $file->getFilename());
        $this->assertEquals('/uploads/documents', $file->getPath());
        $this->assertEquals('/uploads/documents/test-document.pdf', $file->getFullPath());
        $this->assertEquals('application/pdf', $file->getMimeType());
        $this->assertEquals(1024 * 500, $file->getSize());
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    public function testFileIsImmutable(): void
    {
        $file = FileMother::create();
        $reflection = new \ReflectionClass($file);

        $this->assertTrue($reflection->isReadonly());
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidFileSizeException
     * @throws InvalidMediaConstraintsException
     */
    public function testInvalidMimeType(): void
    {
        $this->expectException(InvalidMimeTypeException::class);

        FileMother::create(mimeType: 'image/jpeg');
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     * @throws InvalidMediaConstraintsException
     */
    public function testFileSizeTooLarge(): void
    {
        $this->expectException(InvalidFileSizeException::class);

        FileMother::create(size: 101 * 1024 * 1024); // 101 MB
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidFileSizeException
     * @throws InvalidMimeTypeException
     * @throws InvalidMediaConstraintsException
     */
    public function testAllowedMimeTypes(): void
    {
        $file = FileMother::create();
        $allowedTypes = $file->getAllowedMimeTypes();

        $this->assertContains('application/pdf', $allowedTypes);
        $this->assertContains('application/msword', $allowedTypes);
        $this->assertContains('text/plain', $allowedTypes);
        $this->assertContains('text/csv', $allowedTypes);
        $this->assertContains('application/zip', $allowedTypes);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidFileSizeException
     * @throws InvalidMimeTypeException
     * @throws InvalidMediaConstraintsException
     */
    #[DataProvider('validMimeTypesProvider')]
    public function testValidMimeTypes(string $mimeType): void
    {
        $file = FileMother::create(mimeType: $mimeType);

        $this->assertEquals($mimeType, $file->getMimeType());
    }

    /**
     * @return array<string, array<string>>
     */
    public static function validMimeTypesProvider(): array
    {
        return [
            'pdf' => ['application/pdf'],
            'msword' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv'],
        ];
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    #[DataProvider('fullPathProvider')]
    public function testGetFullPath(string $path, string $filename, string $expected): void
    {
        $file = FileMother::create(filename: $filename, path: $path);

        $this->assertEquals($expected, $file->getFullPath());
    }

    /**
     * @return array<string, array<string>>
     */
    public static function fullPathProvider(): array
    {
        return [
            'without trailing slash' => ['/uploads/documents', 'report.pdf', '/uploads/documents/report.pdf'],
            'with trailing slash' => ['/uploads/documents/', 'report.pdf', '/uploads/documents/report.pdf'],
            'nested path without trailing slash' => ['/var/www/uploads/documents', 'report.pdf', '/var/www/uploads/documents/report.pdf'],
            'nested path with trailing slash' => ['/var/www/uploads/documents/', 'report.pdf', '/var/www/uploads/documents/report.pdf'],
        ];
    }
}
