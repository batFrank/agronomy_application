## File handling
### FileSystems
1. `PublicLocal` Storage under `<BaseDir>/public/uploads`. All files under this directory can be accessed by 
an url like `https://example.com/uploads/file`. Recommended for application assets or images, where access control is not
expected.
2. `PrivateLocal` Storage for private files. **Private** means that the file cannot be accessed directly from an url.
Storage under `<BaseDir>/Storage` in the main directory. The files must be served by the application, and access control can be
defined. Recommended for user private or confidential files.
3. `ChunksLocal` Temporal file storage for files uploaded in multiple parts. Storage under `<BaseDir>/var/tmp`. The files are expected
to be removed after all parts has been merged. Is recommended to clear the directory in conjunction with cache.
4. `TmpLocal` Storage under PHP tmp dir. Used for exceptional cases.

By default, only 4 fileSystems types has been defined. More can be added based on requirements, example: `PublicAmazonS3` or `PrivateAmazonS3`

### `FileSystemInterface`

Location: `src\Domain\Shared\FileSystem\FileSystemInterface`.  
The interface declare 5 methods
1. `move` Move a file from a source to a destination. The source file will be deleted and cannot be accessed after the method call
2. `copy` Copy a file from a source to a destination. Both files will be available.
3. `delete` Remove a file
4. `pathExists` Verify if a path (file or directory) exists in a specific file system.
5. `copyToStream` Copy the file content to a defined stream. Useful for downloads.

The interface can be expanded based on requirements.

### `ChunkUploadInterface`

**Location** `src\Domain\Shared\FileSystem\ChunkUploadInterface`.  
The interface declares 2 methods:
1. `handle` Save a file part. By default, all parts are stored in `ChunksLocal` filesystem. No validation can be provided
to the part.
2. `merge` Merge all file parts. The result file is stored in the `tmp` directory of `php`, that means, if the file is not
moved to another location, it will be removed at the end of the script.

## How to use
### Create a Command (CQRS)
```php
class ExampleCommand implements \App\Domain\Bus\Command {
    public function __construct(private readonly \App\Domain\Shared\FileSystem\File $file) {}
    
    public function getFile(): \App\Domain\Shared\FileSystem\File {
        return $this->file;
    }
}
```

### Create a command handler
```php
class ExampleCommandHandler {
    public function __construct(
        private readonly \App\Domain\Shared\FileSystem\FileSystemInterface $fileSystem,
        private readonly \App\Domain\Shared\Uuid\UuidGenerator $uuidGenerator,
    ) {}
  
    public function handle(ExampleCommand $command): void {
        $destination = new \App\Domain\Shared\FileSystem\File(
            path: '/dir1/example-dir',
            filename: sprintf('%s.pdf', $this->uuidGenerator->generate()->value()),
            displayName: 'A pdf example for display.pdf',
            mimeType: 'application/pdf',
            fileSize: 0,
            fileSystemType: \App\Domain\Shared\FileSystem\FileSystemType::PrivateLocal,
        );
        
        // Returns a new File with mimetype and fileSize updated to the real values of the file
        $destination = $this->fileSystem->move(
            source: $command->getFile(),
            destination: $destination
        );
        
        // Do database operations or validations for the file or convert to Image/MediaType file
    }
}
```

### Upload a single file
```php
readonly class ExampleController extends \App\Infrastructure\Bus\CommandBusAccessor {
    public function __invoke(\Symfony\Component\HttpFoundation\Request $request): \Symfony\Component\HttpFoundation\JsonResponse {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('example-file');
        if (null === $file) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(status: 400);
        }
        
        $file = \App\Infrastructure\Shared\FileSystem\TmpFile::createFromFile($uploadedFile);
        
        $this->execute(new ExampleCommand(
            file: $file,
        ));
        
        return new \Symfony\Component\HttpFoundation\JsonResponse(status: 200);
    }
}
```

### Upload a file in multiple parts
```php
readonly class ExampleController extends \App\Infrastructure\Bus\CommandBusAccessor {
    public function __invoke(
        \Symfony\Component\HttpFoundation\Request $request,
        \App\Domain\Shared\FileSystem\ChunkUploadInterface $chunkUpload,
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('example-file');
        if (null === $file) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(status: 400);
        }
        $currentPart = (int) $request->request->get('current-part', 1);
        $totalParts = (int) $request->request->get('total-parts', 1);
        $operationId = $request->headers->get('x-file-id');// Considered as file request, used to identify the next parts
        
        // Validate all variables
        
        $file = \App\Infrastructure\Shared\FileSystem\TmpFile::createFromFile($uploadedFile);
        
        $chunkFile = new \App\Domain\Shared\FileSystem\ChunkFile(
            file: $file,
            currentPart: $currentPart,
            totalParts: $totalParts,
            identifier: $operationId,
        );
        
        $chunkUpload->handle(file: $chunkFile);
        
        if ($chunkFile->getCurrentPart() < $chunkFile->getTotalParts()) {
            return new \Symfony\Component\HttpFoundation\Response();
        }
        
        // All files parts are uploaded
        $result = $chunkUpload->merge($chunkFile); // Use the last part to get all parts
        
        $this->execute(new ExampleCommand(
            file: $file,
        ));
        
        return new \Symfony\Component\HttpFoundation\JsonResponse(status: 200);
    }
}
```

### Download a file
```php
readonly class ExampleDownloadController extends \App\Infrastructure\Bus\QueryBusAccessor {
    public function __invoke(\App\Domain\Shared\FileSystem\FileSystemInterface $fileSystem): \Symfony\Component\HttpFoundation\StreamedResponse {
        /** @var \App\Domain\Shared\FileSystem\File $file */
        $file = $this->ask(new GetFileQueryExample());
        $output = @fopen('php://output', 'wb');
        if (false === $output) {
            return new \Symfony\Component\HttpFoundation\Response(status: 500);
        }
        
        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            callbackOrChunks: $fileSystem->copyToStream(file: $file, outputStream: $output),
            headers: [
                "Content-Transfer-Encoding" => 'binary',
                "Content-Type" => $file->getMimeType(),
                "Content-Disposition" => 'attachment; filename="' . $file->getDisplayName() ?? $file->getFilename() . '"',
                "Content-Length" => $file->getFileSize(),
            ]
        );
    }   
}
```
