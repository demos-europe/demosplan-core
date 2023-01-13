<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Filesystem;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;

abstract class FilesystemFlysystemAdapter implements FilesystemOperator
{
    /**
     * @var FilesystemOperator
     */
    private $filesystemOperator;

    public function __construct(FilesystemOperator $filesystemOperator)
    {
        $this->filesystemOperator = $filesystemOperator;
    }

    public function fileExists(string $location): bool
    {
        return $this->filesystemOperator->fileExists($location);
    }

    public function read(string $location): string
    {
        return $this->filesystemOperator->read($location);
    }

    public function readStream(string $location)
    {
        return $this->filesystemOperator->readStream($location);
    }

    public function listContents(
        string $location,
        bool $deep = self::LIST_SHALLOW
    ): DirectoryListing {
        return $this->filesystemOperator->listContents($location, $deep);
    }

    public function lastModified(string $path): int
    {
        return $this->filesystemOperator->lastModified($path);
    }

    public function fileSize(string $path): int
    {
        return $this->filesystemOperator->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->filesystemOperator->mimeType($path);
    }

    public function visibility(string $path): string
    {
        return $this->filesystemOperator->visibility($path);
    }

    public function write(string $location, string $contents, array $config = []): void
    {
        $this->filesystemOperator->write($location, $contents, $config);
    }

    public function writeStream(string $location, $contents, array $config = []): void
    {
        $this->filesystemOperator->writeStream($location, $contents, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->filesystemOperator->setVisibility($path, $visibility);
    }

    public function delete(string $location): void
    {
        $this->filesystemOperator->delete($location);
    }

    public function deleteDirectory(string $location): void
    {
        $this->filesystemOperator->deleteDirectory($location);
    }

    public function createDirectory(string $location, array $config = []): void
    {
        $this->filesystemOperator->createDirectory($location, $config);
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->filesystemOperator->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->filesystemOperator->copy($source, $destination, $config);
    }

    public function directoryExists(string $location): bool
    {
        return $this->filesystemOperator->directoryExists($location);
    }

    public function has(string $location): bool
    {
        return $this->filesystemOperator->has($location);
    }
}
