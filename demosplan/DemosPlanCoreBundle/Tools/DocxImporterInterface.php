<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use Symfony\Component\HttpFoundation\File\File;

interface DocxImporterInterface
{
    /**
     * Import a docx file.
     *
     * @return array
     */
    public function importDocx(File $file, string $elementId, string $procedure, string $category): array;
}
