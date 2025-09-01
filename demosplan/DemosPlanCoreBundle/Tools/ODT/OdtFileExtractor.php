<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

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
        private readonly ?ZipArchive $zipArchive = null
    ) {
    }

    /**
     * Extract ODT file content and return structured data.
     */
    public function extractContent(string $odtFilePath): OdtFileData
    {
        $zip = $this->zipArchive ?? new ZipArchive();

        if ($zip->open($odtFilePath) !== true) {
            throw new \Exception('Unable to open ODT file.');
        }

        $contentXml = $zip->getFromName('content.xml');
        $stylesXml = $zip->getFromName('styles.xml');

        // Extract all pictures to a temporary folder using DemosPlanPath
        $tempDir = DemosPlanPath::getTemporaryPath('odt_' . basename($odtFilePath, '.odt'));
        $zip->extractTo($tempDir);
        $zip->close();

        // Return false as null for consistency
        return new OdtFileData(
            contentXml: $contentXml !== false ? $contentXml : null,
            stylesXml: $stylesXml !== false ? $stylesXml : null,
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
