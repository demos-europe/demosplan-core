<?php

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

/**
 * @method string         getHash()
 * @method string         getFileName()
 * @method int            getFileSize()
 * @method string         getContentType()
 * @method string         getPath()
 * @method string         getAbsolutePath()
 * @method Procedure|null getProcedure()
 */
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
        ?Procedure $procedure
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
}
