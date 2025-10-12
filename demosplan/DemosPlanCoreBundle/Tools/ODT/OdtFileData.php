<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

/**
 * Value object representing extracted ODT file data.
 *
 * Contains all the content and metadata extracted from an ODT file.
 */
class OdtFileData
{
    public function __construct(
        public readonly ?string $contentXml,
        public readonly ?string $stylesXml,
        public readonly string $tempDir,
        public readonly string $originalPath,
    ) {
    }

    public function hasContent(): bool
    {
        return null !== $this->contentXml;
    }

    public function hasStyles(): bool
    {
        return null !== $this->stylesXml;
    }
}
