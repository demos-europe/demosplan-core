<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureTypeData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Map\CoordinateJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureMapSettingResourceType;
use Doctrine\ORM\ORMInvalidArgumentException;
use Exception;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class ProcedureMapSettingTest extends FunctionalTestCase
{
    /** @var ProcedureService */
    protected $sut;

    /** @var Procedure */
    protected $testProcedure;

    /** @var Session */
    protected $mockSession;

    /** @var MapService */
    protected $mapService;

    /** @var ReportService */
    protected $reportService;

    /** @var FileService|object|null */
    private $fileService;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $contentService = $this->getContainer()->get(ContentService::class);
        $masterTemplateService = $this->getContainer()->get(MasterTemplateService::class);
        $coordinateJsonConverter = $this->getContainer()->get(CoordinateJsonConverter::class);

    }

    public function testGetMasterTemplateBoundingBox()
    {

        $contentService = $this->getContainer()->get(ContentService::class);
        $masterTemplateService = $this->getContainer()->get(MasterTemplateService::class);
        $coordinateJsonConverter = $this->getContainer()->get(CoordinateJsonConverter::class);
        $procedureMapSettingResourceType = new ProcedureMapSettingResourceType($contentService, $masterTemplateService, $coordinateJsonConverter);

        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $reflectionMethod->setAccessible(true);

        // Call the protected method
        $masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox('555555.41,9999999.13,611330.65,6089742.54');

        $result = $reflectionMethod->invoke($procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');

        // Assert the result
        $expectedResult = [
            'start' => [
                'latitude' => 555555.41,
                'longitude' => 9999999.13,
            ],
            'end' => [
                'latitude' => 611330.65,
                'longitude' => 6089742.54,
            ],
        ];
        $this->assertEquals($expectedResult, $result);

    }

    public function testGetConfigBoundingBox()
    {

        $contentService = $this->getContainer()->get(ContentService::class);
        $masterTemplateService = $this->getContainer()->get(MasterTemplateService::class);
        $coordinateJsonConverter = $this->getContainer()->get(CoordinateJsonConverter::class);
        $globalConfig = $this->getContainer()->get(GlobalConfig::class);

        $procedureMapSettingResourceType = new ProcedureMapSettingResourceType($contentService, $masterTemplateService, $coordinateJsonConverter);
        $procedureMapSettingResourceType->setGlobalConfig($globalConfig);

        $convertFlatListToCoordinatesMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'convertFlatListToCoordinates');
        $convertFlatListToCoordinatesMethod->setAccessible(true);

        $globalConfigBoundingBoxExpectedValue = $convertFlatListToCoordinatesMethod->invoke($procedureMapSettingResourceType, $globalConfig->getMapMaxBoundingbox(), true);




        // Use reflection to access the protected method
        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);



        // Call the protected method
        $masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox('');

        $result = $getMapSettingMethod->invoke($procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');


        $this->assertEquals($globalConfigBoundingBoxExpectedValue, $result);

    }
}
