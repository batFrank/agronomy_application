<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Media;

use App\Domain\Shared\Media\InvalidFileSizeException;
use App\Domain\Shared\Media\InvalidImageDimensionsException;
use App\Domain\Shared\Media\InvalidMediaConstraintsException;
use App\Domain\Shared\Media\InvalidMimeTypeException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Media\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    public function testCreateValidImage(): void
    {
        $image = ImageMother::create();

        $this->assertInstanceOf(Uuid::class, $image->getId());
        $this->assertEquals('test-image.jpg', $image->getFilename());
        $this->assertEquals('/uploads/images', $image->getPath());
        $this->assertEquals('/uploads/images/test-image.jpg', $image->getFullPath());
        $this->assertEquals('image/jpeg', $image->getMimeType());
        $this->assertEquals(1024 * 100, $image->getSize());
        $this->assertEquals(800, $image->getWidth());
        $this->assertEquals(600, $image->getHeight());
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    public function testImageIsImmutable(): void
    {
        $image = ImageMother::create();
        $reflection = new \ReflectionClass($image);

        $this->assertTrue($reflection->isReadonly());
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     */
    public function testInvalidMimeType(): void
    {
        $this->expectException(InvalidMimeTypeException::class);

        ImageMother::create(mimeType: 'application/pdf');
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    #[DataProvider('invalidDimensionsProvider')]
    public function testInvalidDimensions(?int $width, ?int $height): void
    {
        $this->expectException(InvalidImageDimensionsException::class);

        ImageMother::create(width: $width, height: $height);
    }

    /**
     * @return array<string, array<int|null>>
     */
    public static function invalidDimensionsProvider(): array
    {
        return [
            'width too small' => [0, 600],
            'width too large' => [10001, 600],
            'height too small' => [800, 0],
            'height too large' => [800, 10001],
        ];
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    public function testFileSizeTooLarge(): void
    {
        $this->expectException(InvalidFileSizeException::class);

        ImageMother::create(size: 11 * 1024 * 1024); // 11 MB
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    #[DataProvider('validMimeTypesProvider')]
    public function testValidMimeTypes(string $mimeType): void
    {
        $image = ImageMother::create(mimeType: $mimeType);

        $this->assertEquals($mimeType, $image->getMimeType());
    }

    /**
     * @return array<string, array<string>>
     */
    public static function validMimeTypesProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
        ];
    }

    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    #[DataProvider('fullPathProvider')]
    public function testGetFullPath(string $path, string $filename, string $expected): void
    {
        $image = ImageMother::create(filename: $filename, path: $path);

        $this->assertEquals($expected, $image->getFullPath());
    }

    /**
     * @return array<string, array<string>>
     */
    public static function fullPathProvider(): array
    {
        return [
            'without trailing slash' => ['/uploads/images', 'photo.jpg', '/uploads/images/photo.jpg'],
            'with trailing slash' => ['/uploads/images/', 'photo.jpg', '/uploads/images/photo.jpg'],
            'nested path without trailing slash' => ['/var/www/uploads/images', 'photo.jpg', '/var/www/uploads/images/photo.jpg'],
            'nested path with trailing slash' => ['/var/www/uploads/images/', 'photo.jpg', '/var/www/uploads/images/photo.jpg'],
        ];
    }
}
