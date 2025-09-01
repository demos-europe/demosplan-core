<?php
declare(strict_types=1);

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
        public readonly string $originalPath
    ) {
    }

    public function hasContent(): bool
    {
        return $this->contentXml !== null;
    }

    public function hasStyles(): bool
    {
        return $this->stylesXml !== null;
    }
}