<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\DraftStatement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use Tests\Base\FunctionalTestCase;

class DraftStatementHandlerTest extends FunctionalTestCase
{
    /** @var DraftStatementHandler */
    protected $sut;

    /** @var DraftStatement */
    protected $testDraftStatement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(DraftStatementHandler::class);
        $this->testDraftStatement = $this->fixtures->getReference('testDraftStatement');
    }

    public function testDetUnsubmittedDraftStatementsOfSoonEndingProcedures()
    {
        self::markSkippedForCIIntervention();
        $djkafh = $this->sut->getUnsubmittedDraftStatementsOfSoonEndingProcedures();
    }
}
