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
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVersionField;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadStatementData extends TestFixture implements DependentFixtureInterface
{
    protected $manager;

    final public const PI_SEGMENTS_PROPOSAL_RESOURCE_URL_TEST = 'http://www.pisegmentsproposalresourceurl.com';

    final public const TEST_STATEMENT = 'testStatement';
    final public const TEST_STATEMENT_ORIGINAL = 'testStatementOrig';
    final public const TEST_STATEMENT_WITH_TOKEN = 'testStatementWithToken';
    final public const MANUAL_STATEMENT_IN_PUBLIC_PARTICIPATION_PHASE = 'manualStatementInPublicParticipationPhase';

    /**
     * @throws InvalidDataException
     */
    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        /** @var User $testUser */
        $testUser = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var Orga $testOrga */
        $testOrga = $this->getReference('testOrgaInvitableInstitution');
        /** @var Elements $testElement */
        $testElement = $this->getReference('testElement1');
        /** @var SingleDocumentVersion $testDocument */
        $testDocument = $this->getReference('testSingleDocumentVersion1');
        /** @var ParagraphVersion $testParagraphVersion */
        $testParagraphVersion = $this->getReference('testParagraph2Version');
        /** @var County $testCounty */
        $testCounty = $this->getReference('testCounty1');
        /** @var Municipality $testMunicipality */
        $testMunicipality = $this->getReference('testMunicipality1');
        /** @var PriorityArea $testPriorityArea */
        $testPriorityArea = $this->getReference('testPriorityArea1');

        $statementOrig = new Statement();
        $statementOrig->setElement($testElement);
        $statementOrig->setExternId('1000');
        $statementOrig->setGdprConsent(new GdprConsent());
        $statementOrig->setInternId('dddd');
        $statementOrig->setMeta((new StatementMeta())->setStatement($statementOrig));
        $statementOrig->setOrganisation($testOrga);
        $statementOrig->setParagraph($testParagraphVersion);
        $statementOrig->setPhase('participation');
        $statementOrig->setProcedure($testProcedure);
        $statementOrig->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statementOrig->setSubmitType('system');
        $statementOrig->setText('Ich bin der Text für das Statement');
        $statementOrig->setTitle('Statement Original');
        $statementOrig->setUser($testUser);

        $manager->persist($statementOrig);
        $this->setReference(self::TEST_STATEMENT_ORIGINAL, $statementOrig);

        $statement = new Statement();
        $statement->addCounty($testCounty);
        $statement->addMunicipality($testMunicipality);
        $statement->addPriorityArea($testPriorityArea);
        $statement->setElement($testElement);
        $statement->setExternId('1000');
        $statement->setInternId('2222');
        $statement->setMeta((new StatementMeta())->setStatement($statement)->setAuthorName('Max Mustermann'));
        $statement->setOrganisation($testOrga);
        $statement->setOriginal($statementOrig);
        $statement->setParent($statementOrig);
        $statement->setParagraph($testParagraphVersion);
        $statement->setPhase('participation');
        $statement->setProcedure($testProcedure);
        $statement->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement->setRepresentationCheck(true);
        $statement->setRepresents('representedOrganization');
        $statement->setSubmitType('system');
        $statement->setText('Ich bin der Text für das Statement');
        $statement->setTitle('Statement');
        $statement->setUser($testUser);
        $statement->setPiSegmentsProposalResourceUrl(self::PI_SEGMENTS_PROPOSAL_RESOURCE_URL_TEST);
        $this->setReference(self::TEST_STATEMENT, $statement);
        $manager->persist($statement);

        $statement1 = new Statement();
        $statement1->addCounty($testCounty);
        $statement1->addMunicipality($testMunicipality);
        $statement1->addPriorityArea($testPriorityArea);
        $statement1->setAssignee(null);
        $statement1->setElement($testElement);
        $statement1->setExternId('1111');
        $statement1->setInternId('11111111');
        $statement1->setMeta((new StatementMeta())->setStatement($statement1));
        $statement1->setOrganisation($testOrga);
        $statement1->setOriginal($statementOrig);
        $statement1->setParagraph($testParagraphVersion);
        $statement1->setParent($statementOrig);
        $statement1->setPhase('participation');
        $statement1->setProcedure($testProcedure);
        $statement1->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        $statement1->setRepresentationCheck(true);
        $statement1->setRepresents('representedOrganization');
        $statement1->setSubmitType('system');
        $statement1->setText('Ich bin der Text für das Statement1');
        $statement1->setTitle('Statement1');
        $statement1->setUser($testUser);
        $this->setReference('testStatement1', $statement1);
        $manager->persist($statement1);

        // add Fragments
        $fragmentTexts = collect(['First Fragment', 'Second Fragment', 'Third Fragment']);
        $fragmentTexts->each(function ($text, $offset) use ($manager, $statement) {
            $statementFragment = new StatementFragment();
            $statementFragment->setAssignedToFbDate(new DateTime());
            $statementFragment->setAssignee(null);
            $statementFragment->setDepartment($this->getReference('testDepartment'));
            $statementFragment->setDisplayId($offset + 1);
            $statementFragment->setProcedure($statement->getProcedure());
            $statementFragment->setStatement($statement);
            $statementFragment->setText($text);
            $statementFragment->setSortIndex($offset + 1);
            $manager->persist($statementFragment);
            $this->setReference('testStatementFragment'.($offset + 1), $statementFragment);
        });

        $statementFragment2 = new StatementFragment();
        $statementFragment2->setAssignedToFbDate(new DateTime());
        $statementFragment2->setCounties([$testCounty, $this->getReference('testCounty2')]);
        $statementFragment2->setDisplayId(745);
        $statementFragment2->setMunicipalities([$testMunicipality, $this->getReference('testMunicipality2')]);
        $statementFragment2->setPriorityAreas([$testPriorityArea, $this->getReference('testPriorityArea2')]);
        $statementFragment2->setProcedure($statement->getProcedure());
        $statementFragment2->setProcedure($statement->getProcedure());
        $statementFragment2->setStatement($statement);
        $statementFragment2->setSortIndex(4);
        $statementFragment2->setTags([
            $this->getReference('testFixtureTag_1'),
            $this->getReference('testFixtureTag_2'),
            $this->getReference('testFixtureTag_3'),
            $this->getReference('testFixtureTag_4'),
        ]);
        $statementFragment2->setText('some kind of text');
        $manager->persist($statementFragment2);
        $this->setReference('testStatementFragmentFilled', $statementFragment2);

        $statementFragment3 = new StatementFragment();
        $statementFragment3->setAssignedToFbDate(new DateTime());
        $statementFragment3->setCounties([$testCounty]);
        $statementFragment3->setDisplayId(722);
        $statementFragment3->setMunicipalities([$testMunicipality]);
        $statementFragment3->setPriorityAreas([$testPriorityArea]);
        $statementFragment3->setProcedure($statement->getProcedure());
        $statementFragment3->setStatement($statement1);
        $statementFragment3->setText('some kind of another text');
        $statementFragment3->setSortIndex(1);
        $manager->persist($statementFragment3);
        $this->setReference('testStatementFragmentFilled1', $statementFragment3);

        $statementFragment4 = new StatementFragment();
        $statementFragment4->setAssignedToFbDate(new DateTime());
        $statementFragment4->setAssignee($testUser);
        $statementFragment4->setCounties([$testCounty]);
        $statementFragment4->setDisplayId(722);
        $statementFragment4->setMunicipalities([$testMunicipality]);
        $statementFragment4->setPriorityAreas([$testPriorityArea]);
        $statementFragment4->setProcedure($statement->getProcedure());
        $statementFragment4->setStatement($statement);
        $statementFragment4->setSortIndex(5);
        $statementFragment4->setText('some kind of another text 4');
        $manager->persist($statementFragment4);
        $this->setReference('testStatementFragmentAssigned4', $statementFragment4);

        $statementFragment5 = new StatementFragment();
        $statementFragment5->setAssignedToFbDate(new DateTime());
        $statementFragment5->setAssignee($testUser);
        $statementFragment5->setCounties([$testCounty]);
        $statementFragment5->setDepartment($this->getReference('testDepartment'));
        $statementFragment5->setDisplayId(724);
        $statementFragment5->setMunicipalities([$testMunicipality]);
        $statementFragment5->setPriorityAreas([$testPriorityArea]);
        $statementFragment5->setProcedure($statement->getProcedure());
        $statementFragment5->setStatement($statement);
        $statementFragment5->setSortIndex(6);
        $statementFragment5->setStatus('fragment.status.assignedToFB');
        $statementFragment5->setText('some kind of another text 5');
        $manager->persist($statementFragment5);
        $this->setReference('testStatementFragmentAssignedToDepartment', $statementFragment5);

        $statementFragment6 = new StatementFragment();
        $statementFragment6->setArchivedDepartmentName('someDepartmentName');
        $statementFragment6->setArchivedOrgaName('someOrgaName');
        $statementFragment6->setAssignedToFbDate(new DateTime());
        $statementFragment6->setAssignee($testUser);
        $statementFragment6->setCounties([$testCounty]);
        $statementFragment6->setDepartment($this->getReference('testDepartment'));
        $statementFragment6->setDisplayId(726);
        $statementFragment6->setMunicipalities([$testMunicipality]);
        $statementFragment6->setPriorityAreas([$testPriorityArea]);
        $statementFragment6->setProcedure($statement->getProcedure());
        $statementFragment6->setStatement($statement);
        $statementFragment6->setStatus('fragment.status.verified');
        $statementFragment6->setSortIndex(7);
        $statementFragment6->setText('some kind of another text 6');
        $manager->persist($statementFragment6);
        $this->setReference('testStatementFragmentAssignedWithArchivedOrga', $statementFragment6);

        $statementFragment7 = new StatementFragment();
        $statementFragment7->setAssignedToFbDate(new DateTime());
        $statementFragment7->setAssignee($testUser);
        $statementFragment7->setCounties([$testCounty]);
        $statementFragment7->setDisplayId(777);
        $statementFragment7->setMunicipalities([$testMunicipality]);
        $statementFragment7->setPriorityAreas([$testPriorityArea]);
        $statementFragment7->setProcedure($statement->getProcedure());
        $statementFragment7->setStatement($statement);
        $statementFragment7->setStatus('fragment.status.verified');
        $statementFragment7->setText('some kind of another text 7');
        $statementFragment7->setSortIndex(8);
        $manager->persist($statementFragment7);
        $this->setReference('testStatementFragmentWithVerifiedState', $statementFragment7);

        $statementFragment8 = new StatementFragment();
        $statementFragment8->setAssignedToFbDate(new DateTime());
        $statementFragment8->setAssignee($testUser);
        $statementFragment8->setCounties([$testCounty]);
        $statementFragment8->setDisplayId(777);
        $statementFragment8->setMunicipalities([$testMunicipality]);
        $statementFragment8->setPriorityAreas([$testPriorityArea]);
        $statementFragment8->setProcedure($statement->getProcedure());
        $statementFragment8->setStatement($statement);
        $statementFragment8->setStatus('fragment.status.verified');
        $statementFragment8->setText('some kind of another text 7');
        $statementFragment8->setSortIndex(9);
        $manager->persist($statementFragment8);
        $this->setReference('testStatementFragmentWithVerifiedStateWithoutArchivedOrga', $statementFragment8);

        $statementFragment9 = new StatementFragment();
        $statementFragment9->setAssignedToFbDate(new DateTime());
        $statementFragment9->setCounties([$testCounty]);
        $statementFragment9->setDisplayId(999);
        $statementFragment9->setMunicipalities([$testMunicipality]);
        $statementFragment9->setPriorityAreas([$testPriorityArea]);
        $statementFragment9->setProcedure($statement->getProcedure());
        $statementFragment9->setStatement($statement);
        $statementFragment9->setText('some kind of another text 9');
        $statementFragment9->setSortIndex(10);
        $manager->persist($statementFragment9);
        $this->setReference('statementFragment9', $statementFragment9);

        $statement5 = new Statement();
        $statement5->setCreated(new DateTime('2000-01-01'));
        $statement5->setElement($testElement);
        $statement5->setExternId('1023');
        $statement5->setInternId('4556');
        $statement5->setMeta((new StatementMeta())->setStatement($statement5));
        $statement5->setOrganisation($testOrga);
        $statement5->setOriginal($statementOrig);
        $statement5->setParent($statementOrig);
        $statement5->setParagraph($testParagraphVersion);
        $statement5->setPhase('participation');
        $statement5->setProcedure($testProcedure);
        $statement5->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement5->setRepresentationCheck(true);
        $statement5->setRepresents('representedOrganization');
        $statement5->setSubmitType('system');
        $statement5->setText('Ich bin der Text für ein weiteres Statement');
        $statement5->setTitle('oneMoreStatement');
        $statement5->setUser($testUser);
        $statement5->setSimilarStatementSubmitters(
            new ArrayCollection([
                $this->getReference('testProcedurePerson1'),
                $this->getReference('testProcedurePerson2'),
                ]
            )
        );

        $this->setReference('testFixtureStatement', $statement5);
        $manager->persist($statement5);

        $statementVersionField = new StatementVersionField();
        $statementVersionField->setName('recommondation');
        $statementVersionField->setStatement($statement);
        $statementVersionField->setType('text');
        $statementVersionField->setUserName($testUser->getFirstname().' '.$testUser->getLastname());
        $statementVersionField->setValue('Ich bin der alte, nicht mehr aktuelle Text für das Statement');
        $manager->persist($statementVersionField);

        // SN gleiche Orga, anderer User
        $statement2 = new Statement();
        $statement2->addTag($this->getReference('testFixtureTag_2'));
        $statement2->setElement($testElement);
        $statement2->setExternId('1002');
        $statement2->setOrganisation($testOrga);
        $statement2->setOriginal($this->getReference('testFixtureStatement'));
        $statement2->setParagraph($testParagraphVersion);
        $statement2->setPhase('participation');
        $statement2->setProcedure($testProcedure);
        $statement2->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement2->setSubmitType('system');
        $statement2->setText('Ich bin der Text für das Statement, gleiche Orga, anderer User');
        $statement2->setTitle('Statement Orga');
        $statement2->setUser($this->getReference('testUserPlanningOffice'));
        $statement2->setSimilarStatementSubmitters(new ArrayCollection([$this->getReference('testProcedurePerson1')]));

        /** @var User $submitter */
        $submitter = $this->getReference('testUserDataInput1');

        $statementMeta = new StatementMeta();
        $statementMeta->setAuthorName('AuthorName');
        $statementMeta->setCaseWorkerName('CaseWorkerName');
        $statementMeta->setOrgaCity('some city');
        $statementMeta->setOrgaCity('some Organistaion');
        $statementMeta->setOrgaDepartmentName('some Department');
        $statementMeta->setOrgaEmail('some OrgaMail');
        $statementMeta->setOrgaName('some OrgaName');
        $statementMeta->setOrgaPostalCode('some postalcode');
        $statementMeta->setOrgaStreet('some street');
        $statementMeta->setStatement($statement2);
        $statementMeta->setSubmitName($submitter->getFullname());
        $statementMeta->setSubmitUId($submitter->getId());

        $statement2->setMeta($statementMeta);

        $this->setReference('testStatement2', $statement2);
        $manager->persist($statement2);

        $statement5 = new Statement();
        $statement5->setElement($testElement);
        $statement5->setExternId('1004');
        $statement5->setOrganisation($testOrga);
        $statement5->setOriginal($statementOrig);
        $statement5->setParent($statementOrig);
        $statement5->setParagraph($testParagraphVersion);
        $statement5->setPhase('participation');
        $statement5->setProcedure($testProcedure);
        $statement5->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement5->setSubmitType('system');
        $statement5->setText('Ich bin der Text für das ChildStatement, gleiche Orga, anderer User');
        $statement5->setTitle('child Statement of testStatement2');
        $statement5->setUser($this->getReference('testUserPlanningOffice'));

        $statementMeta2 = new StatementMeta();
        $statementMeta2->setAuthorName('AuthorName');
        $statementMeta2->setCaseWorkerName('CaseWorkerName');
        $statementMeta2->setOrgaCity('some city');
        $statementMeta2->setOrgaCity('some Organistaion');
        $statementMeta2->setOrgaDepartmentName('some Department');
        $statementMeta2->setOrgaEmail('some OrgaMail');
        $statementMeta2->setOrgaName('some OrgaName');
        $statementMeta2->setOrgaPostalCode('some postalcode');
        $statementMeta2->setOrgaStreet('some street');
        $statementMeta2->setStatement($statement5);
        $statementMeta2->setSubmitName($submitter->getFullname());
        $statementMeta2->setSubmitUId($submitter->getId());

        $statement5->setMeta($statementMeta2);

        $this->setReference('childTestStatement2', $statement5);
        $manager->persist($statement5);

        // SN andere Orga
        $statement3 = new Statement();
        $statement3->setElement($testElement);
        $statement3->setExternId('1003');
        $statement3->setGdprConsent(new GdprConsent());
        $statement3->setMeta((new StatementMeta())->setStatement($statement3));
        $statement3->setOrganisation($this->getReference('testOrgaPB'));
        $statement3->setParagraph($testParagraphVersion);
        $statement3->setPhase('participation');
        $statement3->setProcedure($testProcedure);
        $statement3->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement3->setSubmit(new DateTime());
        $statement3->setSubmitType('system');
        $statement3->setTags([$this->getReference('testFixtureTag_1'), $this->getReference('testFixtureTag_2')]);
        $statement3->setText('Ich bin der Text für das Statement einer anderen Orga');
        $statement3->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementOtherOrga', $statement3);
        $manager->persist($statement3);

        // SN für Abwägungstabelle
        $statement4 = new Statement();
        $statement4->setExternId('1003');
        $statement4->setMeta((new StatementMeta())->setStatement($statement4));
        $statement4->setOrganisation($this->getReference('testOrgaPB'));
        $statement4->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement4->setParent($this->getReference('testStatementOtherOrga'));
        $statement4->setPhase('participation');
        $statement4->setProcedure($testProcedure);
        $statement4->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement4->setSubmit(new DateTime());
        $statement4->setSubmitType('system');
        $statement4->setText('Ich bin der Text für das Statement einer anderen Orga');
        $statement4->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementNotOriginal', $statement4);
        $manager->persist($statement4);

        // SN für Abwägungstabelle
        $statement6 = new Statement();
        $statement6->setAssignee($testUser);
        $statement6->setElement($testElement);
        $statement6->setExternId('1006');
        $statement6->setMeta((new StatementMeta())->setStatement($statement6));
        $statement6->setOrganisation($this->getReference('testOrgaPB'));
        $statement6->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement6->setParagraph($testParagraphVersion);
        $statement6->setParent($this->getReference('testStatementOtherOrga'));
        $statement6->setPhase('participation');
        $statement6->setProcedure($testProcedure);
        $statement6->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement6->setSubmit(new DateTime());
        $statement6->setSubmitType('system');
        $statement6->setText('Ich bin der Text für das Statement 6 ');
        $statement6->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementAssigned6', $statement6);
        $manager->persist($statement6);

        $statement7 = new Statement();
        $statement7->setAssignee($testUser);
        $statement7->setElement($testElement);
        $statement7->setExternId('1007');
        $statement7->setMeta((new StatementMeta())->setStatement($statement7));
        $statement7->setOrganisation($this->getReference('testOrgaPB'));
        $statement7->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement7->setParagraph($testParagraphVersion);
        $statement7->setParent($this->getReference('testStatementOtherOrga'));
        $statement7->setPhase('participation');
        $statement7->setProcedure($testProcedure);
        $statement7->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement7->setSubmit(new DateTime());
        $statement7->setSubmitType('system');
        $statement7->setText('Ich bin der Text für das Statement 7 ');
        $statement7->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementAssigned7', $statement7);
        $manager->persist($statement7);

        // Cluster Statement01
        $clusterStatement01 = new Statement();
        $clusterStatement01->setAssignee($testUser);
        $clusterStatement01->setClusterStatement(true);
        $clusterStatement01->setExternId('G1008');
        $clusterStatement01->setGdprConsent(new GdprConsent());
        $clusterStatement01->setMeta((new StatementMeta())->setStatement($clusterStatement01));
        $clusterStatement01->setPhase('participation');
        $clusterStatement01->setProcedure($testProcedure);
        $clusterStatement01->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement01->setText('Ich bin der Text für das cluster-original #1');

        $this->setReference('clusterStatement 1', $clusterStatement01);
        $manager->persist($clusterStatement01);

        // Cluster Statement1
        $clusterStatement1 = new Statement();
        $clusterStatement1->setAssignee($testUser);
        $clusterStatement1->setCluster([$statement7]);
        $clusterStatement1->setClusterStatement(true);
        $clusterStatement1->setExternId('G1008');
        $clusterStatement1->setMeta((new StatementMeta())->setStatement($clusterStatement1));
        $clusterStatement1->setOriginal($clusterStatement01);
        $clusterStatement1->setParent($clusterStatement01);
        $clusterStatement1->setPhase('participation');
        $clusterStatement1->setProcedure($testProcedure);
        $clusterStatement1->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement1->setText('Ich bin der Text für das cluster #1');

        $this->setReference('clusterStatement1', $clusterStatement1);
        $manager->persist($clusterStatement1);

        $statement10 = new Statement();
        $statement10->setAssignee($testUser);
        $statement10->setExternId('1010');
        $statement10->setMeta((new StatementMeta())->setStatement($statement10));
        $statement10->setParagraph($testParagraphVersion);
        $statement10->setPhase('participation');
        $statement10->setProcedure($testProcedure);
        $statement10->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement10->setText('Ich bin der Text für das Statement 10 ');

        $this->setReference('testStatementAssigned10', $statement10);
        $manager->persist($statement10);

        $statement11 = new Statement();
        $statement11->setAssignee($testUser);
        $statement11->setExternId('1011');
        $statement11->setMeta((new StatementMeta())->setStatement($statement11));
        $statement11->setParagraph($testParagraphVersion);
        $statement11->setPhase('participation');
        $statement11->setProcedure($testProcedure);
        $statement11->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement11->setText('Ich bin der Text für das Statement 11 ');

        $this->setReference('testStatementAssigned11', $statement11);
        $manager->persist($statement11);

        // Cluster Statement2
        $clusterStatement2 = new Statement();
        $clusterStatement2->setAssignee($testUser);
        $clusterStatement2->setCluster([$statement10, $statement11]);
        $clusterStatement2->setExternId('C1009');
        $clusterStatement2->setMeta((new StatementMeta())->setStatement($clusterStatement2));
        $clusterStatement2->setPhase('participation');
        $clusterStatement2->setProcedure($testProcedure);
        $clusterStatement2->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement2->setText('Ich bin der Text für das cluster #2');

        $this->setReference('clusterStatement2', $clusterStatement2);
        $manager->persist($clusterStatement2);

        $statement12 = new Statement();
        $statement12->setAssignee($testUser);
        $statement12->setElement($testElement);
        $statement12->setExternId('1012');
        $statement12->setMeta((new StatementMeta())->setStatement($statement12));
        $statement12->setOrganisation($this->getReference('testOrgaPB'));
        $statement12->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement12->setParagraph($testParagraphVersion);
        $statement12->setParent($this->getReference('testStatementOtherOrga'));
        $statement12->setPhase('participation');
        $statement12->setProcedure($testProcedure);
        $statement12->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement12->setSubmit(new DateTime());
        $statement12->setSubmitType('system');
        $statement12->setText('Ich bin der Text für das Statement 12 ');
        $statement12->setUser($this->getReference('testUserPlanningOffice'));

        $manager->persist($statement12);
        $this->setReference('testStatementAssigned12', $statement12);

        $statement13 = new Statement();
        $statement13->setAssignee($testUser);
        $statement13->setElement($testElement);
        $statement13->setExternId('M1013');
        $statement13->setManual(true);
        $statement13->setMeta((new StatementMeta())->setStatement($statement13));
        $statement13->setOrganisation($this->getReference('testOrgaPB'));
        $statement13->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement13->setParagraph($testParagraphVersion);
        $statement13->setParent($this->getReference('testStatementOtherOrga'));
        $statement13->setPhase('participation');
        $statement13->setProcedure($testProcedure);
        $statement13->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement13->setSubmit(new DateTime());
        $statement13->setSubmitType('manual');
        $statement13->setText('Ich bin der Text für das Statement 13');
        $statement13->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testManualStatement', $statement13);
        $manager->persist($statement13);

        $statement14 = new Statement();
        $statement14->setAssignee($testUser);
        $statement14->setExternId('1014');
        $statement14->setMeta((new StatementMeta())->setStatement($statement14));
        $statement14->setOrganisation($this->getReference('testOrgaPB'));
        $statement14->setOriginal($statementOrig);
        $statement14->setParent($statementOrig);
        $statement14->setPhase('participation');
        $statement14->setProcedure($testProcedure);
        $statement14->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement14->setSubmit(new DateTime());
        $statement14->setSubmitType('manual');
        $statement14->setText('Ich bin der Text für das Statement 14');
        $statement14->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementParent', $statement14);
        $manager->persist($statement14);

        $statement15 = new Statement();
        $statement15->setAssignee($testUser);
        $statement15->setElement($testElement);
        $statement15->setExternId('1015');
        $statement15->setMeta((new StatementMeta())->setStatement($statement15));
        $statement15->setOrganisation($this->getReference('testOrgaPB'));
        $statement15->setOriginal($statementOrig);
        $statement15->setParagraph($testParagraphVersion);
        $statement15->setParent($statement14);
        $statement15->setPhase('participation');
        $statement15->setProcedure($testProcedure);
        $statement15->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement15->setSubmit(new DateTime());
        $statement15->setText('Ich bin der Text für das Statement 14');
        $statement15->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testCopiedStatement1', $statement15);
        $manager->persist($statement15);

        $statement16 = new Statement();
        $statement16->setAssignee($testUser);
        $statement16->setElement($testElement);
        $statement16->setExternId('1016');
        $statement16->setManual(true);
        $statement16->setMeta((new StatementMeta())->setStatement($statement16));
        $statement16->setOrganisation($this->getReference('testOrgaPB'));
        $statement16->setOriginal($statementOrig);
        $statement16->setParagraph($testParagraphVersion);
        $statement16->setParent($statement14);
        $statement16->setPhase('participation');
        $statement16->setProcedure($testProcedure);
        $statement16->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement16->setSubmit(new DateTime());
        $statement16->setText('Ich bin der Text für das Statement 14');
        $statement16->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testCopiedStatement2', $statement16);
        $manager->persist($statement16);

        $originalStatement17 = new Statement();
        $originalStatement17->setAssignee($testUser);
        $originalStatement17->setElement($testElement);
        $originalStatement17->setExternId('1017');
        $originalStatement17->setGdprConsent(new GdprConsent());
        $originalStatement17->setMeta((new StatementMeta())->setStatement($originalStatement17));
        $originalStatement17->setOrganisation($this->getReference('testOrgaPB'));
        $originalStatement17->setPhase('participation');
        $originalStatement17->setProcedure($testProcedure);
        $originalStatement17->setPublicVerified(Statement::PUBLICATION_PENDING);
        $originalStatement17->setSubmit(new DateTime());
        $originalStatement17->setText('Ich bin der Text für das Statement 17');
        $originalStatement17->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testOriginalStatementWithElementOnly', $originalStatement17);
        $manager->persist($originalStatement17);

        $statement17 = new Statement();
        $statement17->setAssignee($testUser);
        $statement17->setElement($testElement);
        $statement17->setExternId('1017');
        $statement17->setMeta((new StatementMeta())->setStatement($statement17));
        $statement17->setOrganisation($this->getReference('testOrgaPB'));
        $statement17->setOriginal($originalStatement17);
        $statement17->setParent($originalStatement17);
        $statement17->setPhase('participation');
        $statement17->setProcedure($testProcedure);
        $statement17->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement17->setSubmit(new DateTime());
        $statement17->setText('Ich bin der Text für das Statement 17');
        $statement17->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementWithElementOnly', $statement17);
        $manager->persist($statement17);

        $originalStatement18 = new Statement();
        $originalStatement18->setAssignee($testUser);
        $originalStatement18->setDocument($testDocument);
        $originalStatement18->setExternId('1018');
        $originalStatement18->setGdprConsent(new GdprConsent());
        $originalStatement18->setMeta((new StatementMeta())->setStatement($originalStatement18));
        $originalStatement18->setOrganisation($this->getReference('testOrgaPB'));
        $originalStatement18->setPhase('participation');
        $originalStatement18->setProcedure($testProcedure);
        $originalStatement18->setPublicVerified(Statement::PUBLICATION_PENDING);
        $originalStatement18->setSubmit(new DateTime());
        $originalStatement18->setText('Ich bin der Text für das Statement 18');
        $originalStatement18->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementWithDocumentOnly', $originalStatement18);
        $manager->persist($originalStatement18);

        $statement18 = new Statement();
        $statement18->setAssignee($testUser);
        $statement18->setDocument($testDocument);
        $statement18->setExternId('1018');
        $statement18->setMeta((new StatementMeta())->setStatement($statement18));
        $statement18->setOrganisation($this->getReference('testOrgaPB'));
        $statement18->setOriginal($originalStatement18);
        $statement18->setParent($originalStatement18);
        $statement18->setPhase('participation');
        $statement18->setProcedure($testProcedure);
        $statement18->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement18->setSubmit(new DateTime());
        $statement18->setText('Ich bin der Text für das Statement 18');
        $statement18->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementWithDocumentOnly', $statement18);
        $manager->persist($statement18);

        $originalStatement19 = new Statement();
        $originalStatement19->setAssignee($testUser);
        $originalStatement19->setExternId('1019');
        $originalStatement19->setGdprConsent(new GdprConsent());
        $originalStatement19->setMeta((new StatementMeta())->setStatement($originalStatement19));
        $originalStatement19->setOrganisation($this->getReference('testOrgaPB'));
        $originalStatement19->setParagraph($testParagraphVersion);
        $originalStatement19->setPhase('participation');
        $originalStatement19->setProcedure($testProcedure);
        $originalStatement19->setPublicVerified(Statement::PUBLICATION_PENDING);
        $originalStatement19->setSubmit(new DateTime());
        $originalStatement19->setText('Ich bin der Text für das Statement 19');
        $originalStatement19->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementWithParagraphOnly', $originalStatement19);
        $manager->persist($originalStatement19);

        $statement19 = new Statement();
        $statement19->setAssignee($testUser);
        $statement19->setExternId('1019');
        $statement19->setMeta((new StatementMeta())->setStatement($statement19));
        $statement19->setOrganisation($this->getReference('testOrgaPB'));
        $statement19->setOriginal($originalStatement19);
        $statement19->setParagraph($testParagraphVersion);
        $statement19->setParent($originalStatement19);
        $statement19->setPhase('participation');
        $statement19->setProcedure($testProcedure);
        $statement19->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement19->setSubmit(new DateTime());
        $statement19->setText('Ich bin der Text für das Statement 19');
        $statement19->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatementWithParagraphOnly', $statement19);
        $manager->persist($statement19);

        $statement20 = new Statement();
        $statement20->setAssignee($testUser);
        $statement20->setExternId('1020');
        $statement20->setMeta((new StatementMeta())->setStatement($statement20));
        $statement20->setOrganisation($this->getReference('testOrgaPB'));
        $statement20->setOriginal($statementOrig);
        $statement20->setParent($statementOrig);
        $statement20->setPhase('participation');
        $statement20->setProcedure($testProcedure);
        $statement20->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement20->setSubmit(new DateTime());
        $statement20->setText('Ich bin der Text für das Statement 20');
        $statement20->setUser($this->getReference('testUserPlanningOffice'));

        $this->setReference('testStatement20', $statement20);
        $manager->persist($statement20);

        $statementFragment10 = new StatementFragment();
        $statementFragment10->setArchivedDepartmentName('someDepartmentName');
        $statementFragment10->setArchivedOrgaName('someOrgaName');
        $statementFragment10->setAssignedToFbDate(new DateTime());
        $statementFragment10->setAssignee($testUser);
        $statementFragment10->setCounties([$testCounty]);
        $statementFragment10->setDepartment($this->getReference('testDepartment'));
        $statementFragment10->setDisplayId(00);
        $statementFragment10->setElement($testElement);
        $statementFragment10->setMunicipalities([$testMunicipality]);
        $statementFragment10->setParagraph($testParagraphVersion);
        $statementFragment10->setPriorityAreas([$testPriorityArea]);
        $statementFragment10->setProcedure($statement20->getProcedure());
        $statementFragment10->setStatement($statement20);
        $statementFragment10->setStatus('fragment.status.verified');
        $statementFragment10->setText('some kind of another text 10');
        $statementFragment10->setSortIndex(1);
        $manager->persist($statementFragment10);
        $this->setReference('testStatementFragment10', $statementFragment10);

        $statementFragment11 = new StatementFragment();
        $statementFragment11->setAssignedToFbDate(new DateTime());
        $statementFragment11->setCounties([$testCounty]);
        $statementFragment11->setDisplayId(0011);
        $statementFragment11->setMunicipalities([$testMunicipality]);
        $statementFragment11->setPriorityAreas([$testPriorityArea]);
        $statementFragment11->setProcedure($statement20->getProcedure());
        $statementFragment11->setStatement($statement20);
        $statementFragment11->setSortIndex(2);
        $statementFragment11->setText('some kind of another text 11');
        $manager->persist($statementFragment11);
        $this->setReference('statementFragment11', $statementFragment11);

        $originalStatement21 = new Statement();
        $originalStatement21->setExternId('1021');
        $originalStatement21->setGdprConsent(new GdprConsent());
        $originalStatement21->setInternId('21');
        $originalStatement21->setMeta((new StatementMeta())->setStatement($originalStatement21));
        $originalStatement21->setOrganisation($this->getReference('testOrgaPB'));
        $originalStatement21->setParent($statement2);
        $originalStatement21->setPhase('participation');
        $originalStatement21->setProcedure($testProcedure);
        $originalStatement21->setPublicVerified(Statement::PUBLICATION_PENDING);
        $originalStatement21->setSubmit(new DateTime());
        $originalStatement21->setText('Ich bin der Text für das Statement 21');
        $originalStatement21->setUser($this->getReference('testUserPlanningOffice'));
        $manager->persist($originalStatement21);
        $this->setReference('originalStatement21WithInternId', $originalStatement21);

        $statement21 = new Statement();
        $statement21->setAssignee($testUser);
        $statement21->setExternId('1021');
        $statement21->setMeta((new StatementMeta())->setStatement($statement21));
        $statement21->setOrganisation($this->getReference('testOrgaPB'));
        $statement21->setOriginal($originalStatement21);
        $statement21->setParent($originalStatement21);
        $statement21->setPhase('participation');
        $statement21->setProcedure($testProcedure);
        $statement21->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statement21->setSubmit(new DateTime());
        $statement21->setText('Ich bin der Text für das Statement 21');
        $statement21->setUser($this->getReference('testUserPlanningOffice'));
        $manager->persist($statement21);
        $this->setReference('testStatementWithInternID', $statement21);

        $statement22 = new Statement();
        $statement22->setAssignee($testUser);
        $statement22->setElement($testElement);
        $statement22->setExternId('1022');
        $statement22->setOrganisation($this->getReference('testOrgaPB'));
        $statement22->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement22->setParagraph($testParagraphVersion);
        $statement22->setParent($this->getReference('testStatementOtherOrga'));
        $statement22->setPhase('participation');
        $statement22->setProcedure($testProcedure);
        $statement22->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement22->setSubmit(new DateTime());
        $statement22->setSubmitType('system');
        $statement22->setText('Ich bin der Text für das Statement 22 ');
        $statement22->setUser($this->getReference('testUserPlanningOffice'));
        $statement22->setMeta((new StatementMeta())->setStatement($statement22));

        $this->setReference('testStatementAssigned22', $statement22);
        $manager->persist($statement22);

        $statementWithFile = new Statement();
        $statementWithFile->setElement($testElement);
        $statementWithFile->setExternId('1234');
        $statementWithFile->setMeta((new StatementMeta())->setStatement($statementWithFile));
        $statementWithFile->setOrganisation($this->getReference('testOrgaPB'));
        $statementWithFile->setOriginal($this->getReference('testStatementOtherOrga'));
        $statementWithFile->setParagraph($testParagraphVersion);
        $statementWithFile->setParent($this->getReference('testStatementOtherOrga'));
        $statementWithFile->setPhase('participation');
        $statementWithFile->setProcedure($testProcedure);
        $statementWithFile->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statementWithFile->setSubmit(new DateTime());
        $statementWithFile->setText('Ich bin der Text für das Statement mit files ');
        $statementWithFile->setUser($this->getReference('testUserPlanningOffice'));

        $this->addReferenceForStatement23WithAMap(
            $this->getReference('testUserPlanningOffice'),
            $testElement,
            $testParagraphVersion,
            $testProcedure,
            $testUser
        );

        /** @var File $file */
        $file = $this->getReference('testFile');
        $fileString = $file->getFilename().':'.$file->getIdent();

        $statementWithFile->setAssignee($testUser);
        $statementWithFile->setFiles([$fileString]);
        $statementWithFile->setSubmitType('system');

        $this->setReference('testStatementWithFile', $statementWithFile);
        $manager->persist($statementWithFile);

        // Cluster Statement3: unassigned Cluster
        $clusterStatement3 = new Statement();
        $clusterStatement3->setCluster([$statement22]);
        $clusterStatement3->setExternId('C1013');
        $clusterStatement3->setMeta((new StatementMeta())->setStatement($clusterStatement3));
        $clusterStatement3->setPhase('participation');
        $clusterStatement3->setProcedure($testProcedure);
        $clusterStatement3->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement3->setText('Ich bin der Text für das cluster #3');

        $this->setReference('clusterStatement3', $clusterStatement3);
        $manager->persist($clusterStatement3);

        $statementTestTagsBulkEdit1 = new Statement();
        $statementTestTagsBulkEdit1->setProcedure($testProcedure);
        $statementTestTagsBulkEdit1->setOriginal($statementOrig);
        $statementTestTagsBulkEdit1->setExternId('stmBulkEdit1');
        $statementTestTagsBulkEdit1->setPhase('participation');
        $statementTestTagsBulkEdit1->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statementTestTagsBulkEdit1->setText('Lorem ipsum');
        $this->setReference('statementTestTagsBulkEdit1', $statementTestTagsBulkEdit1);
        $manager->persist($statementTestTagsBulkEdit1);

        $testProcedure2 = $this->getReference('testProcedure2');

        $statementOrigWithToken = new Statement();
        $statementOrigWithToken->setElement($testElement);
        $statementOrigWithToken->setExternId('4363');
        $statementOrigWithToken->setGdprConsent(new GdprConsent());
        $statementOrigWithToken->setInternId('4363');
        $statementOrigWithToken->setMeta((new StatementMeta())->setStatement($statementOrigWithToken));
        $statementOrigWithToken->setOrganisation($testOrga);
        $statementOrigWithToken->setParagraph($testParagraphVersion);
        $statementOrigWithToken->setPhase('participation');
        $statementOrigWithToken->setProcedure($testProcedure2);
        $statementOrigWithToken->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statementOrigWithToken->setSubmitType('system');
        $statementOrigWithToken->setText('Ich bin der Text für das Statement');
        $statementOrigWithToken->setTitle('Statement Original');
        $statementOrigWithToken->setUser($testUser);

        $manager->persist($statementOrigWithToken);
        $this->setReference('testStatementOrigWithToken', $statementOrigWithToken);

        $statementWithToken = new Statement();
        $statementWithToken->addCounty($testCounty);
        $statementWithToken->addMunicipality($testMunicipality);
        $statementWithToken->addPriorityArea($testPriorityArea);
        $statementWithToken->setElement($testElement);
        $statementWithToken->setExternId('1000');
        $statementWithToken->setInternId('2222');
        $statementWithToken->setMeta((new StatementMeta())->setStatement($statementWithToken)->setAuthorName('Max Mustermann'));
        $statementWithToken->setOrganisation($testOrga);
        $statementWithToken->setOriginal($statementOrigWithToken);
        $statementWithToken->setParent($statementOrigWithToken);
        $statementWithToken->setParagraph($testParagraphVersion);
        $statementWithToken->setPhase('participation');
        $statementWithToken->setProcedure($testProcedure2);
        $statementWithToken->setPublicVerified(Statement::PUBLICATION_PENDING);
        $statementWithToken->setRepresentationCheck(true);
        $statementWithToken->setRepresents('representedOrganization');
        $statementWithToken->setSubmitType('system');
        $statementWithToken->setText('Ich bin der Text für das Statement');
        $statementWithToken->setTitle('Statement');
        $statementWithToken->setUser($testUser);
        $statementWithToken->setPiSegmentsProposalResourceUrl(self::PI_SEGMENTS_PROPOSAL_RESOURCE_URL_TEST);
        $this->setReference(self::TEST_STATEMENT_WITH_TOKEN, $statementWithToken);
        $manager->persist($statementWithToken);

        $manager->flush();

        $this->fillProcedureToDelete($manager);
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadElementsData::class,
            LoadFileData::class,
            LoadLocationData::class,
            LoadProcedureData::class,
            LoadTagData::class,
            LoadUserData::class,
            LoadProcedurePersonData::class,
        ];
    }

    protected function fillProcedureToDelete(ObjectManager $manager)
    {
        /** @var Procedure $procedureToDelete */
        $procedureToDelete = $this->getReference('procedureToDelete');
        /** @var User $testUser */
        $testUser = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var Elements $testElement */
        $testElement = $this->getReference('testelement9');
        /** @var ParagraphVersion $testParagraphVersion */
        $testParagraphVersion = $this->getReference('testparagraph4Version');

        // ClusterStatements:
        $clusterStatement22OfProcedureToDelete = new Statement();
        $clusterStatement22OfProcedureToDelete->setAssignee($testUser);
        $clusterStatement22OfProcedureToDelete->setElement($testElement);
        $clusterStatement22OfProcedureToDelete->setExternId('2553');
        $clusterStatement22OfProcedureToDelete->setMeta((new StatementMeta())->setStatement($clusterStatement22OfProcedureToDelete));
        $clusterStatement22OfProcedureToDelete->setOrganisation($this->getReference('testOrgaPB'));
        $clusterStatement22OfProcedureToDelete->setOriginal($this->getReference('testStatementOtherOrga'));
        $clusterStatement22OfProcedureToDelete->setParagraph($testParagraphVersion);
        $clusterStatement22OfProcedureToDelete->setParent($this->getReference('testStatementOtherOrga'));
        $clusterStatement22OfProcedureToDelete->setPhase('participation');
        $clusterStatement22OfProcedureToDelete->setProcedure($procedureToDelete);
        $clusterStatement22OfProcedureToDelete->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $clusterStatement22OfProcedureToDelete->setSubmit(new DateTime());
        $clusterStatement22OfProcedureToDelete->setSubmitType('system');
        $clusterStatement22OfProcedureToDelete->setText('Ich bin der Text für das Statement 22 ');
        $clusterStatement22OfProcedureToDelete->setUser($this->getReference('testUserPlanningOffice'));
        $manager->persist($clusterStatement22OfProcedureToDelete);
        $this->setReference('clusterStatement22OfProcedureToDelete', $clusterStatement22OfProcedureToDelete);

        $clusterStatement11OfProcedureToDelete = new Statement();
        $clusterStatement11OfProcedureToDelete->setAssignee($testUser);
        $clusterStatement11OfProcedureToDelete->setExternId('2554');
        $clusterStatement11OfProcedureToDelete->setMeta((new StatementMeta())->setStatement($clusterStatement11OfProcedureToDelete));
        $clusterStatement11OfProcedureToDelete->setParagraph($testParagraphVersion);
        $clusterStatement11OfProcedureToDelete->setPhase('participation');
        $clusterStatement11OfProcedureToDelete->setProcedure($procedureToDelete);
        $clusterStatement11OfProcedureToDelete->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement11OfProcedureToDelete->setText('Ich bin der Text für das Statement 10 ');
        $this->setReference('clusterStatement11OfProcedureToDelete', $clusterStatement11OfProcedureToDelete);
        $manager->persist($clusterStatement11OfProcedureToDelete);

        // headstatement
        $clusterStatement1OfProcedureToDelete = new Statement();
        $clusterStatement1OfProcedureToDelete->setCluster([$clusterStatement22OfProcedureToDelete, $clusterStatement11OfProcedureToDelete]);
        $clusterStatement1OfProcedureToDelete->setClusterStatement(true);
        $clusterStatement1OfProcedureToDelete->setExternId('G1056');
        $clusterStatement1OfProcedureToDelete->setMeta((new StatementMeta())->setStatement($clusterStatement1OfProcedureToDelete));
        $clusterStatement1OfProcedureToDelete->setPhase('participation');
        $clusterStatement1OfProcedureToDelete->setProcedure($procedureToDelete);
        $clusterStatement1OfProcedureToDelete->setPublicVerified(Statement::PUBLICATION_PENDING);
        $clusterStatement1OfProcedureToDelete->setText('Ich bin der Text für das cluster #1056');
        $manager->persist($clusterStatement1OfProcedureToDelete);
        $this->setReference('clusterStatement1OfProcedureToDelete', $clusterStatement1OfProcedureToDelete);

        // original Statements
        $emptyOriginalStatementOfProcedureToDelete = new Statement();
        $emptyOriginalStatementOfProcedureToDelete->setAssignee($testUser);
        $emptyOriginalStatementOfProcedureToDelete->setExternId('2444');
        $emptyOriginalStatementOfProcedureToDelete->setMeta((new StatementMeta())->setStatement($emptyOriginalStatementOfProcedureToDelete));
        $emptyOriginalStatementOfProcedureToDelete->setOriginal(null);
        $emptyOriginalStatementOfProcedureToDelete->setParagraph($testParagraphVersion);
        $emptyOriginalStatementOfProcedureToDelete->setParent(null);
        $emptyOriginalStatementOfProcedureToDelete->setPhase('participation');
        $emptyOriginalStatementOfProcedureToDelete->setProcedure($procedureToDelete);
        $emptyOriginalStatementOfProcedureToDelete->setPublicVerified(Statement::PUBLICATION_PENDING);
        $emptyOriginalStatementOfProcedureToDelete->setText('Ich bin der Text für das Statement 2444');
        $this->setReference('emptyOriginalStatementOfProcedureToDelete', $emptyOriginalStatementOfProcedureToDelete);
        $manager->persist($emptyOriginalStatementOfProcedureToDelete);

        $originalStatement2OfProcedureToDelete = new Statement();
        $originalStatement2OfProcedureToDelete->setAssignee($testUser);
        $originalStatement2OfProcedureToDelete->setExternId('2445');
        $originalStatement2OfProcedureToDelete->setMeta((new StatementMeta())->setStatement($originalStatement2OfProcedureToDelete));
        $originalStatement2OfProcedureToDelete->setOriginal(null);
        $originalStatement2OfProcedureToDelete->setParagraph($testParagraphVersion);
        $originalStatement2OfProcedureToDelete->setParent(null);
        $originalStatement2OfProcedureToDelete->setPhase('participation');
        $originalStatement2OfProcedureToDelete->setProcedure($procedureToDelete);
        $originalStatement2OfProcedureToDelete->setPublicVerified(Statement::PUBLICATION_PENDING);
        $originalStatement2OfProcedureToDelete->setText('Ich bin der Text für das Statement 2445');
        $this->setReference('originalStatement2OfProcedureToDelete', $originalStatement2OfProcedureToDelete);
        $manager->persist($originalStatement2OfProcedureToDelete);

        // children
        $normalStatement = new Statement();
        $normalStatement->setAssignee($testUser);
        $normalStatement->setExternId('2445');
        $normalStatement->setMeta((new StatementMeta())->setStatement($normalStatement));
        $normalStatement->setOriginal($originalStatement2OfProcedureToDelete);
        $normalStatement->setParagraph($testParagraphVersion);
        $normalStatement->setParent($originalStatement2OfProcedureToDelete);
        $normalStatement->setPhase('participation');
        $normalStatement->setProcedure($procedureToDelete);
        $normalStatement->setPublicVerified(Statement::PUBLICATION_PENDING);
        $normalStatement->setText('Ich bin der Text für das Statement 25665');
        $this->setReference('normalStatement', $normalStatement);
        $manager->persist($normalStatement);

        $normalStatement2 = new Statement();
        $normalStatement2->setAssignee($testUser);
        $normalStatement2->setExternId('3456');
        $normalStatement2->setOriginal($originalStatement2OfProcedureToDelete);
        $normalStatement2->setParagraph($testParagraphVersion);
        $normalStatement2->setParent($originalStatement2OfProcedureToDelete);
        $normalStatement2->setPhase('participation');
        $normalStatement2->setProcedure($procedureToDelete);
        $normalStatement2->setPublicVerified(Statement::PUBLICATION_PENDING);
        $normalStatement2->setText('Ich bin der Text für das Statement 3456');

        $normalStatement2->setMeta((new StatementMeta())->setStatement($normalStatement2));
        $this->setReference('normalStatement2', $normalStatement2);
        $manager->persist($normalStatement2);

        // copies
        $copyOfnNormalStatement2 = new Statement();
        $copyOfnNormalStatement2->setAssignee($testUser);
        $copyOfnNormalStatement2->setExternId('34562');
        $copyOfnNormalStatement2->setMeta((new StatementMeta())->setStatement($copyOfnNormalStatement2));
        $copyOfnNormalStatement2->setOriginal($originalStatement2OfProcedureToDelete);
        $copyOfnNormalStatement2->setParagraph($testParagraphVersion);
        $copyOfnNormalStatement2->setParent($normalStatement2);
        $copyOfnNormalStatement2->setPhase('participation');
        $copyOfnNormalStatement2->setProcedure($procedureToDelete);
        $copyOfnNormalStatement2->setPublicVerified(Statement::PUBLICATION_PENDING);
        $copyOfnNormalStatement2->setText('Ich bin der Text für das kopierte Statement 34562');
        $this->setReference('copyOfnNormalStatement2', $copyOfnNormalStatement2);
        $manager->persist($copyOfnNormalStatement2);

        // placeholder in this procedure to delete
        $placeholderStatement = new Statement();
        $placeholderStatement->setAssignee($testUser);
        $placeholderStatement->setExternId('6666');
        $placeholderStatement->setMeta((new StatementMeta())->setStatement($placeholderStatement));
        $placeholderStatement->setOriginal($originalStatement2OfProcedureToDelete);
        $placeholderStatement->setParagraph($testParagraphVersion);
        $placeholderStatement->setParent($normalStatement2);
        $placeholderStatement->setPhase('participation');
        $placeholderStatement->setPlaceholderStatement(null);
        $placeholderStatement->setProcedure($procedureToDelete);
        $placeholderStatement->setPublicVerified(Statement::PUBLICATION_PENDING);
        $placeholderStatement->setText('Ich bin der Text für das Statement 6666');
        $this->setReference('placeholderStatement', $placeholderStatement);
        $manager->persist($placeholderStatement);

        // placeholder in another procedure
        $placeholderStatementInAnotherProcedure = new Statement();
        $placeholderStatementInAnotherProcedure->setAssignee($testUser);
        $placeholderStatementInAnotherProcedure->setExternId('555');
        $placeholderStatementInAnotherProcedure->setMeta((new StatementMeta())->setStatement($placeholderStatementInAnotherProcedure));
        $placeholderStatementInAnotherProcedure->setOriginal($this->getReference('testStatementOtherOrga'));
        $placeholderStatementInAnotherProcedure->setParagraph($testParagraphVersion);
        $placeholderStatementInAnotherProcedure->setParent($this->getReference('testStatementOtherOrga'));
        $placeholderStatementInAnotherProcedure->setPhase('participation');
        $placeholderStatementInAnotherProcedure->setPlaceholderStatement(null);
        $placeholderStatementInAnotherProcedure->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $placeholderStatementInAnotherProcedure->setPublicVerified(Statement::PUBLICATION_PENDING);
        $placeholderStatementInAnotherProcedure->setText('Ich bin der Text für das Statement 555');
        $this->setReference('placeholderStatementInAnotherProcedure', $placeholderStatementInAnotherProcedure);
        $manager->persist($placeholderStatementInAnotherProcedure);

        // moved into another procedure statement:
        $movedStatementInAnotherProcedure = new Statement();
        $movedStatementInAnotherProcedure->setAssignee($testUser);
        $movedStatementInAnotherProcedure->setExternId('7777');
        $movedStatementInAnotherProcedure->setMeta((new StatementMeta())->setStatement($movedStatementInAnotherProcedure));
        $movedStatementInAnotherProcedure->setOriginal($originalStatement2OfProcedureToDelete);
        $movedStatementInAnotherProcedure->setParagraph($testParagraphVersion);
        $movedStatementInAnotherProcedure->setParent($normalStatement2);
        $movedStatementInAnotherProcedure->setPhase('participation');
        $movedStatementInAnotherProcedure->setPlaceholderStatement($placeholderStatement);
        $movedStatementInAnotherProcedure->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $movedStatementInAnotherProcedure->setPublicVerified(Statement::PUBLICATION_PENDING);
        $movedStatementInAnotherProcedure->setText('Ich bin der Text für das Statement 7777');
        $movedStatementInAnotherProcedure->wasMoved();
        $this->setReference('movedStatementInAnotherProcedure', $movedStatementInAnotherProcedure);
        $manager->persist($movedStatementInAnotherProcedure);
        $placeholderStatement->setMovedStatement($movedStatementInAnotherProcedure);
        $manager->persist($placeholderStatement);

        // moved into procedure to delete statement:
        $movedStatementInThisProcedure = new Statement();
        $movedStatementInThisProcedure->setAssignee($testUser);
        $movedStatementInThisProcedure->setExternId('888');
        $movedStatementInThisProcedure->setMeta((new StatementMeta())->setStatement($movedStatementInThisProcedure));
        $movedStatementInThisProcedure->setOriginal($originalStatement2OfProcedureToDelete);
        $movedStatementInThisProcedure->setParagraph($testParagraphVersion);
        $movedStatementInThisProcedure->setParent($normalStatement2);
        $movedStatementInThisProcedure->setPhase('participation');
        $movedStatementInThisProcedure->setPlaceholderStatement($placeholderStatementInAnotherProcedure);
        $movedStatementInThisProcedure->setProcedure($procedureToDelete);
        $movedStatementInThisProcedure->setPublicVerified(Statement::PUBLICATION_PENDING);
        $movedStatementInThisProcedure->setText('Ich bin der Text für das Statement 888');
        $this->setReference('movedStatementInThisProcedure', $movedStatementInThisProcedure);
        $manager->persist($movedStatementInThisProcedure);
        $placeholderStatementInAnotherProcedure->setMovedStatement($movedStatementInThisProcedure);
        $manager->persist($placeholderStatementInAnotherProcedure);

        $this->createManualStatementInPublicParticipationPhase();

        $manager->flush();
    }

    private function addReferenceForStatement23WithAMap($user, $element, $paragraphVersion, $procedure, $assignee)
    {
        $polygon = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon",'.
            '"coordinates":[[[539432.055536051,6042149.304450158],[536072.0488160376,5999813.219777989],'.
            '[565640.1079521559,6000821.221793992],[539432.055536051,6042149.304450158]]]},"properties":'.
            '{"metadata":{"featureLayerExtent":[536072.0488160376,5999813.219777989,565640.1079521559,'.
            '6042149.304450158],"printLayers":[{"layerName":"c8834c00ec2611eabba9782bcb0d78b1",'.
            '"layerTitle":"Digitale Topographische Karte","tiles":[{"position":{"z":0,"x":1,"y":0},'.
            '"url":"http://service.schleswig-holstein.de/OGCFassade/SH_WMS_BDDcol_BOBSH.aspx?'.
            'SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&FORMAT=image%2Fpng&TRANSPARENT=true&'.
            'LAYERS=0%2C1%2C2%2C3%2C4%2C5%2C6&WIDTH=256&HEIGHT=256&CRS=EPSG%3A25832&STYLES=&'.
            'BBOX=528013.5820323441%2C6003726.367967656%2C614029.7540646882%2C6089742.54",'.
            '"tileSize":256,"tileExtent":[528013.5820323441,6003726.367967656,614029.7540646882,'.
            '6089742.54]},{"position":{"z":0,"x":1,"y":1},"url":"http://service.schleswig-holstein.de/'.
            'OGCFassade/SH_WMS_BDDcol_BOBSH.aspx?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&'.
            'FORMAT=image%2Fpng&TRANSPARENT=true&LAYERS=0%2C1%2C2%2C3%2C4%2C5%2C6&WIDTH=256&'.
            'HEIGHT=256&CRS=EPSG%3A25832&STYLES=&BBOX=528013.5820323441%2C5917710.195935312'.
            '%2C614029.7540646882%2C6003726.367967656","tileSize":256,"tileExtent":[528013.5820323441,'.
            '5917710.195935312,614029.7540646882,6003726.367967656]}]}]}}}]}';

        $statement23WithPolygonAndMap = new Statement();
        $statement23WithPolygonAndMap->setPolygon($polygon);
        $statement23WithPolygonAndMap->setAssignee($assignee);
        $statement23WithPolygonAndMap->setElement($element);
        $statement23WithPolygonAndMap->setExternId('1023');
        $statement23WithPolygonAndMap->setOrganisation($this->getReference('testOrgaPB'));
        $statement23WithPolygonAndMap->setOriginal($this->getReference('testStatementOtherOrga'));
        $statement23WithPolygonAndMap->setParagraph($paragraphVersion);
        $statement23WithPolygonAndMap->setParent($this->getReference('testStatementOtherOrga'));
        $statement23WithPolygonAndMap->setPhase('participation');
        $statement23WithPolygonAndMap->setProcedure($procedure);
        $statement23WithPolygonAndMap->setPublicVerified(Statement::PUBLICATION_APPROVED);
        $statement23WithPolygonAndMap->setSubmit(new DateTime());
        $statement23WithPolygonAndMap->setSubmitType('system');
        $statement23WithPolygonAndMap->setText('Text of Statement 23. This statement has a map.');
        $statement23WithPolygonAndMap->setUser($user);
        $statement23WithPolygonAndMap->setMeta((new StatementMeta())->setStatement($statement23WithPolygonAndMap));

        $this->setReference('statement23WithPolygonAndMap', $statement23WithPolygonAndMap);

        $this->manager->persist($statement23WithPolygonAndMap);
    }

    private function createManualStatementInPublicParticipationPhase(): void
    {
        $statement = new Statement();
        $statement->setExternId('9876')
        ->setPhase('participation')
        ->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE))
        ->setPublicVerified(Statement::PUBLICATION_PENDING)
        ->setText('bla');

        $statement->setManual(true);
        $statement->setAssignee($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $statement->setOriginal($this->getReference('testStatementOrig'));
        $statement->setMeta((new StatementMeta())->setStatement($statement));

        $this->setReference(self::MANUAL_STATEMENT_IN_PUBLIC_PARTICIPATION_PHASE, $statement);
        $this->manager->persist($statement);
    }
}
