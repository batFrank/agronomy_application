<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Shared\FileSystem;

use App\Infrastructure\Shared\FileSystem\TmpFile;
use App\Infrastructure\Shared\FileSystem\TmpFileException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TmpFile::class)]
class TmpFileTest extends TestCase
{
    private const string IMAGE_B64 = 'data:image/png;base64,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';

    /**
     * @throws TmpFileException
     */
    public function testCreateImageFileFromContent(): void
    {
        $result = TmpFile::createImageFileFromContent(self::IMAGE_B64);
        $this->assertEquals('image/png', $result->getMimeType());
    }

    /**
     * @throws TmpFileException
     */
    #[DataProvider('provideCreateImageFileFromFileExceptions')]
    public function testCreateImageFileFromFileExceptions(string $content, \Throwable $exception): void
    {
        $this->expectExceptionObject($exception);
        TmpFile::createImageFileFromContent($content);
    }

    public static function provideCreateImageFileFromFileExceptions(): \Generator
    {
        yield 'invalid base 64 content' => [
            'content' => 'invalid-b64',
            'exception' => TmpFileException::contentIsNotValid(),
        ];

        yield 'invalid image' => [
            'content' => 'data:image/png;base64,dGhpcyBpcyBhIHRlc3Q=',
            'exception' => TmpFileException::fileIsNotAValidImage(),
        ];
    }

    public function testCreateFileFromContent(): void
    {
        $result = TmpFile::createFileFromContent('test content');

        $this->assertEquals(
            'test content',
            file_get_contents($result->getPath())
        );

        $this->assertEquals(
            'text/plain',
            $result->getMimeType()
        );
    }
}
