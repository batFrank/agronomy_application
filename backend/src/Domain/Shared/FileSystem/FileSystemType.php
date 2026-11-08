<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

enum FileSystemType
{
    /** Reference to a directory under public, for example: public/uploads dir. */
    case PublicLocal;

    /** Reference to private directory. (Private means, cannot be accessed with an url. A way must be provided for reading the file). */
    case PrivateLocal;

    /** Reference to var/chunks directory for parts temporal storage. */
    case ChunksLocal;

    /** Reference to temporal directory. Where files are expected to be removed at the end of the script */
    case TmpLocal;

    public function toString(): string
    {
        return match ($this) {
            self::PublicLocal => 'PUBLIC_LOCAL',
            self::PrivateLocal => 'PRIVATE_LOCAL',
            self::ChunksLocal => 'CHUNKS_LOCAL',
            self::TmpLocal => 'TMP_LOCAL',
        };
    }
}
