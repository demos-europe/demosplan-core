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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Tests\Base\UnitTestCase;

class BoilerplateGroupTest extends UnitTestCase
{
    protected ?BoilerplateGroup $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $procedurePhaseDefinition = new ProcedurePhaseDefinition();
        $procedure = new Procedure($procedurePhaseDefinition, $procedurePhaseDefinition);
        $this->sut = new BoilerplateGroup('Meine Gruppe', $procedure);
    }

    /**
     * DPLAN-18271: a boilerplate awaiting async deletion is, conceptually, already gone —
     * display/edit consumers (the admin Textbausteine list, the group-edit form) must not
     * keep showing it via this method, unlike the raw getBoilerplates() bookkeeping
     * consumers still need.
     */
    public function testGetBoilerplatesExcludingPendingDeletionOmitsFlaggedBoilerplate(): void
    {
        $liveBoilerplate = new Boilerplate();
        $pendingDeletionBoilerplate = new Boilerplate();
        $pendingDeletionBoilerplate->setPendingDeletion(true);
        $this->sut->addBoilerplate($liveBoilerplate);
        $this->sut->addBoilerplate($pendingDeletionBoilerplate);

        $visibleBoilerplates = $this->sut->getBoilerplatesExcludingPendingDeletion();

        self::assertSame([$liveBoilerplate], $visibleBoilerplates);
        self::assertCount(2, $this->sut->getBoilerplates());
    }
}
