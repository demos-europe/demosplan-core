<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Map\CoordinateJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureMapSettingResourceType;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;

class ProcedureMapSettingTest extends FunctionalTestCase
{
    /** @var ProcedureMapSettingResourceType */
    protected $procedureMapSettingResourceType;

    /** @var MasterTemplateService */
    protected $masterTemplateService;

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /** @var CoordinateJsonConverter */
    protected $coordinateJsonConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $contentService = $this->getContainer()->get(ContentService::class);
        $this->coordinateJsonConverter = $this->getContainer()->get(CoordinateJsonConverter::class);

        $this->masterTemplateService = $this->getContainer()->get(MasterTemplateService::class);
        $this->globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);

        $this->procedureMapSettingResourceType = new ProcedureMapSettingResourceType($contentService, $this->masterTemplateService, $this->coordinateJsonConverter);
        $this->procedureMapSettingResourceType->setGlobalConfig($this->globalConfig);
    }

    /**
     * @dataProvider defaultMapExtentDataProvider()
     */
    public function testDefaultMapExtent($mapExtentMasterTemplateValue, $expectedDefaultMapExtent)
    {
        $this->masterTemplateService->getMasterTemplate()->getSettings()->setMapExtent($mapExtentMasterTemplateValue);

        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);

        $result = $getMapSettingMethod->invoke($this->procedureMapSettingResourceType, 'getMapExtent', 'getMapPublicExtent');
        static::assertEquals($expectedDefaultMapExtent, $result);
    }

    public function defaultMapExtentDataProvider(): array
    {
        $this->setUp();

        return [
            [
                'mapExtentMasterTemplateValue' => '555555.41,9999999.13,611330.65,6089742.54',
                'expectedResult'               => [
                    'start' => [
                        'latitude'  => 555555.41,
                        'longitude' => 9999999.13,
                    ],
                    'end' => [
                        'latitude'  => 611330.65,
                        'longitude' => 6089742.54,
                    ],
                ],
            ],
            [
                'mapExtentMasterTemplateValue' => '',
                'expectedResult'               => $this->coordinateJsonConverter->convertFlatListToCoordinates($this->globalConfig->getMapPublicExtent(), true),
            ],
            [
                'mapExtentMasterTemplateValue' => null,
                'expectedResult'               => $this->coordinateJsonConverter->convertFlatListToCoordinates($this->globalConfig->getMapPublicExtent(), true),
            ],
        ];
    }

    /**
     * @dataProvider defaultBoundingBoxDataProvider()
     */
    public function testDefaultBoundingBox($boundingBoxMasterTemplateValue, $expectedDefaultBoundingBox)
    {
        $this->masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox($boundingBoxMasterTemplateValue);

        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);

        $result = $getMapSettingMethod->invoke($this->procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');
        static::assertEquals($expectedDefaultBoundingBox, $result);
    }

    public function defaultBoundingBoxDataProvider(): array
    {
        $this->setUp();

        return [
            [
                'boundingBoxMasterTemplateValue' => '555555.41,9999999.13,611330.65,6089742.54',
                'expectedResult'                 => [
                    'start' => [
                        'latitude'  => 555555.41,
                        'longitude' => 9999999.13,
                    ],
                    'end' => [
                        'latitude'  => 611330.65,
                        'longitude' => 6089742.54,
                    ],
                ],
            ],
            [
                'boundingBoxMasterTemplateValue' => '',
                'expectedResult'                 => $this->coordinateJsonConverter->convertFlatListToCoordinates($this->globalConfig->getMapMaxBoundingbox(), true),
            ],
            [
                'boundingBoxMasterTemplateValue' => null,
                'expectedResult'                 => $this->coordinateJsonConverter->convertFlatListToCoordinates($this->globalConfig->getMapMaxBoundingbox(), true),
            ],
        ];
    }
}
