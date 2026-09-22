<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DemosEurope\DemosplanAddon\Contracts\ValueObject\FileInfoInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

class FileInfo extends ValueObject implements FileInfoInterface
{
    /**
     * @var string
     */
    protected $hash;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var int
     */
    protected $fileSize;

    /**
     * @var string
     */
    protected $contentType;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $absolutePath;

    /**
     * @var Procedure|null
     */
    protected $procedure;

    public function __construct(
        string $hash,
        string $fileName,
        int $fileSize,
        string $contentType,
        string $path,
        string $absolutePath,
        ?Procedure $procedure,
    ) {
        $this->hash = $hash;
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->contentType = $contentType;
        $this->path = $path;
        $this->absolutePath = $absolutePath;
        $this->procedure = $procedure;

        $this->lock();
    }

    public function getHash(): string
    {
        return $this->getProperty('hash');
    }

    public function getFileName(): string
    {
        return $this->getProperty('fileName');
    }

    public function getFileSize(): int
    {
        return $this->getProperty('fileSize');
    }

    public function getContentType(): string
    {
        return $this->getProperty('contentType');
    }

    public function getPath(): string
    {
        return $this->getProperty('path');
    }

    public function getAbsolutePath(): string
    {
        return $this->getProperty('absolutePath');
    }

    public function getProcedure(): ?Procedure
    {
        return $this->getProperty('procedure');
    }
}
