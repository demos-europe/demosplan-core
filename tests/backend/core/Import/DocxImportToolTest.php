<?php

namespace Tests\Core\Import;

use DemosEurope\DemosplanAddon\Contracts\Tools\DocxAddonImporterInterface;
use Symfony\Component\HttpFoundation\File\File;
use Tests\Base\FunctionalTestCase;

class DocxImportToolTest extends FunctionalTestCase
{
    protected $sut;



    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(DocxAddonImporterInterface::class);
    }

    public function testDocxImport()
    {
        $filePath = __DIR__ . '/MoritzTollesAnschreiben.docx';
        $file = new File($filePath);
        $result = $this->sut->import($file, '');
        var_dump($result);
    }

}
