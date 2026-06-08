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

use demosplan\DemosPlanCoreBundle\Exception\OdtProcessingException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

/**
 * Service responsible for extracting content from ODT files.
 *
 * Separates file system operations from the main ODT processing logic
 * following Single Responsibility Principle.
 */
class OdtFileExtractor
{
    public function __construct(
        private readonly ?ZipArchive $zipArchive = null,
    ) {
    }

    /**
     * Extract ODT file content and return structured data.
     */
    public function extractContent(string $odtFilePath): OdtFileData
    {
        $zip = $this->zipArchive ?? new ZipArchive();

        if (true !== $zip->open($odtFilePath)) {
            throw OdtProcessingException::unableToOpenFile($odtFilePath);
        }

        $contentXml = $zip->getFromName('content.xml');
        $stylesXml = $zip->getFromName('styles.xml');

        // Extract all pictures to a temporary folder using DemosPlanPath
        $tempDir = DemosPlanPath::getTemporaryPath('odt_'.basename($odtFilePath, '.odt'));

        // Extract with security validation to prevent zip slip attacks
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $filename = $zip->getNameIndex($i);
            if (false === $filename || str_ends_with($filename, '/')) {
                continue;
            }

            // Security check: prevent path traversal attacks
            if (str_contains($filename, '../') || str_starts_with($filename, '/')) {
                continue;
            }

            $zip->extractTo($tempDir, $filename);
        }
        $zip->close();

        // Return false as null for consistency
        return new OdtFileData(
            contentXml: false !== $contentXml ? $contentXml : null,
            stylesXml: false !== $stylesXml ? $stylesXml : null,
            tempDir: $tempDir,
            originalPath: $odtFilePath
        );
    }

    /**
     * Clean up extracted temporary files.
     */
    public function cleanup(string $tempDir): void
    {
        $fs = new Filesystem();
        if ($fs->exists($tempDir)) {
            $fs->remove($tempDir);
        }
    }
}
