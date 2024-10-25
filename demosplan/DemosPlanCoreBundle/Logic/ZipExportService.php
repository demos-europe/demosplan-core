<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Exception\InvalidParameterTypeException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Patchwork\Utf8;
use PhpOffice\PhpWord\Writer\PDF;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\Exception\FileNotFoundException;
use ZipStream\Exception\FileNotReadableException;
use ZipStream\ZipStream;

class ZipExportService
{
    /**
     * @var array<int,string>
     */
    private $filesAdded = [];

    public function __construct(private readonly FileService $fileService, private readonly LoggerInterface $logger)
    {
    }

    /**
     * Creates a response that can be returned by a controller action that provides a ZIP file to the
     * requesting user.
     *
     * The ZIP file is given as parameter to the $fillZipFunction callable so it can be filled with data.
     *
     * @param callable(ZipStream):void $fillZipFunction
     */
    public function buildZipStreamResponse(string $name, callable $fillZipFunction): StreamedResponse
    {
        return new StreamedResponse(function () use ($name, $fillZipFunction): void {
            $zip = new ZipStream(
                // do not compress files
                defaultDeflateLevel: -1,
                sendHttpHeaders: true,
                outputName: $name,
                contentDisposition: 'attachment',
                contentType: 'application/zip',
            );
            $fillZipFunction($zip);

            $zip->finish();
        });
    }

    /**
     * @throws FileNotFoundException
     * @throws FileNotReadableException
     */
    public function addFileToZipStream(string $filePath, string $zipPath, ZipStream $zip): void
    {
        $zip->addFileFromPath($zipPath, $filePath, compressionMethod: CompressionMethod::STORE);

        $this->logger->info('Added File to Zip');
    }

    public function addFileToZip(
        string $folderPath,
        FileInfo $fileInfo,
        ZipStream $zip,
        string $fileNamePrefix,
    ): void {
        $path = Utf8::toAscii($folderPath.$fileNamePrefix.$fileInfo->getFileName());
        $pathHash = md5((string) $path);
        if (in_array($pathHash, $this->filesAdded, true)) {
            $this->logger->warning('File already present in Zip', ['path' => $path]);

            return;
        }
        $this->filesAdded[] = $pathHash;

        $this->logger->info(
            'Try to add File to Zip',
            [
                'hash'         => $fileInfo->getHash(),
                'name'         => $fileInfo->getFileName(),
                'absolutePath' => $fileInfo->getAbsolutePath(),
            ]
        );

        $this->addFileToZipStream($fileInfo->getAbsolutePath(), $path, $zip);
    }

    public function addFilePathToZipStream(string $filePath, string $zipPath, ZipStream $zip): void
    {
        try {
            $fileInfo = $this->fileService->getFileInfoFromFileString($filePath);
            $path = Utf8::toAscii($zipPath.'/'.$fileInfo->getFileName());
            $this->addFileToZipStream($fileInfo->getAbsolutePath(), $path, $zip);
            $this->logger->info(
                'Added File to Zip.',
                [
                    'hash'         => $fileInfo->getHash(),
                    'name'         => $fileInfo->getFileName(),
                    'absolutePath' => $fileInfo->getAbsolutePath(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->warning('Could not add file to Zip',
                [
                    'file'      => $filePath,
                    'path'      => $zipPath,
                    'exception' => $e->getMessage(),
                ]);
        }
    }

    public function addStringToZipStream(string $filename, string $string, ZipStream $zip): void
    {
        $streamRead = fopen('php://temp', 'rwb');
        fwrite($streamRead, $string);
        rewind($streamRead);
        $zip->addFileFromStream(Utf8::toAscii($filename), $streamRead);
    }

    public function addFiles(
        string $fileNamePrefix,
        DemosFilesystem $fs,
        string $fileFolderPath,
        ZipStream $zip,
        string ...$fileStrings,
    ): void {
        foreach ($fileStrings as $fileString) {
            try {
                $fileInfo = $this->fileService->getFileInfoFromFileString($fileString);

                // check whether file exists
                if (!$fs->exists($fileInfo->getAbsolutePath())) {
                    $this->logger->warning('Could not add file to Zip. File does not exist',
                        [
                            'fileString' => $fileString,
                        ]
                    );
                    continue;
                }

                $this->addFileToZip($fileFolderPath, $fileInfo, $zip, $fileNamePrefix);
            } catch (Exception) {
                $this->logger->warning('Could not add file to Zip',
                    [
                        'fileString' => $fileString,
                        'path'       => '',
                    ]
                );
            }
        }
    }

    /**
     * @param WriterInterface|PDF $writer
     */
    public function addWriterToZipStream($writer, string $pathInZip, ZipStream $zipStream, string $tmpPrefix, string $tmpSuffix): void
    {
        $this->checkIfSavable($writer);

        $temporaryFullFilePath = $this->writeToTemporaryFilePath($writer, $tmpPrefix, $tmpSuffix);
        $this->addFileToZipStream($temporaryFullFilePath, $pathInZip, $zipStream);
    }

    /**
     * @param WriterInterface|PDF $writer
     *
     * @return string the path the temporary file was written into
     */
    private function writeToTemporaryFilePath(object $writer, string $prefix, string $suffix): string
    {
        $this->checkIfSavable($writer);

        $date = date('YmdHis');
        $randomInt = random_int(0, mt_getrandmax());
        $tempFileName = sprintf('%s%s_%s%s', $prefix, $date, $randomInt, $suffix);
        $temporaryFullFilePath = DemosPlanPath::getTemporaryPath($tempFileName);
        // `PDF` doesn't implement `WriterInterface`, but has a `save` method available via
        // magic `call` method
        $writer->save($temporaryFullFilePath);

        return $temporaryFullFilePath;
    }

    private function checkIfSavable(object $writer): void
    {
        if (!($writer instanceof WriterInterface || $writer instanceof PDF)) {
            throw InvalidParameterTypeException::fromTypes($writer::class, [WriterInterface::class, PDF::class]);
        }
    }
}
