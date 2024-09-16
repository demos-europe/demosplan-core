<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadProcedureData extends TestFixture implements DependentFixtureInterface
{
    final public const TESTPROCEDURE = 'testProcedure';
    final public const TESTPROCEDURE_DRAFTSTATEMENT = 'testDraftStatement';
    final public const TESTPROCEDURE_DRAFTSTATEMENTVERSION = 'testdraftStatementVersion';
    final public const TESTPROCEDURE_GISLAYER = 'testGisLayer';
    final public const TESTPROCEDURE_NEWS = 'testNews';
    final public const TESTPROCEDURE_PARAGRAPHPROCEDURE = 'testParagraphProcedure';
    final public const TESTPROCEDURE_PARAGRAPHVERSION = 'testParagraphVersion';
    final public const TESTPROCEDURE_REPORT = 'testReport';
    final public const TESTPROCEDURE_SINGLEDOCUMENT = 'testSingleDocument1';
    final public const TESTPROCEDURE_SINGLEDOCUMENTELEMENT = 'testSingleDocumentElement';
    final public const TESTPROCEDURE_SINGLEDOCUMENTVERSION = 'testSingleDocumentVersion';
    final public const TESTPROCEDURE_STATEMENT = 'testStatement';
    final public const TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE = 'procedureInPublicParticipationPhase';
    final public const TEST_PROCEDURE_2 = 'testProcedure2';

    private $currentDate;
    private $existingExternalPhasesWrite;
    private $existingInternalPhasesWrite;
    private $manager;
    private $testOrgaFP;
    private $testUser;

    public function __construct(EntityManagerInterface $entityManager, GlobalConfigInterface $globalConfig)
    {
        parent::__construct($entityManager);

        $this->existingInternalPhasesWrite = $globalConfig->getInternalPhaseKeys('write');
        $this->existingExternalPhasesWrite = $globalConfig->getExternalPhaseKeys('write');
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        $this->currentDate = new DateTime();
        /* @var Orga $testOrgaFP */
        $this->testOrgaFP = $this->getReference('testOrgaFP');
        /* @var User $testUser */
        $this->testUser = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // Erstelle die Masterblaupause.
        // sie hat eine festgeschriebene Id, kann aber nicht ohne weiteres via doctrine
        // direkt gesetzt werden, deshalb wird die Id in den Tests dynamisiert
        $procedureMaster = new Procedure();
        $procedureMaster
            ->setName('Master')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('MasterOrga')
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('test')
            ->setPublicParticipationPhase('configuration')
            ->addSlug(new Slug('procedureMasterSlug'))
            ->setMaster(true)
            ->setMasterTemplate(true)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedureMaster));

        $manager->persist($procedureMaster);
        $manager->flush();

        $this->loadMasterBluePrintFileElements($manager, $procedureMaster);
        $this->loadMasterBluePrintParagraphElements($manager, $procedureMaster);

        $this->setReference('masterBlaupause', $procedureMaster);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedureMaster)
            ->setMapExtent('441997.41,5923055.13,611330.65,6089742.54')
            ->setBoundingBox('441997.41,5923055.13,611330.65,6089742.54')
            ->setPlanningArea('');

        $manager->persist($procedureSettings);

        $this->loadTestProcedure();

        $procedure2 = new Procedure();
        $procedure2->setName('TestProcedure2')
            ->setShortUrl('Lokstedt62')
            ->setStartDate($this->currentDate)
            ->setEndDate($this->currentDate)
            ->setOrga($this->testOrgaFP)
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setOrgaName('testOrgaFP')
            ->setDesc('testDesc')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation')
            ->setStep('test')
            ->setLogo('test')
            ->setExternId('test')
            ->setPlisId('test')
            ->setClosed(false)
            ->setDeleted(false)
            ->setMaster(false)
            ->setExternalName('testExternalName')
            ->setExternalDesc('testExternalDesc')
            ->setPublicParticipation(false)
            ->setPublicParticipationStep('asdfasdf')
            ->setMunicipalCode('12345')
            ->setPublicParticipationPhase('configuration')
            ->setPublicParticipationStartDate($this->currentDate)
            ->setPublicParticipationEndDate($this->currentDate)
            ->setCreatedDate($this->currentDate)
            ->setPublicParticipationContact('testPPContact')
            ->setPublicParticipationPublicationEnabled(false)
            ->setLocationName('testLocationName')
            ->setLocationPostCode('12345')
            ->setClosedDate($this->currentDate)
            ->setDeletedDate($this->currentDate)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure2))
            ->addSlug(new Slug('procedure2NeverUsedSlug'));
        $procedure2->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));

        $now = Carbon::now();
        $tomorrow = $now->addDays(7);
        $procedure2->setEndDate($tomorrow->toDateTime());

        $manager->persist($procedure2);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedure2)
            ->setPlanningArea('');
        $manager->persist($procedureSettings);

        $procedure2 = new Procedure();
        $procedure2
            ->setName('Procedure number two')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('test second')
            ->setPhase('configuration')
            ->setPublicParticipationPhase('configuration')
            ->setMaster(false)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure2))
            ->addSlug(new Slug('procedure2Slug'));

        $manager->persist($procedure2);

        $this->setReference(self::TEST_PROCEDURE_2, $procedure2);
        $this->setReference('defaultExportFieldsConfiguration', $procedure2->getDefaultExportFieldsConfiguration());

        $archivedProcedure = new Procedure();
        $archivedProcedure->setName('ArchivedProcedure')
            ->setPhase('closed')
            ->setPublicParticipationPhase('closed')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($archivedProcedure))
            ->addSlug(new Slug('procedureArchivedSlug'));

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($archivedProcedure)
            ->setPlanningArea('');
        $archivedProcedure->setSettings($procedureSettings);
        $manager->persist($procedureSettings);

        $this->setReference('archivedProcedure', $archivedProcedure);
        $manager->persist($archivedProcedure);

        $semiArchivedProcedure = new Procedure();
        $semiArchivedProcedure->setName('SemiArchivedProcedure')
            ->setPhase('closed')
            ->setPublicParticipationPhase('configuration')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($semiArchivedProcedure))
            ->addSlug(new Slug('procedureSemiArchivedSlug'));
        $semiArchivedProcedure->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($semiArchivedProcedure)
            ->setPlanningArea('');
        $semiArchivedProcedure->setSettings($procedureSettings);
        $manager->persist($procedureSettings);

        $this->setReference('semiArchivedProcedure', $semiArchivedProcedure);
        $manager->persist($semiArchivedProcedure);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedure2)
            ->setPlanningArea('');
        $manager->persist($procedureSettings);

        $procedure3 = new Procedure();
        $procedure3
            ->setName('Procedure number three')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->testOrgaFP])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('test third')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'configuration')
            ->setPublicParticipationPhase('configuration')
            ->setMaster(false)
            ->setPublicParticipationPublicationEnabled(false)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure3))
            ->addSlug(new Slug('procedure3Slug'));
        $procedure3->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));

        $manager->persist($procedure3);

        $this->setReference('testProcedure3', $procedure3);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedure3)
            ->setPlanningArea('');
        $manager->persist($procedureSettings);

        $procedure4 = new Procedure();
        $procedure4
            ->setName('Master Procedure number four')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->getReference('testOrgaFP')])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('test fourth')
            ->setPhase('configuration')
            ->setPublicParticipationPhase('configuration')
            ->setMaster(true)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure4))
            ->addSlug(new Slug('procedure4Slug'));
        $procedure4->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));

        $manager->persist($procedure4);

        $this->setReference('testProcedure4', $procedure4);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedure4)
            ->setPlanningArea('');
        $manager->persist($procedureSettings);

        // create GisLayerCategory for MasterBlueprint
        $gisLayerCategoryMaster = new GisLayerCategory();
        $gisLayerCategoryMaster->setName('testGisLayerCategoryOfMaster');
        $gisLayerCategoryMaster->setProcedure($this->getReference('masterBlaupause'));
        $manager->persist($gisLayerCategoryMaster);

        $gisLayer4 = new GisLayer();
        $gisLayer4->setProcedureId($procedureMaster->getId());
        $gisLayer4->setBplan(false);
        $gisLayer4->setDefaultVisibility(false);
        $gisLayer4->setDeleted(false);
        $gisLayer4->setLegend('legende_2015.pdf:8299a35b-5414-4739-9024-1aa1ab4f62f5:481309:application/pdf');
        $gisLayer4->setLayers('0,1,2,3,4,5,6');
        $gisLayer4->setName('blaupause1');
        $gisLayer4->setOpacity('100');
        $gisLayer4->setOrder(0);
        $gisLayer4->setPrint(false);
        $gisLayer4->setScope(false);
        $gisLayer4->setType('base');
        $gisLayer4->setUrl('http://www.blaupause1.de');
        $gisLayer4->setEnabled(true);
        $gisLayer4->setGlobalLayer(false);
        $gisLayer4->setXplan(false);
        $gisLayer4->setIsMiniMap(false);
        $gisLayer4->setCategory($gisLayerCategoryMaster);

        $manager->persist($gisLayer4);
        $this->setReference('gisLayer4', $gisLayer4);

        $gisLayer2 = new GisLayer();
        $gisLayer2->setProcedureId($procedureMaster->getId());
        $gisLayer2->setBplan(false);
        $gisLayer2->setDefaultVisibility(false);
        $gisLayer2->setDeleted(false);
        $gisLayer2->setLegend('legende_2015.pdf:8299a35b-5414-4739-9024-1aa1ab4f62f5:481309:application/pdf');
        $gisLayer2->setLayers('0,1,2,3,4,5,6');
        $gisLayer2->setName('blaupause2');
        $gisLayer2->setOpacity('100');
        $gisLayer2->setOrder(0);
        $gisLayer2->setPrint(false);
        $gisLayer2->setScope(false);
        $gisLayer2->setType('base');
        $gisLayer2->setUrl('http://www.blaupause2.de');
        $gisLayer2->setEnabled(true);
        $gisLayer2->setGlobalLayer(false);
        $gisLayer2->setXplan(false);
        $gisLayer2->setIsMiniMap(false);
        $gisLayer2->setCategory($gisLayerCategoryMaster);

        $manager->persist($gisLayer2);
        $this->setReference('gisLayer2', $gisLayer2);

        $news3 = new News();
        $news3->setTitle('News1 blaupause Title');
        $news3->setDescription('Ich bin die Description der blaupause News1');
        $news3->setText('Ich bin der Text der blaupause News1');
        $news3->setPId($procedureMaster->getId());
        $news3->setPicture('');
        $news3->setPictitle('');
        $news3->setPdf('');
        $news3->setPdftitle('');
        $news3->setEnabled(true);
        $news3->setDeleted(false);
        $news3->setCreateDate(new DateTime());
        $news3->setModifyDate(new DateTime());
        $news3->setDeleteDate(new DateTime());
        $news3->setRoles(
            [$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination'), $this->getReference('testRoleCitiz')]
        );

        $manager->persist($news3);
        $this->setReference('news3', $news3);

        $news2 = new News();
        $news2->setTitle('News2 blaupause Title');
        $news2->setDescription('Ich bin die Description der blaupause News2');
        $news2->setText('Ich bin der Text der blaupause News2');
        $news2->setPId($procedureMaster->getId());
        $news2->setPicture('');
        $news2->setPictitle('');
        $news2->setPdf('');
        $news2->setPdftitle('');
        $news2->setEnabled(true);
        $news2->setDeleted(false);
        $news2->setCreateDate(new DateTime());
        $news2->setModifyDate(new DateTime());
        $news2->setDeleteDate(new DateTime());
        $news2->setRoles(
            [$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination'), $this->getReference('testRoleCitiz')]
        );

        $manager->persist($news2);
        $this->setReference('news2', $news2);

        $procedureMaster2 = new Procedure();
        $procedureMaster2
            ->setName('Master2')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('MasterOrga')
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('test2')
            ->setPublicParticipationPhase('configuration')
            ->setMaster(true)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedureMaster2))
            ->addSlug(new Slug('procedureMaster2Slug'));
        $procedureMaster2->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));

        $manager->persist($procedureMaster2);
        $this->setReference('masterBlaupause2', $procedureMaster2);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedureMaster2)
            ->setMapExtent('441997.41,5923055.13,611330.65,6089742.54')
            ->setBoundingBox('441997.41,5923055.13,611330.65,6089742.54')
            ->setPlanningArea('');
        $manager->persist($procedureSettings);

        $this->createBlueprintProcedure();
        $this->createProcedureToDelete();
        $this->createProcedureInPublicConsultationPhase();

        $manager->flush();
    }

    private function loadTestProcedure(): void
    {
        /** @var Customer $customer */
        $customer = $this->getReference('testCustomerBrandenburg');

        $procedure = new Procedure();
        $procedure->setName('TestProcedure1')
            ->setShortUrl('Lokstedt64')
            ->setStartDate($this->currentDate)
            ->setEndDate($this->currentDate)
            ->setCustomer($customer)
            ->setOrga($this->testOrgaFP)
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->setOrgaName('testOrgaFP')
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('testDesc')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation')
            ->setStep('test')
            ->setLogo('test')
            ->setExternId('test')
            ->setPlisId('test')
            ->setClosed(false)
            ->setDeleted(false)
            ->setMaster(false)
            ->setExternalName('testExternalName')
            ->setExternalDesc('testExternalDesc')
            ->setPublicParticipation(false)
            ->setPublicParticipationStep('asdfasdf')
            ->setMunicipalCode('12345')
            ->setPublicParticipationPhase($this->existingExternalPhasesWrite[0] ?? 'participation')
            ->setPublicParticipationStartDate($this->currentDate)
            ->setPublicParticipationEndDate($this->currentDate)
            ->setCreatedDate($this->currentDate)
            ->setPublicParticipationContact('testPPContact')
            ->setLocationName('testLocationName')
            ->setLocationPostCode('12456')
            ->setClosedDate($this->currentDate)
            ->setDeletedDate($this->currentDate)
            ->setDataInputOrganisations([$this->getReference('dataInputOrga')])
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure))
            ->addSlug(new Slug('procedure1Slug'));
        $procedure->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));
        $procedure->setProcedureBehaviorDefinition($this->getReference(LoadProcedureBehaviorDefinitionData::PROCEDURE_TESTPROCEDURE));
        $procedure->setProcedureUiDefinition($this->getReference(LoadProcedureUiDefinitionData::PROCEDURE_TESTPROCEDURE));
        $procedure->setStatementFormDefinition($this->getReference(LoadStatementFormDefinitionData::PROCEDURE_TESTPROCEDURE));
        $this->manager->persist($procedure);
        $customer->setDefaultProcedureBlueprint($procedure);

        // related entities
        $this->loadTestProcedureNews($procedure);
        $this->loadTestProcedureGisLayer($procedure);
        $element = $this->loadTestProcedureSingleDocumentElement($procedure);
        $testSingleDocumentElement = $this->loadTestProcedureParagraphProcedure($procedure);
        $this->loadTestProcedureParagraphVersion($procedure, $testSingleDocumentElement);
        $locationArray = $this->loadTestProcedureLocationInformation();
        $this->loadTestProcedureStatement($procedure, $locationArray);
        $this->loadTestProcedureSingleDocument($procedure, $element);
        $this->loadTestProcedureSingleDocumentVersion($procedure, $element);
        $this->loadTestProcedureManualListSort($procedure);
        $this->loadTestProcedureReportEntry($procedure, $customer);
        $draftStatement = $this->loadTestProcedureDraftStatement($procedure);
        $this->loadTestProcedureDraftStatementVersion($procedure, $draftStatement);
        $this->loadProcedureTestProcedureSettings($procedure);
        $this->loadTestProcedureFileElement($procedure);

        $this->setReference(self::TESTPROCEDURE, $procedure);
    }

    private function loadTestProcedureNews(Procedure $procedure): void
    {
        $news1 = new News();
        $news1->setTitle('News1 Title');
        $news1->setDescription('Ich bin die Description der News1');
        $news1->setText('Ich bin der Text der News1');
        $news1->setPId($procedure->getId());
        $news1->setPicture('BOB-SH_Logo.jpg:89c42e9b-9aaa-11e5-9c91-005056ae0004:6169:image/jpeg');
        $news1->setPictitle('toDeletePicture');
        $news1->setPdf('8974387587345');
        $news1->setPdftitle('toDeletePDF');
        $news1->setEnabled(true);
        $news1->setDeleted(false);
        $news1->setCreateDate(new DateTime());
        $news1->setModifyDate(new DateTime());
        $news1->setDeleteDate(new DateTime());
        $news1->setRoles(
            [
                $this->getReference('testRoleFP'),
                $this->getReference('testRolePublicAgencyCoordination'),
                $this->getReference('testRoleCitiz'),
            ]
        );
        $this->setReference(self::TESTPROCEDURE_NEWS, $news1);
        $this->manager->persist($news1);
    }

    private function loadTestProcedureGisLayer(Procedure $procedure): void
    {
        $gisLayer1 = new GisLayer();
        $gisLayer1->setBplan(false);
        $gisLayer1->setDefaultVisibility(false);
        $gisLayer1->setDeleted(false);
        $gisLayer1->setLegend('legende_2015.pdf:8299a35b-5414-4739-9024-1aa1ab4f62f5:481309:application/pdf');
        $gisLayer1->setLayers('0,1,2,3,4,5,6');
        $gisLayer1->setName('TestKarte1');
        $gisLayer1->setOpacity('100');
        $gisLayer1->setOrder(0);
        $gisLayer1->setProcedureId($procedure->getId());
        $gisLayer1->setPrint(false);
        $gisLayer1->setScope(false);
        $gisLayer1->setType('base');
        $gisLayer1->setUrl('http://www.testurl.de');
        $gisLayer1->setEnabled(true);
        $gisLayer1->setGlobalLayer(false);
        $gisLayer1->setXplan(false);
        $gisLayer1->setIsMiniMap(false);
        $this->setReference(self::TESTPROCEDURE_GISLAYER, $gisLayer1);
        $this->manager->persist($gisLayer1);
    }

    private function loadTestProcedureSingleDocumentElement(Procedure $procedure): Elements
    {
        $element = new Elements();
        $element->setTitle('Title of element relatet to SingleDocument');
        $element->setText('Text of element relatet to SingleDocument');
        $element->setIcon('icon-home');
        $element->setCategory('paragraph');
        $element->setOrder(2);
        $element->setProcedure($procedure);
        $element->setEnabled(true);
        $element->setDeleted(false);
        $element->setElementParentId('f7a2863c-9457-43dc-9bef-400d09d6e9ce');
        $this->manager->persist($element);
        $this->manager->flush();
        $this->setReference(self::TESTPROCEDURE_SINGLEDOCUMENTELEMENT, $element);

        return $element;
    }

    private function loadTestProcedureFileElement(Procedure $procedure): Elements
    {
        $element = new Elements();
        $element->setTitle('Scoping-Papier');
        $element->setText('Text of element relatet to SingleDocument');
        $element->setIcon('icon-home');
        $element->setCategory('file');
        $element->setOrder(10);
        $element->setProcedure($procedure);
        $element->setEnabled(true);
        $element->setDeleted(false);
        $this->manager->persist($element);
        $this->manager->flush();
        $this->setReference('TestProcedureFileElement', $element);

        return $element;
    }

    private function loadTestProcedureParagraphProcedure(Procedure $procedure): Elements
    {
        /** @var Elements $testSingleDocumentElement */
        $testSingleDocumentElement = $this->getReference('testSingleDocumentElement');
        $paragraph1 = new Paragraph();
        $paragraph1->setElement($testSingleDocumentElement);
        $paragraph1->setCategory('begruendung');
        $paragraph1->setVisible(1);
        $paragraph1->setDeleted(false);
        $paragraph1->setProcedure($procedure);
        $paragraph1->setTitle('testParagraphProcedure');
        $paragraph1->setText('The text of the testParagraphProcedure');
        $this->manager->persist($paragraph1);
        $this->setReference(self::TESTPROCEDURE_PARAGRAPHPROCEDURE, $paragraph1);

        return $testSingleDocumentElement;
    }

    private function loadTestProcedureParagraphVersion(
        Procedure $procedure,
        Elements $testSingleDocumentElement
    ) {
        $paragraphVersion1 = new ParagraphVersion();
        $paragraphVersion1->setElement($testSingleDocumentElement);
        $paragraphVersion1->setCategory('begruendung2');
        $paragraphVersion1->setVisible(true);
        $paragraphVersion1->setDeleted(false);
        $paragraphVersion1->setProcedure($procedure);
        $paragraphVersion1->setTitle('testParagraphVersion');
        $paragraphVersion1->setText('The text of the testParagraphProcedure');
        $this->manager->persist($paragraphVersion1);
        $this->setReference(self::TESTPROCEDURE_PARAGRAPHVERSION, $paragraphVersion1);
    }

    private function loadTestProcedureLocationInformation(): array
    {
        $county5 = new County();
        $county5->setName('Kreis 5');
        $this->manager->persist($county5);

        $priorityArea5 = new PriorityArea();
        $priorityArea5->setKey('Vorrang 5');
        $priorityArea5->setType('positive');
        $this->manager->persist($priorityArea5);

        $municipality5 = new Municipality();
        $municipality5->setName('Gemeinde 5');
        $this->manager->persist($municipality5);

        return [
            'municipality' => $municipality5,
            'county'       => $county5,
            'priorityArea' => $priorityArea5,
        ];
    }

    private function loadTestProcedureStatement(Procedure $procedure, array $locationArray): void
    {
        $statement = new Statement();
        $statement->setTitle('Statement Original');
        $statement->setSubmitType('system');
        $statement->setExternId('1000');
        $statement->setText('Ich bin der Text für das Statement');
        $statement->setProcedure($procedure);
        $statement->setUser($this->testUser);
        $statement->setOrganisation($this->getReference('testOrgaInvitableInstitution'));
        $statement->setElement($this->getReference('testSingleDocumentElement'));
        $statement->setParagraph($this->getReference('testParagraphVersion'));
        $statement->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation');
        $statement->setMapFile('Chrysanthemum.jpg:fefcd2bc-51a6-46c0-96a1-fbe62c9dc64c:879394:image/pjpeg');
        $statement->setFile('toDeleteFile');
        $statement->addCounty($locationArray['county']);
        $statement->addMunicipality($locationArray['municipality']);
        $statement->addPriorityArea($locationArray['priorityArea']);
        $statement->setMeta((new StatementMeta())->setStatement($statement)->setAuthorName('Max Mustermann'));
        $statement->setPublicVerified(Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED);
        $this->setReference(self::TESTPROCEDURE_STATEMENT, $statement);
        $this->manager->persist($statement);
    }

    private function loadTestProcedureSingleDocument(Procedure $procedure, Elements $element): void
    {
        $singleDocument = new SingleDocument();
        $singleDocument->setProcedure($procedure);
        $singleDocument->setElement($element);
        $singleDocument->setCategory('informationen2');
        $singleDocument->setOrder(0);
        $singleDocument->setTitle('testFixtureDocument1');
        $singleDocument->setText('the text1');
        $singleDocument->setSymbol('');
        $singleDocument->setDocument('20131112_OSBA_Leitfaden_zur_Datensicherheit.pdf:df055eb7-5405-425b-9e21-7faa63f67777:158851:application/pdf');
        $singleDocument->setStatementEnabled(true);
        $singleDocument->setVisible(true);
        $singleDocument->setDeleted(false);
        $this->manager->persist($singleDocument);
        $this->setReference(self::TESTPROCEDURE_SINGLEDOCUMENT, $singleDocument);
    }

    private function loadTestProcedureSingleDocumentVersion(Procedure $procedure, Elements $element): void
    {
        $singleDocumentVersion = new SingleDocumentVersion();
        $singleDocumentVersion->setProcedure($procedure);
        $singleDocumentVersion->setElement($element);
        $singleDocumentVersion->setCategory('informationen2');
        $singleDocumentVersion->setOrder(0);
        $singleDocumentVersion->setTitle('testFixtureDocument1');
        $singleDocumentVersion->setText('the text1');
        $singleDocumentVersion->setSymbol('');
        $singleDocumentVersion->setDocument('Gutachten.pdf:0136deb4-5fef-4dbb-84fb-549fab3ec19e:13794:application/pdf');
        $singleDocumentVersion->setStatementEnabled(true);
        $singleDocumentVersion->setVisible(true);
        $singleDocumentVersion->setDeleted(false);
        $singleDocumentVersion->setModifyDate(new DateTime());
        $singleDocumentVersion->setCreateDate(new DateTime());
        $singleDocumentVersion->setDeleteDate(new DateTime());
        $this->manager->persist($singleDocumentVersion);
        $this->setReference(self::TESTPROCEDURE_SINGLEDOCUMENTVERSION, $singleDocumentVersion);
    }

    private function loadTestProcedureManualListSort(Procedure $procedure): void
    {
        $manualListSort = new ManualListSort();
        $manualListSort->setContext('procedure:'.$procedure->getId());
        $manualListSort->setPId($procedure->getId());
        $manualListSort->setNamespace('news');
        $manualListSort->setIdents('');
        $this->manager->persist($manualListSort);
    }

    private function loadTestProcedureReportEntry(Procedure $procedure, Customer $customer): void
    {
        $report = new ReportEntry();
        $report->setUser($this->testUser);
        $report->setIdentifier($procedure->getId());
        $report->setIdentifierType('procedure');
        $report->setGroup('procedure');
        $report->setCategory('add');
        $report->setMessage(Json::encode(['stripped' => 'content']));
        $report->setCustomer($customer);
        $this->manager->persist($report);
        $this->setReference(self::TESTPROCEDURE_REPORT, $report);
    }

    private function loadTestProcedureDraftStatement(Procedure $procedure): DraftStatement
    {
        $draftStatement = new DraftStatement();
        $draftStatement->setTitle('Draft Statement');
        $draftStatement->setText('Ich bin der Text für das Draft Statement');
        $draftStatement->setProcedure($procedure);
        $draftStatement->setUser($this->testUser);
        $draftStatement->setUName($this->testUser->getFirstname().' '.$this->testUser->getLastname());
        $draftStatement->setDepartment($this->getReference('testDepartment'));
        $draftStatement->setDName($this->getReference('testDepartment')->getName());
        $draftStatement->setOrganisation($this->getReference('testOrgaInvitableInstitution'));
        $draftStatement->setOName($this->testOrgaFP->getName());
        $draftStatement->setElement($this->getReference('testSingleDocumentElement'));
        $draftStatement->setParagraph($this->getReference('testParagraphVersion'));
        $draftStatement->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation');
        $draftStatement->setFile('Chrysanthemum.jpg:fefcd2bc-51a6-46c0-96a1-fbe62c9dc64c:879394:image/pjpeg');
        $draftStatement->setMapFile('Map_6e0f8e31-d468-465d-9087-4f0a69ec637c.png:e1883475-60cb-49c4-b0b6-11e4b5536e75');
        $this->setReference(self::TESTPROCEDURE_DRAFTSTATEMENT, $draftStatement);
        $this->manager->persist($draftStatement);

        return $draftStatement;
    }

    private function loadTestProcedureDraftStatementVersion(
        Procedure $procedure,
        DraftStatement $draftStatement
    ): void {
        $draftStatementVersion = new DraftStatementVersion();
        $draftStatementVersion->setTitle('Draft Statement');
        $draftStatementVersion->setText('Ich bin der Text für das Draft Statement');
        $draftStatementVersion->setProcedure($procedure);
        $draftStatementVersion->setUser($this->testUser);
        $draftStatementVersion->setUName($this->testUser->getFirstname().' '.$this->testUser->getLastname());
        $draftStatementVersion->setDepartment($this->getReference('testDepartment'));
        $draftStatementVersion->setDName($this->getReference('testDepartment')->getName());
        $draftStatementVersion->setOrganisation($this->getReference('testOrgaInvitableInstitution'));
        $draftStatementVersion->setOName($this->testOrgaFP->getName());
        $draftStatementVersion->setElement($this->getReference('testSingleDocumentElement'));
        $draftStatementVersion->setParagraph($this->getReference('testParagraphVersion'));
        $draftStatementVersion->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation');
        $draftStatementVersion->setFile('Chrysanthemum.jpg:fefcd2bc-51a6-46c0-96a1-fbe62c9dc64c:879394:image/pjpeg');
        $draftStatementVersion->setMapFile('Map_6e0f8e31-d468-465d-9087-4f0a69ec637c.png:e1883475-60cb-49c4-b0b6-11e4b5536e75');
        $draftStatementVersion->setCreatedDate(new DateTime());
        $draftStatementVersion->setDeletedDate(new DateTime());
        $draftStatementVersion->setRejectedDate(new DateTime());
        $draftStatementVersion->setReleasedDate(new DateTime());
        $draftStatementVersion->setSubmittedDate(new DateTime());
        $draftStatementVersion->setVersionDate(new DateTime());
        $draftStatementVersion->setLastModifiedDate(new DateTime());
        $draftStatementVersion->setDraftStatement($draftStatement);
        $this->setReference(self::TESTPROCEDURE_DRAFTSTATEMENTVERSION, $draftStatementVersion);
        $this->manager->persist($draftStatementVersion);
    }

    private function loadProcedureTestProcedureSettings(Procedure $procedure): void
    {
        $switchDateTime = Carbon::create(1999, 4, 4, 6, 30, 35);
        $publicSwitchDateTime = Carbon::create(1999, 5, 5, 12, 15, 40);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setId('fe069bbd-1bbc-464f-aecb-19aa5c7db338')
            ->setProcedure($procedure)
            ->setMapExtent('576805.21,5949330.84,577927.05,5950158.99')
            ->setBoundingBox('574056.93,5947084.05,581909.78,5952881.08')
            ->setInformationUrl('http://xplan-sax-kom.sakd.de/xplan-wms/services?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetFeatureInfo&layers=bp_plan,bp_baugeb,bp_baugebteilfl,bp_gembedarfsfl,bp_gruenfl,bp_laermschutzber,bp_schutzpflentwfl,bp_strverksfl,bp_verentsorgung,bp_verentsorgungsfl,bp_wegerecht,&query_layers=bp_plan,bp_baugeb,bp_baugebteilfl,bp_gembedarfsfl,bp_gruenfl,bp_laermschutzber,bp_schutzpflentwfl,bp_strverksfl,bp_verentsorgung,bp_verentsorgungsfl,bp_wegerecht,&SRS=EPSG:25832&FORMAT=image/png&INFO_FORMAT=text/html&STYLES=&FEATURE_COUNT=100')
            ->setTerritory('POINT(577175.61335492 5949591.1563017),POINT(577089.62359961 5949685.0835729),POINT(577130.6340983 5949696.9898467),POINT(577143.86329142 5949878.2297925),POINT(577194.1342253 5949943.0528388),POINT(577290.70733511 5949982.7404182),POINT(577471.94728092 5950022.4279976),POINT(577538.09324655 5949779.0108441),POINT(577538.09324655 5949640.1043163),POINT(577215.3009343 5949645.3959935),POINT(577180.90503217 5949589.8333824)')
            ->setCoordinate('577380.68163195,5949764.0961163')
            ->setPlanText('08.07.2015')
            ->setPlanPDF('Legende.pdf:096600cc-6f32-45a7-b01d-83bf96941c4e:119994:application/pdf')
            ->setPlanPara1PDF('Begruendung.pdf:4b591ee0-924f-46b1-b4ea-5a9df58f7da5:80204:application/pdf')
            ->setPlanPara2PDF('legende_2015.pdf:3dd47341-9dd1-4eb5-88b5-fe8df7056fb8:481309:application/x-download')
            ->setPlanDrawText('<p>Planzeichenerkl&aumlrung')
            ->setPlanDrawPDF('Planzeichnung.pdf:f1fc9608-db14-430b-91d2-df4686bceb2a:228978:application/pdf')
            ->setEmailTitle('Einladung zur Beteiligung  Amt "Nordwest": Ein neues Testverfahren 1')
            ->setEmailText('Hallo Hallo! Was geht?')
            ->setEmailCc('ich@ich.de')
            ->setDesignatedPublicPhase($this->existingInternalPhasesWrite[0] ?? 'configuration')
            ->setDesignatedPhase('configuration')

            ->setDesignatedSwitchDate($switchDateTime->toDateTime())
            ->setDesignatedPublicSwitchDate($publicSwitchDateTime->toDateTime())
            ->setDesignatedEndDate($switchDateTime->addHours(3)->addMinutes(30)->toDateTime())
            ->setDesignatedPublicEndDate($publicSwitchDateTime->addHours(5)->addMinutes(15)->toDateTime())

            ->setPlanningArea('I');
        $procedureSettings->setMapHint('This is a procedure specific map hint which might have been edited by a planer.');
        $this->manager->persist($procedureSettings);
    }

    private function createBlueprintProcedure(): void
    {
        $masterProcedureWithBoilerplates = new Procedure();
        $masterProcedureWithBoilerplates
            ->setName('MasterProcedureWithBoilerplates')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setDesc('testproceduremaster')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'configuration')
            ->setPublicParticipationPhase('configuration')
            ->setMaster(false)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($masterProcedureWithBoilerplates))
            ->addSlug(new Slug('procedureMasterProcedureBoilerplateSlug'));
        $masterProcedureWithBoilerplates->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));
        $this->manager->persist($masterProcedureWithBoilerplates);
        $this->setReference('testmasterProcedureWithBoilerplates', $masterProcedureWithBoilerplates);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($masterProcedureWithBoilerplates)
            ->setMapExtent('441997.41,5923055.13,611330.65,6089742.54')
            ->setBoundingBox('441997.41,5923055.13,611330.65,6089742.54')
            ->setPlanningArea('');
        $this->manager->persist($procedureSettings);

        $gisLayerCategory = new GisLayerCategory();
        $gisLayerCategory->setProcedure($masterProcedureWithBoilerplates);
        $gisLayerCategory->setName('name');

        $this->manager->persist($gisLayerCategory);
    }

    private function createProcedureToDelete(): void
    {
        $procedureToDelete = new Procedure();
        $procedureToDelete->setName('TestprocedureToDelete')
            ->setShortUrl('Lokstedt62')
            ->setStartDate($this->currentDate)
            ->setEndDate($this->currentDate)
            ->setOrga($this->testOrgaFP)
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->setAgencyMainEmailAddress('agencyMainEmailAddress@example.org')
            ->setOrgaName('testOrgaFP')
            ->setDesc('testDesc')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'configuration')
            ->setStep('test')
            ->setLogo('test')
            ->setExternId('test')
            ->setPlisId('test')
            ->setClosed(false)
            ->setDeleted(false)
            ->setMaster(false)
            ->setExternalName('testExternalName')
            ->setExternalDesc('testExternalDesc')
            ->setPublicParticipation(false)
            ->setPublicParticipationStep('asdfasdf')
            ->setMunicipalCode('12345')
            ->setPublicParticipationPhase('configuration')
            ->setPublicParticipationStartDate($this->currentDate)
            ->setPublicParticipationEndDate($this->currentDate)
            ->setCreatedDate($this->currentDate)
            ->setPublicParticipationContact('testPPContact')
            ->setLocationName('testLocationName')
            ->setLocationPostCode('12345')
            ->setClosedDate($this->currentDate)
            ->setDeletedDate($this->currentDate)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedureToDelete))
            ->addSlug(new Slug('procedureToDeleteSlug'));
        $procedureToDelete->setProcedureType($this->getReference(LoadProcedureTypeData::BPLAN));
        $this->manager->persist($procedureToDelete);

        $this->setReference('procedureToDelete', $procedureToDelete);

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedureToDelete)
            ->setPlanningArea('');
        $this->manager->persist($procedureSettings);
    }

    public function getDependencies()
    {
        return [
            LoadProcedureBehaviorDefinitionData::class,
            LoadProcedureTypeData::class,
            LoadProcedureUiDefinitionData::class,
            LoadStatementFormDefinitionData::class,
            LoadUserData::class,
        ];
    }

    private function createProcedureInPublicConsultationPhase(): void
    {
        $procedure = new Procedure();
        $procedure
            ->setName('Procedure in public consultation phase')
            ->setOrga($this->testOrgaFP)
            ->setOrgaName('testOrgaFP')
            ->setOrganisation([$this->testOrgaFP])
            ->setPlanningOffices([$this->getReference('testOrgaPB')])
            ->setAgencyMainEmailAddress('a@a.com')
            ->setPhase($this->existingInternalPhasesWrite[0] ?? 'participation')
            ->setPublicParticipationPhase('participation')
            ->setMaster(false)
            ->addExportFieldsConfiguration(new ExportFieldsConfiguration($procedure))
            ->addSlug(new Slug(self::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE));

        $procedureSettings = new ProcedureSettings();
        $procedureSettings
            ->setProcedure($procedure)
            ->setPlanningArea('');
        $procedure->setSettings($procedureSettings);
        $this->manager->persist($procedureSettings);

        $this->manager->persist($procedure);
        $this->setReference(self::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE, $procedure);
    }

    protected function loadMasterBluePrintFileElements(
        ObjectManager $manager,
        Procedure $masterBlueprint
    ): void {
        $elementsToCreate = [
            ElementsInterface::ELEMENT_TITLES['fnp_aenderung'],
            ElementsInterface::ELEMENT_TITLES['lapro_aenderung'],
            ElementsInterface::ELEMENT_TITLES['ergaenzende_unterlage'],
            ElementsInterface::ELEMENT_TITLES['arbeitskreispapier'],
            ElementsInterface::ELEMENT_TITLES['verteiler'],
            ElementsInterface::ELEMENT_TITLES['niederschrift_sonstige'],
            ElementsInterface::ELEMENT_TITLES['scoping_papier'],
            ElementsInterface::ELEMENT_TITLES['gutachten'],
            ElementsInterface::ELEMENT_TITLES['arbeitskreispapier_i'],
            ElementsInterface::ELEMENT_TITLES['arbeitskreispapier_ii'],
            ElementsInterface::ELEMENT_TITLES['niederschrift_grobabstimmung_arbeitskreise'],
            ElementsInterface::ELEMENT_TITLES['grobabstimmungspapier'],
            ElementsInterface::ELEMENT_TITLES['scoping_protokoll'],
        ];

        foreach ($elementsToCreate as $key => $elementTitle) {
            $element = new Elements();
            $element->setProcedure($masterBlueprint);
            $element->setCategory('file');
            $element->setOrder($key);
            $element->setEnabled(1);
            $element->setTitle($elementTitle);
            $manager->persist($element);

            $this->loadTestProcedureSingleDocument($masterBlueprint, $element);
            $this->loadTestProcedureSingleDocumentVersion($masterBlueprint, $element);

            $manager->persist($element);
            $this->setReference("masterBlueprintElement-$key", $element);
        }
    }

    protected function loadMasterBluePrintParagraphElements(
        ObjectManager $manager,
        Procedure $masterBlueprint
    ): void {
        $elementsToCreate = [
            ElementsInterface::ELEMENT_TITLES['verordnung'],
            ElementsInterface::ELEMENT_TITLES['begruendung'],
        ];
        foreach ($elementsToCreate as $key => $elementTitle) {
            $element = new Elements();
            $element->setProcedure($masterBlueprint);
            $element->setCategory(ElementsInterface::ELEMENT_CATEGORIES['paragraph']);
            $element->setOrder($key);
            $element->setEnabled(1);
            $element->setTitle($elementTitle);
            $manager->persist($element);

            $this->loadTestProcedureSingleDocument($masterBlueprint, $element);
            $this->loadTestProcedureSingleDocumentVersion($masterBlueprint, $element);

            $manager->persist($element);
            $this->setReference("masterBlueprintElement-paragraph$key", $element);
        }
    }
}
