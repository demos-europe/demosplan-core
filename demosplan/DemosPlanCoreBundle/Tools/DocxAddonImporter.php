<?php

namespace demosplan\DemosPlanCoreBundle\Tools;

use DemosEurope\DemosplanAddon\Contracts\Tools\DocxAddonImporterInterface;
use Symfony\Component\HttpFoundation\File\File;

class DocxAddonImporter implements DocxAddonImporterInterface
{
    public function __construct(private readonly DocxImporterInterface $docxImporter)
    {

    }

    public function importDocx(File $file, string $procedureId): array
    {
        return $this->docxImporter->importDocx($file, '', $procedureId, '');
    }
}
