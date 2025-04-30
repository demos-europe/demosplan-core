<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Router;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class RouterTest extends FunctionalTestCase
{
    use ProphecyTrait;

    protected $urlPrefixProcedure = '/verfahren/';
    protected $urlSuffixProcedure = '/public/detail';
    protected $urlPrefixReport = '/report/view/';

    /**
     * @dataProvider  generateUrlProvider
     */
    public function testGenerateUrl(string $generatedUrl, string $expectedUrl, array $params, int $referenceType)
    {
        $sut = $this->getSut($generatedUrl);
        $url = $sut->generate('dummyNameInputViaMock', $params, $referenceType);
        static::assertEquals($expectedUrl, $url);
    }

    /**
     * @return array<string,string,array<string,mixed>|,int>
     */
    public function generateUrlProvider(): array
    {
        $anyUrlSlash = 'http://irgendwas.de/';
        $anyUrl = 'http://irgendwas.de';
        $anyUrlFolder = 'http://irgendwas.de/folder/index.html';
        $anyUrlNetworkSlash = '//irgendwas.de/';
        $absolutePath = RouterInterface::ABSOLUTE_PATH;
        $networkPath = RouterInterface::NETWORK_PATH;

        return [
            [
                $anyUrlSlash,
                $anyUrlSlash,
                [],
                $absolutePath,
            ],
            [
                $anyUrl,
                $anyUrl,
                [],
                $absolutePath,
            ],
            [
                'https://irgendwas.de',
                $anyUrl,
                [],
                $absolutePath,
            ],
            [
                $anyUrlFolder,
                $anyUrlFolder,
                [],
                $absolutePath,
            ],
            [
                'https://irgendwas.de/folder/index.html',
                $anyUrlFolder,
                [],
                $absolutePath,
            ],
            [
                $anyUrlNetworkSlash,
                $anyUrlSlash,
                [],
                $networkPath,
            ],
            [
                $anyUrlNetworkSlash,
                $anyUrlSlash,
                [],
                $networkPath,
            ],
            [
                '//irgendwas.de',
                $anyUrl,
                [],
                $networkPath,
            ],
            [
                '//irgendwas.de?key=value&k=v&http=keepme&https=keepmetoo',
                'http://irgendwas.de?key=value&k=v&http=keepme&https=keepmetoo',
                [],
                $networkPath,
            ],
            [
                $anyUrlSlash,
                $anyUrlSlash,
                [],
                RouterInterface::ABSOLUTE_URL,
            ],
        ];
    }

    /**
     * @dataProvider  generateUrlPathPrefixProvider
     */
    public function testUrlPathPrefix(string $generatedUrl, string $expectedUrl, string $schema)
    {
        $sut = $this->getSut($generatedUrl, 'dialog', $schema);
        $url = $sut->generate('dummyNameInputViaMock');
        static::assertEquals($expectedUrl, $url);
    }

    /**
     * @return array<string,string,array<string,mixed>|,int>
     */
    public function generateUrlPathPrefixProvider(): array
    {
        $urlHttps = 'https://irgendwas.de/folder/index.html';
        $urlHttp = 'http://irgendwas.de/dialog/folder/index.html';

        return [
            [
                $urlHttps,
                $urlHttp,
                'http',
            ],
            // see T20601
            [
                $urlHttps,
                $urlHttp,
                'http',
            ],
        ];
    }

    public function testProcedureBeautify()
    {
        self::markSkippedForCIIntervention();
        $urlPrefix = 'https://irgendwas.de/verfahren/';
        $urlSuffix = '/something';

        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);

        $generatedUrl = $urlPrefix.$testProcedure->getShortUrl().$urlSuffix;
        $expectedUrl = $urlPrefix.$testProcedure->getShortUrl().$urlSuffix;
        $procedureShortUrl = $testProcedure->getShortUrl();
        $procedureId = $testProcedure->getId();
        // using dataProvider is not possible as fixture is needed
        $dataArray = [
            [
                'inputParams'  => [
                    'procedure' => $procedureShortUrl,
                ],
                'params'       => [
                    'procedure' => $procedureId,
                ],
            ],
            [
                'inputParams'  => [
                    'procedureId' => $procedureShortUrl,
                ],
                'params'       => [
                    'procedureId' => $procedureId,
                ],
            ],
            [
                'inputParams'  => [
                    'procedure' => $procedureShortUrl,
                ],
                'params'       => [
                    'procedure' => $procedureShortUrl,
                ],
            ],
            [
                'inputParams'  => [
                    'procedureId' => $procedureShortUrl,
                ],
                'params'       => [
                    'procedureId' => $procedureShortUrl,
                ],
            ],
        ];

        foreach ($dataArray as $data) {
            $sut = $this->getSut($generatedUrl, '', 'https', $testProcedure, $data['inputParams']);
            $url = $sut->generate('dummyNameInputViaMock', $data['params']);
            static::assertEquals($expectedUrl, $url);
        }
    }

    public function testProcedureDecodeMatch()
    {
        /** @var Router $sut */
        $sut = self::getContainer()->get(RouterInterface::class);
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);
        $dataArray = $this->getDataArrayMatch($testProcedure);
        foreach ($dataArray as $data) {
            $params = $sut->match($data['path']);
            static::assertEquals($testProcedure->getId(), $params[$data['param']]);
        }
    }

    public function testProcedureDecodeMatchRequest()
    {
        /** @var Router $sut */
        $sut = self::getContainer()->get(Router::class);
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);
        $dataArray = $this->getDataArrayMatch($testProcedure);

        foreach ($dataArray as $data) {
            $request = Request::create($data['path']);
            $params = $sut->matchRequest($request);
            static::assertEquals($testProcedure->getId(), $params[$data['param']]);
        }
    }

    protected function getSut(
        string $generatedUrl,
        string $pathPrefix = '',
        string $urlScheme = 'http',
        ?Procedure $procedure = null,
        array $params = [],
    ): Router {
        $mock = $this->prophesize(RouterInterface::class);
        $mock->generate('dummyNameInputViaMock', $params, RouterInterface::ABSOLUTE_PATH)
            ->willReturn($generatedUrl);
        $mock->generate('dummyNameInputViaMock', $params, RouterInterface::NETWORK_PATH)
            ->willReturn($generatedUrl);
        $mock->generate('dummyNameInputViaMock', $params, RouterInterface::ABSOLUTE_URL)
            ->willReturn($generatedUrl);
        $mockGlobalConfig = $this->prophesize(GlobalConfigInterface::class);
        $mockGlobalConfig->getUrlScheme()
            ->willReturn($urlScheme);
        $mockGlobalConfig->getUrlPathPrefix()
            ->willReturn($pathPrefix);
        $mockMethods = [
            new MockMethodDefinition('find', $procedure),
            new MockMethodDefinition('getProcedureBySlug', $procedure),
        ];
        $mockProcedureRepository = $this->getMock(ProcedureRepository::class, $mockMethods);

        return new Router($mockGlobalConfig->reveal(), $mockProcedureRepository, $mock->reveal());
    }

    private function getDataArrayMatch(Procedure $testProcedure): array
    {
        // using dataProvider is not possible as fixture is needed
        return [
            [
                'param' => 'procedure',
                'path'  => $this->urlPrefixProcedure.$testProcedure->getShortUrl(
                ).$this->urlSuffixProcedure,
            ],
            [
                'param' => 'procedure',
                'path'  => $this->urlPrefixProcedure.$testProcedure->getId(
                ).$this->urlSuffixProcedure,
            ],
            [
                'param' => 'procedureId',
                'path'  => $this->urlPrefixReport.$testProcedure->getShortUrl(),
            ],
            [
                'param' => 'procedureId',
                'path'  => $this->urlPrefixReport.$testProcedure->getShortUrl(),
            ],
        ];
    }
}
