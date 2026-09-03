<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Unit\Entity;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Tests\Base\UnitTestCase;

class BoilerplateCategoryTest extends UnitTestCase
{
    protected ?BoilerplateCategory $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $procedurePhaseDefinition = new ProcedurePhaseDefinition();
        $procedure = new Procedure($procedurePhaseDefinition, $procedurePhaseDefinition);
        $this->sut = new BoilerplateCategory();
        $this->sut->setProcedure($procedure);
        $this->sut->setTitle('consideration');
    }

    /**
     * DPLAN-18271: a boilerplate awaiting async deletion is, conceptually, already gone —
     * display consumers (the email/news-notes boilerplate pickers fed by
     * ProcedureService::getBoilerplatesOfCategory()) must not keep offering it via this
     * method, unlike the raw getBoilerplates() the category's own bookkeeping still uses.
     */
    public function testGetBoilerplatesExcludingPendingDeletionOmitsFlaggedBoilerplate(): void
    {
        $procedure = $this->sut->getProcedure();
        $liveBoilerplate = new Boilerplate();
        $liveBoilerplate->setProcedure($procedure);
        $pendingDeletionBoilerplate = new Boilerplate();
        $pendingDeletionBoilerplate->setProcedure($procedure);
        $pendingDeletionBoilerplate->setPendingDeletion(true);
        $this->sut->addBoilerplate($liveBoilerplate);
        $this->sut->addBoilerplate($pendingDeletionBoilerplate);

        $visibleBoilerplates = $this->sut->getBoilerplatesExcludingPendingDeletion();

        self::assertSame([$liveBoilerplate], $visibleBoilerplates);
        self::assertCount(2, $this->sut->getBoilerplates());
    }
}
