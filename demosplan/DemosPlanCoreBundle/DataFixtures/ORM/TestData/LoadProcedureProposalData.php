<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureProposalData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $testProcedureProposal1 = new ProcedureProposal();
        $testProcedureProposal1->setName('testProcedureProposal1');
        $testProcedureProposal1->setDescription('descpription of testProcedureProposal1');
        $testProcedureProposal1->setAdditionalExplanation('additional explanation of testProcedureProposal1');
        $testProcedureProposal1->setStatus(ProcedureProposal::STATUS['has_been_transformed_into_procedure']);

        $manager->persist($testProcedureProposal1);
        $this->setReference('testProcedureProposal1', $testProcedureProposal1);

        $manager->flush();
    }
}
