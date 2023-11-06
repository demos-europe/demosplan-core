<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadDraftStatementData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var ParagraphVersion $paragraphVersion */
        $paragraphVersion = $this->getReference('testParagraph2Version');
        /** @var Elements $element */
        $element = $this->getReference('testElement1');

        /** @var Orga $invitableInstitution */
        $invitableInstitution = $this->getReference('testOrgaInvitableInstitution');
        /** @var Orga $organisation */
        $organisation = $this->getReference('testOrgaPB');
        /** @var Orga $fachplanerOrganisation */
        $fachplanerOrganisation = $this->getReference('testOrgaFP');

        /** @var Department $department */
        $department = $this->getReference('testDepartment');
        /** @var Department $planningDepartment */
        $planningDepartment = $this->getReference('testDepartmentPlanningOffice');

        /** @var User $user */
        $user = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var User $planningUser */
        $planningUser = $this->getReference('testUserPlanningOffice');

        /** @var Procedure $procedure */
        $procedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);

        $draftStatement = new DraftStatement();
        $draftStatement->setTitle('Draft Statement');
        $draftStatement->setProcedure($procedure);
        $draftStatement->setUser($user);
        $draftStatement->setUName($user->getFirstname().' '.$user->getLastname());
        $draftStatement->setDepartment($department);
        $draftStatement->setDName($department->getName());
        $draftStatement->setOrganisation($invitableInstitution);
        $draftStatement->setOName($fachplanerOrganisation->getName());
        $draftStatement->setElement($element);
        $draftStatement->setParagraph($paragraphVersion);
        $draftStatement->setPhase('participation');
        $draftStatement->setRepresents('representedOrganization');

        $this->setReference('testDraftStatement', $draftStatement);
        $manager->persist($draftStatement);

        // SN gleiche Orga, anderer User
        $draftStatement2 = new DraftStatement();
        $draftStatement2->setTitle('Draft Statement Orga');
        $draftStatement2->setText('Ich bin der Text für das Draft Statement, gleiche Orga, anderer User');
        $draftStatement2->setProcedure($procedure);
        $draftStatement2->setUser($planningUser);
        $draftStatement2->setUName($user->getFirstname().' '.$user->getLastname());
        $draftStatement2->setDepartment($department);
        $draftStatement2->setDName($department->getName());
        $draftStatement2->setOrganisation($invitableInstitution);
        $draftStatement2->setOName($fachplanerOrganisation->getName());
        $draftStatement2->setElement($element);
        $draftStatement2->setParagraph($paragraphVersion);
        $draftStatement2->setPhase('participation');

        $this->setReference('testDraftStatement2', $draftStatement2);
        $manager->persist($draftStatement2);

        $draftStatement3 = new DraftStatement();
        $draftStatement3->setText('Ich bin der Text für das Draft Statement einer anderen Orga');
        $draftStatement3->setProcedure($procedure);
        $draftStatement3->setUser($planningUser);
        $draftStatement3->setUName($planningUser->getFirstname().' '.$planningUser->getLastname());
        $draftStatement3->setDepartment($planningDepartment);
        $draftStatement3->setDName($planningDepartment->getName());
        $draftStatement3->setOrganisation($organisation);
        $draftStatement3->setOName($organisation->getName());
        $draftStatement3->setElement($element);
        $draftStatement3->setParagraph($paragraphVersion);
        $draftStatement3->setPhase('participation');
        $draftStatement3->setShowToAll(true);
        $draftStatement3->setReleased(true);
        $draftStatement3->setReleasedDate(new DateTime());
        $draftStatement3->setSubmitted(true);
        $draftStatement3->setSubmittedDate(new DateTime());

        $this->setReference('testDraftStatementOtherOrga', $draftStatement3);
        $manager->persist($draftStatement3);

        $draftStatement4 = new DraftStatement();
        $draftStatement4->setText('Ich bin der Text für das 4te Draft Statement einer anderen Orga');
        $draftStatement4->setProcedure($procedure);
        $draftStatement4->setUser($user);
        $draftStatement4->setUName($planningUser->getFirstname().' '.$planningUser->getLastname());
        $draftStatement4->setDepartment($department);
        $draftStatement4->setDName($department->getName());
        $draftStatement4->setOrganisation($fachplanerOrganisation);
        $draftStatement4->setOName($fachplanerOrganisation->getName());
        $draftStatement4->setElement($element);
        $draftStatement4->setParagraph($paragraphVersion);
        $draftStatement4->setPhase('participation');
        $draftStatement4->setShowToAll(true);
        $draftStatement4->setReleased(false);
        $draftStatement4->setReleasedDate(new DateTime());
        $draftStatement4->setSubmitted(false);
        $draftStatement4->setSubmittedDate(new DateTime());

        $this->setReference('testDraftStatementOtherOrga2', $draftStatement4);
        $manager->persist($draftStatement4);

        $draftStatement_released = new DraftStatement();
        $draftStatement_released->setText('Ich bin der Text für das released Draft Statement einer anderen Orga');
        $draftStatement_released->setProcedure($procedure);
        $draftStatement_released->setUser($user);
        $draftStatement_released->setUName($planningUser->getFirstname().' '.$planningUser->getLastname());
        $draftStatement_released->setDepartment($department);
        $draftStatement_released->setDName($department->getName());
        $draftStatement_released->setOrganisation($fachplanerOrganisation);
        $draftStatement_released->setElement($element);
        $draftStatement_released->setParagraph($paragraphVersion);
        $draftStatement_released->setPhase('participation');
        $draftStatement_released->setShowToAll(true);
        $draftStatement_released->setReleased(true);
        $draftStatement_released->setReleasedDate(new DateTime());
        $draftStatement_released->setSubmitted(false);
        $draftStatement_released->setSubmittedDate(new DateTime());

        $this->setReference('testReleasedDraftStatementOtherOrga3', $draftStatement_released);
        $manager->persist($draftStatement_released);

        $draftStatement_submitted = new DraftStatement();
        $draftStatement_submitted->setText('Ich bin der Text für das 6te Draft Statement einer anderen Orga');
        $draftStatement_submitted->setProcedure($procedure);
        $draftStatement_submitted->setUser($user);
        $draftStatement_submitted->setUName($planningUser->getFirstname().' '.$planningUser->getLastname());
        $draftStatement_submitted->setDepartment($department);
        $draftStatement_submitted->setDName($department->getName());
        $draftStatement_submitted->setOrganisation($fachplanerOrganisation);
        $draftStatement_submitted->setOName($fachplanerOrganisation->getName());
        $draftStatement_submitted->setElement($element);
        $draftStatement_submitted->setParagraph($paragraphVersion);
        $draftStatement_submitted->setPhase('participation');
        $draftStatement_submitted->setShowToAll(true);
        $draftStatement_submitted->setReleased(true);
        $draftStatement_submitted->setReleasedDate(new DateTime());
        $draftStatement_submitted->setSubmitted(true);
        $draftStatement_submitted->setSubmittedDate(new DateTime());

        $manager->persist($draftStatement_submitted);
        $this->setReference('testSubmittedDraftStatementOtherOrga4', $draftStatement_submitted);

        $draftStatement5 = new DraftStatement();
        $draftStatement5->setText('Ich bin der Text für das 5te Draft Statement');
        $draftStatement5->setProcedure($procedure);
        $draftStatement5->setUser($user);
        $draftStatement5->setUName($planningUser->getFirstname().' '.$planningUser->getLastname());
        $draftStatement5->setDepartment($department);
        $draftStatement5->setDName($department->getName());
        $draftStatement5->setOrganisation($fachplanerOrganisation);
        $draftStatement5->setOName($fachplanerOrganisation->getName());
        $draftStatement5->setElement($this->getReference('testElement7'));
        $draftStatement5->setParagraph($paragraphVersion);
        $draftStatement5->setPhase('participation');
        $draftStatement5->setShowToAll(true);
        $draftStatement5->setReleased(false);
        $draftStatement5->setReleasedDate(new DateTime());
        $draftStatement5->setSubmitted(false);
        $draftStatement5->setSubmittedDate(new DateTime());

        $this->setReference('testDraftStatementOtherOrga2', $draftStatement5);
        $manager->persist($draftStatement5);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadElementsData::class,
            LoadProcedureData::class,
            LoadUserData::class,
        ];
    }
}
