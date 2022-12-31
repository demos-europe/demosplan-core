<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\ExternalFileSaver\Functional;

use Intervention\Image\Exception\NotReadableException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Base\FunctionalTestCase;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Logic\ExternalFileSaver;
use demosplan\DemosPlanCoreBundle\Logic\Router;
use demosplan\DemosPlanCoreBundle\Logic\UrlFileReader;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;

class ExternalFileSaverTest extends FunctionalTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * @var ExternalFileSaver
     */
    protected $sut;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->makeClient();// self::createClient();
        $this->currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $this->sut = self::$container->get(ExternalFileSaver::class);
        $this->router = self::$container->get(Router::class);
    }

    public function testSaveExternalFile(): void
    {
        self::markSkippedForCIIntervention();

        $this->mockUrlFileReader();
        // The url is not important, except for the file name
        $localImage = $this->sut->save('irrelevant-url-because-results-are-mocked/fff.png');
        $this->setProcedureAndLogin();
        $localImageUrl = $this->router->generate(
            'core_file_procedure',
            [
                'procedureId' => $this->currentProcedureService->getProcedure()->getId(),
                'hash'        => $localImage->getHash(),
            ],
            Router::ABSOLUTE_URL
        );

        $this->client->request('GET', $localImageUrl);
        $this->assertResponseIsSuccessful();
    }

    public function testWrongUrl()
    {
        self::markSkippedForCIIntervention();

        $externalImageUrl = 'https://wrong/url';
        $this->expectException(NotReadableException::class);
        $this->sut->save($externalImageUrl);
    }

    private function mockUrlFileReader(): void
    {
        $mockUrlFileReader = $this->getMockBuilder(UrlFileReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockUrlFileReader
            ->method('getFileContents')
            ->withAnyParameters()
            ->willReturn(
                file_get_contents(DemosPlanPath::getRootPath('tests/backend/core/ExternalFileSaver/Functional/fff.png'))
            );

        $this->sut->setUrlFileReader($mockUrlFileReader);
    }

    private function setProcedureAndLogin()
    {
        $this->currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference('testUser'));
    }
}
