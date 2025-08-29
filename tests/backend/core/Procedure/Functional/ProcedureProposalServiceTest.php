<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureProposalService;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class ProcedureProposalServiceTest extends FunctionalTestCase
{
    /** @var ProcedureProposalService */
    protected $sut;

    /** @var ProcedureProposal */
    protected $testProcedureProposal;

    /** @var Session */
    protected $mockSession;

    /** @var TranslatorInterface */
    protected $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ProcedureProposalService::class);
        $this->testProcedureProposal = $this->fixtures->getReference('testProcedureProposal1');

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->login($user);
        $this->translator = self::getContainer()->get('translator.default');
    }

    public function testGetProcedureProposals(): void
    {
        $allProcedureProposals = $this->sut->getProcedureProposals();
        static::assertCount($this->countEntries(ProcedureProposal::class), $allProcedureProposals);
    }

    /**
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function testGenerateProcedureProposalsFromProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $amountOfProcedureProposalsBefore = $this->countEntries(ProcedureProposal::class);
        $amountOfProceduresBefore = $this->countEntries(Procedure::class);
        $newProcedure = $this->sut->generateProcedureFromProcedureProposal($this->testProcedureProposal);

        static::assertEquals($amountOfProcedureProposalsBefore, $this->countEntries(ProcedureProposal::class));
        static::assertEquals($amountOfProceduresBefore + 1, $this->countEntries(Procedure::class));

        static::assertEquals($this->testProcedureProposal->getName(), $newProcedure->getName());
        static::assertNotEquals($this->testProcedureProposal->getDescription(), $newProcedure->getDesc());
        static::assertEquals($this->testProcedureProposal->getCoordinate(), $newProcedure->getCoordinate());

        // todo:
        // static::assertEquals($this->testProcedureProposal->getFiles(), $newProcedure->getFiles());
    }

    /**
     * Checks that when we generate a Procedure from a Proposal, the info in
     * additionalExplanation from the latter will be saved as an Element in the generated
     * Procedure.
     *
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function testProcedureReceivesExplanationTextFromProposal(): void
    {
        self::markSkippedForCIIntervention();

        $newProcedure = $this->sut->generateProcedureFromProcedureProposal(
            $this->testProcedureProposal
        );
        $elements = $newProcedure->getElements();
        $explanation = null;
        /** @var Elements $element */
        foreach ($elements as $element) {
            if ($this->translator->trans('explanations') === $element->getTitle()) {
                $explanation = $element;
            }
        }
        static::assertNotNull($explanation);
        static::assertEquals($this->testProcedureProposal->getAdditionalExplanation(), $explanation->getText());
    }
}
