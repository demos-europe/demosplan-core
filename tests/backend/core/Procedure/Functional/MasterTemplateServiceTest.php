<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use Tests\Base\FunctionalTestCase;

class MasterTemplateServiceTest extends FunctionalTestCase
{
    /**
     * @var MasterTemplateService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(MasterTemplateService::class);
    }

    public function testGetMasterTemplate()
    {
        /** @var Procedure $user */
        $fixtureProcedure = $this->fixtures->getReference('masterBlaupause');

        $masterTemplate = $this->sut->getMasterTemplate();
        self::assertEquals($fixtureProcedure->getId(), $masterTemplate->getId());
        self::assertEquals($fixtureProcedure->getId(), $this->sut->getMasterTemplateId());
    }
}
