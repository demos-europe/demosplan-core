<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use Tests\Base\FunctionalTestCase;

class DocumentHandlerTest extends FunctionalTestCase
{
    /**
     * @var DocumentHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(DocumentHandler::class);
    }

    public function testHasProcedurePlanningDocuments()
    {
        self::markSkippedForCIIntervention();

        $orgaId = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getOrga()->getId();
        $procedureWithoutDocs = new Procedure();
        $procedureWithoutDocs->setId('idToAvoidException');
        $procedureWithDocs = $this->fixtures->getReference('testProcedure');

        static::assertFalse($this->sut->hasProcedureElements($procedureWithoutDocs, $orgaId));
        static::assertTrue($this->sut->hasProcedureElements($procedureWithDocs, $orgaId));
    }
}
