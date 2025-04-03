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

    protected function setUp(): void
    {
        parent::setUp();

        $contentService = $this->getContainer()->get(ContentService::class);
        $coordinateJsonConverter = $this->getContainer()->get(CoordinateJsonConverter::class);

        $this->masterTemplateService = $this->getContainer()->get(MasterTemplateService::class);
        $this->globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);

        $this->procedureMapSettingResourceType = new ProcedureMapSettingResourceType($contentService, $this->masterTemplateService, $coordinateJsonConverter);
        $this->procedureMapSettingResourceType->setGlobalConfig($this->globalConfig);
    }

    public function testGetMasterTemplateBoundingBox()
    {
        // Set a bounding box for the master template aka blueprint
        $this->masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox('555555.41,9999999.13,611330.65,6089742.54');

        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);

        $result = $getMapSettingMethod->invoke($this->procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');

        $expectedResult = [
            'start' => [
                'latitude'  => 555555.41,
                'longitude' => 9999999.13,
            ],
            'end' => [
                'latitude'  => 611330.65,
                'longitude' => 6089742.54,
            ],
        ];
        static::assertEquals($expectedResult, $result);
    }

    public function testGetConfigBoundingBox()
    {
        // Set an empty bounding box for the master template aka blueprint
        $this->masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox('');

        $convertFlatListToCoordinatesMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'convertFlatListToCoordinates');
        $convertFlatListToCoordinatesMethod->setAccessible(true);

        // Detect the boundingBox of the global config
        $expectedGlobalConfigBoundingBox = $convertFlatListToCoordinatesMethod->invoke($this->procedureMapSettingResourceType, $this->globalConfig->getMapMaxBoundingbox(), true);

        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);

        $result = $getMapSettingMethod->invoke($this->procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');
        static::assertEquals($expectedGlobalConfigBoundingBox, $result);
    }
}
