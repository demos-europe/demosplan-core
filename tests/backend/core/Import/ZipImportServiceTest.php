<?php

namespace Tests\Core\Import;

use _PHPStan_93af41bf5\Symfony\Component\Finder\SplFileInfo;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Logic\ZipImportService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Symfony\Component\Finder\Finder;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class ZipImportServiceTest extends FunctionalTestCase
{
    private ?Finder $finder;
    private ?FileServiceInterface $fileService;
    private ?array $fileInfos;

    private ?Proxy $testProcedure;
    public function setUp(): void
    {
//        putenv('RABBITMQ_DSN=');
        parent::setUp();
        $this->testProcedure = ProcedureFactory::createOne();
        $this->fileService = $this->getContainer()->get(FileServiceInterface::class);
        $this->finder = Finder::create();
        $currentDirectoryPath = DemosPlanPath::getTestPath('backend/core/Import');
        $this->finder->files()->in($currentDirectoryPath);
        if ($this->finder->hasResults()) {
            /** @var SplFileInfo $file */
            foreach ($this->finder as $file) {
                if ('zip' === $file->getExtension()) {

                    echo var_dump($file->getFilename());

                    $fileInfo = new FileInfo(
                        $this->fileService->createHash(),
                        $file->getFilename(),
                        $file->getSize(),
                        'application/zip',
                        $file->getPath(),
                        $file->getRealPath(),
                        $this->testProcedure->object()
                    );
                    $this->fileInfos[] = $fileInfo;
                }
            }
        }
        $this->sut = $this->getContainer()->get(ZipImportService::class);
    }

    public function testDoEverythingWithZip()
    {
        $workingZip = $this->fileInfos[1];
        $errorZip = $this->fileInfos[0];

        $fileMap = $this->sut->doEverythingWithZip($workingZip, $this->testProcedure->getId());

        $test = 3;


    }
}
