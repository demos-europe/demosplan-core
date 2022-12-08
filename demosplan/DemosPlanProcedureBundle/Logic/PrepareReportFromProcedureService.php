<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use ReflectionException;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanDocumentBundle\Logic\ElementsService;
use demosplan\DemosPlanDocumentBundle\Logic\ParagraphService;
use demosplan\DemosPlanMapBundle\Logic\MapService;
use demosplan\DemosPlanReportBundle\Logic\ProcedureReportEntryFactory;
use demosplan\DemosPlanReportBundle\Logic\ReportService;
use demosplan\DemosPlanReportBundle\Logic\StatementReportEntryFactory;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;

class PrepareReportFromProcedureService extends CoreService
{
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var ElementsService
     */
    private $elementsService;

    /**
     * @var MapService
     */
    private $mapService;

    /**
     * @var ParagraphService
     */
    private $paragraphDocumentService;

    /**
     * @var ReportService
     */
    private $reportService;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var ProcedureReportEntryFactory
     */
    private $procedureReportEntryFactory;

    /**
     * @var StatementReportEntryFactory
     */
    private $statementReportEntryFactory;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        CurrentUserInterface $currentUser,
        ElementsService $elementsService,
        GlobalConfigInterface $globalConfig,
        MapService $mapService,
        ParagraphService $paragraphDocumentService,
        PermissionsInterface $permissions,
        ReportService $reportService,
        ProcedureReportEntryFactory $procedureReportEntryFactory,
        StatementReportEntryFactory $statementReportEntryFactory
    ) {
        $this->currentUser = $currentUser;
        $this->elementsService = $elementsService;
        $this->globalConfig = $globalConfig;
        $this->mapService = $mapService;
        $this->paragraphDocumentService = $paragraphDocumentService;
        $this->permissions = $permissions;
        $this->procedureReportEntryFactory = $procedureReportEntryFactory;
        $this->reportService = $reportService;
        $this->statementReportEntryFactory = $statementReportEntryFactory;
    }

    /**
     * Add report entry to ensure send mail to unregistered public agency will be logged.
     *
     * @param array $recipients array of mailaddresses
     *
     * @throws Exception
     */
    public function addReportEmailToAddresses(
        array $recipients,
        array $ccAddresses,
        string $procedureId,
        string $phase,
        string $mailSubject
    ): void {
        $reportEntry = $this->procedureReportEntryFactory->createRegisterInvitationEntry(
            $recipients,
            $ccAddresses,
            $procedureId,
            $phase,
            $mailSubject
        );
        $this->reportService->persistAndFlushReportEntries($reportEntry);
    }

    /**
     * Add a report about a sent final_mail.
     *
     * @param string[] $emailAttachmentNames
     *
     * @throws UserNotFoundException
     */
    public function addReportFinalMail(Statement $statement, string $mailSubject, array $emailAttachmentNames): void
    {
        try {
            $reportEntry = $this->statementReportEntryFactory->createFinalMailEntry(
                $statement,
                $mailSubject,
                $emailAttachmentNames
            );
            $this->reportService->persistAndFlushReportEntries($reportEntry);
        } catch (Exception $e) {
            $this->getLogger()->error('Could not add report to protocol: ', [$e]);
        }
    }

    /**
     * Add a report about a sent invitation by means of the DemosPlanReportBundle.
     */
    public function addReportInvite(array $recipientsWithEmail, string $procedureId, string $phase, string $mailSubject): void
    {
        try {
            $reportEntry = $this->procedureReportEntryFactory->createInvitationEntry(
                $recipientsWithEmail,
                $procedureId,
                $phase,
                $mailSubject
            );
            $this->reportService->persistAndFlushReportEntries($reportEntry);
        } catch (Exception $e) {
            $this->logger->error('Could not add report for finalMail to protocol: ', [$e]);
        }
    }

    /**
     * Adds a Report by means of the DemosPlanReportService.
     *
     * @see https://yaits.demos-deutschland.de/w/demosplan/functions/verfahrensprotokoll/ Verfahrensprotokoll
     *
     * @throws CustomerNotFoundException
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ReflectionException
     * @throws UserNotFoundException
     */
    public function addReportOnProcedureCreate(array $procedureArray, Procedure $procedure): void
    {
        // initial report for new procedure
        $creationEntry = $this->procedureReportEntryFactory->createProcedureCreationEntry(
            $procedureArray,
            $procedure
        );
        $this->reportService->persistAndFlushReportEntries($creationEntry);

        // T16674: add publicParticipationStartDate entry
        $updateEntry = $this->procedureReportEntryFactory->createPublicParticipationUpdateEntry(
            $procedure
        );
        $this->reportService->persistAndFlushReportEntries($updateEntry);
    }

    /**
     * Create a Report by means of the DemosPlanReportBundle.
     *
     * @throws Exception
     */
    public function createReportEntry(Procedure $sourceProcedure, Procedure $destinationProcedure)
    {
        $sourceProcedureSettings = $sourceProcedure->getSettings();
        $destinationProcedureSettings = $destinationProcedure->getSettings();
        $update = [];

        $sourceDateOfSwitchPublicPhase = $sourceProcedure->getSettings()->getDesignatedPublicSwitchDate();
        $destinationDateOfSwitchPublicPhase = $destinationProcedure->getSettings()->getDesignatedPublicSwitchDate();
        if (!$this->equalDates($sourceDateOfSwitchPublicPhase, $destinationDateOfSwitchPublicPhase)) {
            $update['oldDesignatedCitizenSwitchDate'] = $this->getTimestamp($sourceDateOfSwitchPublicPhase);
            $update['newDesignatedCitizenSwitchDate'] = $this->getTimestamp($destinationDateOfSwitchPublicPhase);
        }

        $sourceDateOfSwitchPhase = $sourceProcedure->getSettings()->getDesignatedSwitchDate();
        $destinationDateOfSwitchPhase = $destinationProcedure->getSettings()->getDesignatedSwitchDate();
        if (!$this->equalDates($sourceDateOfSwitchPhase, $destinationDateOfSwitchPhase)) {
            $update['oldDesignatedAgencySwitchDate'] = $this->getTimestamp($sourceDateOfSwitchPhase);
            $update['newDesignatedAgencySwitchDate'] = $this->getTimestamp($destinationDateOfSwitchPhase);
        }

        $sourceEndDateOfSwitchPhase = $sourceProcedureSettings->getDesignatedEndDate();
        $destinationEndDateOfSwitchPhase = $destinationProcedureSettings->getDesignatedEndDate();
        if (!$this->equalDates($sourceEndDateOfSwitchPhase, $destinationEndDateOfSwitchPhase)) {
            $update['oldDesignatedAgencySwitchEndDate'] = $this->getTimestamp($sourceEndDateOfSwitchPhase);
            $update['newDesignatedAgencySwitchEndDate'] = $this->getTimestamp($destinationEndDateOfSwitchPhase);
        }

        $sourceEndDateOfPublicSwitchPhase = $sourceProcedureSettings->getDesignatedPublicEndDate();
        $destinationEndDateOfPublicSwitchPhase = $destinationProcedureSettings->getDesignatedPublicEndDate();
        if (!$this->equalDates($sourceEndDateOfPublicSwitchPhase, $destinationEndDateOfPublicSwitchPhase)) {
            $update['oldDesignatedCitizenSwitchEndDate'] = $this->getTimestamp($sourceEndDateOfPublicSwitchPhase);
            $update['newDesignatedCitizenSwitchEndDate'] = $this->getTimestamp($destinationEndDateOfPublicSwitchPhase);
        }

        if (0 !== strcmp($sourceProcedure->getSettings()->getDesignatedPublicPhase(), $destinationProcedure->getSettings()->getDesignatedPublicPhase())) {
            $update['oldDesignatedCitizenPhase'] = $sourceProcedure->getSettings()->getDesignatedPublicPhase();
            $update['newDesignatedCitizenPhase'] = $destinationProcedure->getSettings()->getDesignatedPublicPhase();
        }

        if (0 !== strcmp($sourceProcedure->getSettings()->getDesignatedPhase(), $destinationProcedure->getSettings()->getDesignatedPhase())) {
            $update['oldDesignatedAgencyPhase'] = $sourceProcedure->getSettings()->getDesignatedPhase();
            $update['newDesignatedAgencyPhase'] = $destinationProcedure->getSettings()->getDesignatedPhase();
        }

        if (0 !== strcmp($sourceProcedure->getName(), $destinationProcedure->getName())) {
            $update['oldName'] = $sourceProcedure->getName();
            $update['newName'] = $destinationProcedure->getName();
        }

        if (0 !== strcmp($sourceProcedure->getExternalName(), $destinationProcedure->getExternalName())) {
            $update['oldPublicName'] = $sourceProcedure->getExternalName();
            $update['newPublicName'] = $destinationProcedure->getExternalName();
        }

        if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
            // compare Users using custom function instead of just casting a User instance to a string
            $dstProcedureAuthorizedUsersArray = $destinationProcedure->getAuthorizedUsers()->toArray();
            $srcProcedureAuthorizedUsersArray = $sourceProcedure->getAuthorizedUsers()->toArray();
            $changes = array_udiff($dstProcedureAuthorizedUsersArray, $srcProcedureAuthorizedUsersArray, function (User $user1, User $user2) {
                return strcmp($user1->getId(), $user2->getId());
            });
            if (0 !== count($changes)) {
                $update['oldAuthorizedUsers'] = implode(', ', $sourceProcedure->getAuthorizedUserNames());
                $update['newAuthorizedUsers'] = implode(', ', $destinationProcedure->getAuthorizedUserNames());
            }
        }

        if ($this->hasPublicAgencyParticipationDateChanged($sourceProcedure, $destinationProcedure)) {
            $update['oldStartDate'] = $sourceProcedure->getStartDate()->getTimestamp();
            $update['newStartDate'] = $destinationProcedure->getStartDate()->getTimestamp();
            $update['oldEndDate'] = $sourceProcedure->getEndDate()->getTimestamp();
            $update['newEndDate'] = $destinationProcedure->getEndDate()->getTimestamp();
        }

        if ($this->hasPublicParticipationDateChanged($sourceProcedure, $destinationProcedure)) {
            $update['oldPublicStartDate'] = $sourceProcedure->getPublicParticipationStartDate()->getTimestamp();
            $update['newPublicStartDate'] = $destinationProcedure->getPublicParticipationStartDate()->getTimestamp();
            $update['oldPublicEndDate'] = $sourceProcedure->getPublicParticipationEndDate()->getTimestamp();
            $update['newPublicEndDate'] = $destinationProcedure->getPublicParticipationEndDate()->getTimestamp();
        }

        $user = null;
        try {
            $user = $this->currentUser->getUser();
        } catch (UserNotFoundException $e) {
            $this->logger->info('No user found for log creation');
        }
        $phaseChangeEntry = $this->createPhaseChangeReportEntryIfChangesOccurred(
            $sourceProcedure,
            $destinationProcedure,
            $user,
            false
        );
        if (null !== $phaseChangeEntry) {
            $this->reportService->persistAndFlushReportEntries($phaseChangeEntry);
        }

        if (0 !== count($update)) {
            $updateReportEntry = $this->procedureReportEntryFactory->createUpdateEntry(
                $sourceProcedure,
                $update
            );
            $this->reportService->persistAndFlushReportEntries($updateReportEntry);
        }
    }

    /**
     * @param User|null $user avoid passing `null`, as we want to show a user when the report is listed in the UI
     */
    public function createPhaseChangeReportEntryIfChangesOccurred(
        Procedure $sourceProcedure,
        Procedure $destinationProcedure,
        ?User $user,
        bool $createdBySystem
    ): ?ReportEntry {
        if ($this->hasPhaseChanged($sourceProcedure, $destinationProcedure)) {
            $phase = $this->createPhaseChangeMessageData($sourceProcedure, $destinationProcedure);

            if (0 !== count($phase)) {
                $phase['createdBySystem'] = $createdBySystem;

                return $this->procedureReportEntryFactory->createPhaseChangeEntry(
                    $sourceProcedure,
                    $phase,
                    $user
                );
            }
        }

        return null;
    }

    /**
     * Coupling procedures will be executed from the targetProcedure.
     * That means that on call of this method the current procedure is the targetProcedure.
     * Therefor the report entry for the current (target)Procedure is allowed to contain
     * personal data of the current user, while the report entry for the related (source)Procedure,
     * is a kind of "foreign" procedure and therefor is not allowed to contain personal data of the current user.
     *
     * @throws JsonException
     * @throws ProcedureNotFoundException
     */
    public function addReportsOnProcedureCouple(ProcedureCoupleToken $token, User $user): void
    {
        if (null === $token->getTargetProcedure()) {
            throw new ProcedureNotFoundException('Target procedure must be set to generate report entries on procedure coupling');
        }

        // Report-entry for targetProcedure
        $entry = $this->procedureReportEntryFactory->createTargetProcedureCoupleEntry(
            $token->getTargetProcedure()->getId(),
            $token->getSourceProcedure(),
            $user
        );
        $this->reportService->persistAndFlushReportEntry($entry);

        // Report-entry for sourceProcedure (hearing authority)
        $entry = $this->procedureReportEntryFactory->createSourceProcedureCoupleEntry(
            $token->getSourceProcedure()->getId(),
            $token->getTargetProcedure()
        );
        $this->reportService->persistAndFlushReportEntry($entry);
    }

    private function createPhaseChangeMessageData(Procedure $sourceProcedure, Procedure $destinationProcedure): array
    {
        $procedureId = $sourceProcedure->getId();

        $phase = [];

        $phaseBefore = $sourceProcedure->getPhase();
        $phaseAfter = $destinationProcedure->getPhase();
        if ($phaseBefore !== $phaseAfter) {
            $phase['oldPhase'] = $phaseBefore;
            $phase['newPhase'] = $phaseAfter;
            $phase['oldPhaseStart'] = $sourceProcedure->getStartDate()->getTimestamp();
            $phase['newPhaseStart'] = $destinationProcedure->getStartDate()->getTimestamp();
            $phase['oldPhaseEnd'] = $sourceProcedure->getEndDate()->getTimestamp();
            $phase['newPhaseEnd'] = $destinationProcedure->getEndDate()->getTimestamp();
        }

        $publicParticipationPhaseBefore = $sourceProcedure->getPublicParticipationPhase();
        $publicParticipationPhaseAfter = $destinationProcedure->getPublicParticipationPhase();
        if ($publicParticipationPhaseBefore !== $publicParticipationPhaseAfter) {
            $phase['oldPublicPhase'] = $publicParticipationPhaseBefore;
            $phase['newPublicPhase'] = $publicParticipationPhaseAfter;
            $phase['oldPublicPhaseStart'] = $sourceProcedure->getPublicParticipationStartDate()->getTimestamp();
            $phase['newPublicPhaseStart'] = $destinationProcedure->getPublicParticipationStartDate()->getTimestamp();
            $phase['oldPublicPhaseEnd'] = $sourceProcedure->getPublicParticipationEndDate()->getTimestamp();
            $phase['newPublicPhaseEnd'] = $destinationProcedure->getPublicParticipationEndDate()->getTimestamp();
        }

        $planText = $destinationProcedure->getSettings()->getPlanText();
        if ('' !== $planText) {
            $phase['planText'] = $planText;
        }
        $planPdf = $destinationProcedure->getSettings()->getPlanPDF();
        if ('' !== $planPdf) {
            $phase['planPDF'] = $planPdf;
        }
        $planDrawPdf = $destinationProcedure->getSettings()->getPlanDrawPDF();
        if ('' !== $planDrawPdf) {
            $phase['planDrawPDF'] = $planDrawPdf;
        }
        $planPara1Pdf = $destinationProcedure->getSettings()->getPlanPara1PDF();
        if ('' !== $planPara1Pdf) {
            $phase['begruendungPDF'] = $planPara1Pdf;
        }
        $planPara2Pdf = $destinationProcedure->getSettings()->getPlanPara2PDF();
        if ('' !== $planPara2Pdf) {
            $phase['verordnungPDF'] = $planPara2Pdf;
        }

        $gisList = $this->mapService->getGisList($procedureId, null);
        foreach ($gisList as $gis) {
            if ($gis['bplan'] && !$gis['deleted']) {
                $phase['planGisName'] = $gis['name'];
                $phase['planGisVisible'] = $gis['visible'];
            }
        }

        $elementsList = $this->elementsService->getElementsListObjects($procedureId, $sourceProcedure->getOrgaId());
        $elements = [];
        $paragraphs = [];

        foreach ($elementsList as $element) {
            switch ($element->getCategory()) {
                case Elements::ELEMENTS_CATEGORY_PARAGRAPH:
                    $paragraphs = $this->addParagraphReportToMessage($element, $paragraphs);
                    break;
                case Elements::ELEMENTS_CATEGORY_FILE:
                    $elements = $this->addFileReportToMessage($element, $elements);
                    break;
                default:
                    break;
            }
        }

        if (0 !== count($elements)) {
            $phase['elements'] = $elements;
        }

        if (0 !== count($paragraphs)) {
            $phase['paragraphs'] = $paragraphs;
        }

        return $phase;
    }

    private function hasPhaseChanged(Procedure $sourceProcedure, Procedure $destinationProcedure): bool
    {
        return 0 !== strcmp($sourceProcedure->getPhase(), $destinationProcedure->getPhase()) ||
            0 !== strcmp($sourceProcedure->getPublicParticipationPhase(), $destinationProcedure->getPublicParticipationPhase());
    }

    /**
     * Compares the public participation date on accuracy of a day.
     * More accurate comparison would lead to detection of changes of hours, caused by set a date+time
     * for automatically switch phase of a procedure.
     */
    private function hasPublicParticipationDateChanged(
        Procedure $sourceProcedure,
        Procedure $destinationProcedure
    ): bool {
        $currentStartDate = new Carbon($sourceProcedure->getPublicParticipationStartDate());
        $newStartDate = new Carbon($destinationProcedure->getPublicParticipationStartDate());
        $currentEndDate = new Carbon($sourceProcedure->getPublicParticipationEndDate());
        $newEndDate = new Carbon($destinationProcedure->getPublicParticipationEndDate());

        return !$currentStartDate->isSameDay($newStartDate) || !$currentEndDate->isSameDay($newEndDate);
    }

    /**
     * Compares the public participation date on accuracy of a day.
     * More accurate comparison would lead to detection of changes of hours, caused by set a date+time
     * for automatically switch phase of a procedure.
     */
    private function hasPublicAgencyParticipationDateChanged(
        Procedure $sourceProcedure,
        Procedure $destinationProcedure
    ): bool {
        $currentStartDate = new Carbon($sourceProcedure->getStartDate());
        $newStartDate = new Carbon($destinationProcedure->getStartDate());
        $currentEndDate = new Carbon($sourceProcedure->getEndDate());
        $newEndDate = new Carbon($destinationProcedure->getEndDate());

        return !$currentStartDate->isSameDay($newStartDate) || !$currentEndDate->isSameDay($newEndDate);
    }

    private function getTimestamp(?DateTime $dateTime): ?int
    {
        if (null === $dateTime) {
            return null;
        }

        return $dateTime->getTimestamp();
    }

    /**
     * Add published paragraph category to report message.
     *
     * @param array[] $elements
     *
     * @return array[]
     *
     * @throws ReflectionException
     */
    private function addParagraphReportToMessage(Elements $element, array $elements): array
    {
        // element should be visible and not (soft)deleted
        if (!$element->getEnabled() || $element->getDeleted()) {
            return $elements;
        }

        $elementEntry = [];
        // category has uploaded pdf file
        if ('' !== $element->getFile()) {
            $elementEntry['hasParagraphPdf'] = true;
        }

        $paragraphList = $this->paragraphDocumentService->getParaDocumentObjectList(
            $element->getPId(),
            $element->getId()
        );
        foreach ($paragraphList as $paraDoc) {
            if (!$paraDoc->getDeleted() && $paraDoc->getVisible()) {
                $elementEntry['hasParagraphs'] = true;
                break;
            }
        }

        // add entry to report data
        if (0 < count($elementEntry)) {
            $elements[$element->getTitle()] = $elementEntry;
        }

        return $elements;
    }

    /**
     * Add published files category to report message.
     *
     * @param array[] $elements
     *
     * @return array[]
     */
    private function addFileReportToMessage(Elements $element, array $elements): array
    {
        // element should be visible and not (soft)deleted
        if (!$element->getEnabled() || $element->getDeleted() || $element->getDocuments()->isEmpty()) {
            return $elements;
        }

        $elementEntry = [];
        $access = [];
        $documents = [];

        foreach ($element->getDocuments() as $document) {
            if ($document->getVisible() && !$document->getDeleted()) {
                $documents[$document->getTitle()] = $document->getDocument();
            }
        }
        if (0 !== count($documents)) {
            $elementEntry['files'] = $documents;
        }

        if (!$element->getOrganisations()->isEmpty()) {
            foreach ($element->getOrganisations() as $orga) {
                $access[] = $orga->getName();
            }

            if (0 !== count($access)) {
                $elementEntry['access'] = $access;
            }
        }

        // add entry to report data
        if (0 < count($elementEntry)) {
            $elements[$element->getTitle()] = $elementEntry;
        }

        return $elements;
    }

    /**
     * Determines whether the two given dates are equal.
     *
     * @param DateTime $sourceDateOfSwitchPublicPhase
     * @param DateTime $destinationDateOfSwitchPublicPhase
     *
     * @return bool
     */
    private function equalDates($sourceDateOfSwitchPublicPhase, $destinationDateOfSwitchPublicPhase)
    {
        $sourceDateOfSwitchPublicPhaseTimestamp = null;
        if (null != $sourceDateOfSwitchPublicPhase) {
            $sourceDateOfSwitchPublicPhaseTimestamp = $sourceDateOfSwitchPublicPhase->getTimestamp();
        }

        $destinationDateOfSwitchPublicPhaseTimestamp = null;
        if (null != $destinationDateOfSwitchPublicPhase) {
            $destinationDateOfSwitchPublicPhaseTimestamp = $destinationDateOfSwitchPublicPhase->getTimestamp();
        }

        return $sourceDateOfSwitchPublicPhaseTimestamp == $destinationDateOfSwitchPublicPhaseTimestamp;
    }
}
