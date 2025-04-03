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
        // Set an empty bounding box for the master template aka blueprint
        $this->masterTemplateService->getMasterTemplate()->getSettings()->setBoundingBox('');

        $convertFlatListToCoordinatesMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'convertFlatListToCoordinates');
        $convertFlatListToCoordinatesMethod->setAccessible(true);

        // Detect the boundingBox of the global config
        $expectedGlobalConfigBoundingBox = $convertFlatListToCoordinatesMethod->invoke($this->procedureMapSettingResourceType, $this->globalConfig->getMapMaxBoundingbox(), true);

        $getMapSettingMethod = new ReflectionMethod(ProcedureMapSettingResourceType::class, 'getMapSetting');
        $getMapSettingMethod->setAccessible(true);


        $result = $getMapSettingMethod->invoke($this->procedureMapSettingResourceType, 'getBoundingBox', 'getMapMaxBoundingbox');
        $this->assertEquals($expectedGlobalConfigBoundingBox, $result);

    }
}
