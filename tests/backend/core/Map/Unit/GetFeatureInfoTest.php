<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Unit;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementGeoService;
use demosplan\DemosPlanCoreBundle\Services\DatasheetService;
use demosplan\DemosPlanCoreBundle\Services\Map\GetFeatureInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GetFeatureInfoTest extends TestCase
{
    private const CONFIGURED_URL =
        'https://wms.example/path?LAYERS=xplan&QUERY_LAYERS=xplan&SRS=EPSG:25832&STYLES=';

    private GetFeatureInfo $sut;

    /** @var HttpCall&MockObject */
    private HttpCall $httpCall;

    private ?string $capturedRequestMethod = null;
    private ?string $capturedRequestPath = null;
    /**
     * @var array<string, string>|null captured `$data` argument from the last HttpCall::request() call
     */
    private ?array $capturedRequestData = null;

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->createMock(GlobalConfigInterface::class);
        $config->method('getMapGetFeatureInfoUrl')->willReturn(self::CONFIGURED_URL);
        $config->method('useMapGetFeatureInfoUrlUseDb')->willReturn(false);
        $config->method('isMapGetFeatureInfoUrlGlobal')->willReturn(false);

        $this->httpCall = $this->createMock(HttpCall::class);
        $this->httpCall->method('request')->willReturnCallback(
            function (string $method, string $path, array $data): array {
                $this->capturedRequestMethod = $method;
                $this->capturedRequestPath = $path;
                $this->capturedRequestData = $data;

                return ['body' => ''];
            }
        );

        $this->sut = new GetFeatureInfo(
            $this->createMock(ContentService::class),
            $this->createMock(CurrentProcedureService::class),
            $this->createMock(DatasheetService::class),
            $config,
            $this->httpCall,
            $this->createMock(StatementGeoService::class),
        );
        $this->sut->setLogger(new NullLogger());
    }

    /**
     * Regression guard for DPLAN-17001. When the client supplies a `params`
     * payload that does not include LAYERS/QUERY_LAYERS, the params parsed
     * from the procedure's configured informationUrl must still reach the
     * outbound WMS request — otherwise XPlan feature queries return nothing.
     */
    public function testUrlLayersSurviveWhenClientParamsArePresent(): void
    {
        $this->sut->getFeatureInfo([
            'REQUEST' => 'GetFeatureInfo',
            'params'  => 'BBOX=100,200,300,400&X=1&Y=2',
        ]);

        self::assertSame('GET', $this->capturedRequestMethod);
        self::assertSame('https://wms.example/path', $this->capturedRequestPath);
        self::assertNotNull($this->capturedRequestData);
        self::assertSame('xplan', $this->capturedRequestData['LAYERS'] ?? null);
        self::assertSame('xplan', $this->capturedRequestData['QUERY_LAYERS'] ?? null);
        self::assertSame('100,200,300,400', $this->capturedRequestData['BBOX'] ?? null);
        self::assertSame('1', $this->capturedRequestData['X'] ?? null);
        self::assertSame('2', $this->capturedRequestData['Y'] ?? null);
    }

    /**
     * Client params win over URL params when both define the same key
     * (e.g. SRS), so the click-time coordinate system is honoured.
     */
    public function testClientParamsOverrideUrlParamsOnConflict(): void
    {
        $this->sut->getFeatureInfo([
            'REQUEST' => 'GetFeatureInfo',
            'params'  => 'SRS=EPSG:3857',
        ]);

        self::assertNotNull($this->capturedRequestData);
        self::assertSame('EPSG:3857', $this->capturedRequestData['SRS'] ?? null);
        self::assertSame('xplan', $this->capturedRequestData['LAYERS'] ?? null);
    }

    /**
     * When the client does not send a `params` payload at all, the URL
     * params still drive the outbound request.
     */
    public function testUrlLayersSurviveWhenClientParamsAreAbsent(): void
    {
        $this->sut->getFeatureInfo(['REQUEST' => 'GetFeatureInfo']);

        self::assertNotNull($this->capturedRequestData);
        self::assertSame('xplan', $this->capturedRequestData['LAYERS'] ?? null);
        self::assertSame('xplan', $this->capturedRequestData['QUERY_LAYERS'] ?? null);
    }
}
