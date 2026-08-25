<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Export;

/**
 * The stored result of a background export: what to fetch it by, and what to call it on download.
 */
class StoredExportFile
{
    public function __construct(
        private readonly string $fileHash,
        private readonly string $fileName,
    ) {
    }

    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
