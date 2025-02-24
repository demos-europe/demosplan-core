<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PreparationMailVO;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureReportEntryFactory extends AbstractReportEntryFactory
{
    public function __construct(
        CurrentUserInterface $currentUserProvider,
        CustomerService $currentCustomerProvider,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($currentUserProvider, $currentCustomerProvider);
    }

    public function createRegisterInvitationEntry(
        array $recipients,
        array $ccAddresses,
        string $procedureId,
        string $phase,
        string $mailSubject,
    ): ReportEntry {
        $data = [
            'recipients'  => $recipients,
            'ccAddresses' => $ccAddresses,
            'phase'       => $phase,
            'ident'       => $procedureId,
            'mailSubject' => $mailSubject,
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_REGISTER_INVITATION);
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifier($procedureId);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createInvitationEntry(
        array $recipientsWithEmail,
        string $procedureId,
        string $phase,
        string $mailSubject,
    ): ReportEntry {
        $data = [
            'recipients'  => $recipientsWithEmail,
            'phase'       => $phase,
            'ident'       => $procedureId,
            'mailSubject' => $mailSubject,
        ];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_INVITATION);
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifier($procedureId);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createProcedureCreationEntry(
        array $data,
        Procedure $procedure,
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifier($procedure->getIdent());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setCategory(ReportEntry::CATEGORY_ADD);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createPublicParticipationUpdateEntry(Procedure $procedure): ReportEntry
    {
        $data = [
            'newStartDate' => $procedure->getPublicParticipationStartDateTimestamp(),
            'newEndDate'   => $procedure->getPublicParticipationEndDateTimestamp(),
        ];

        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifier($procedure->getIdent());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setCreateDate(Carbon::now()->addSecond()->toDateTime());
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    /**
     * @param User|non-empty-string $user if no user instance is available, the username must be passed
     *
     * @throws JsonException
     */
    public function createPhaseChangeEntry(
        Procedure $procedure,
        array $data,
        User|string $user,
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_CHANGE_PHASES);
        if ($user instanceof User) {
            $entry->setUser($user);
        } else {
            $entry->setUserId('');
            $entry->setUserName($user);
        }
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedure->getId());
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    /**
     * @throws JsonException
     */
    public function createTargetProcedureCoupleEntry(
        string $procedureIdToCreateTheReportEntryFor,
        Procedure $coupledProcedure,
        User $user,
    ): ReportEntry {
        $messageData = [];
        $messageData['sourceProcedure'] = $coupledProcedure->getName();

        return $this->createProcedureCoupleEntry($procedureIdToCreateTheReportEntryFor, $coupledProcedure, $user, $messageData);
    }

    /**
     * @throws JsonException
     */
    public function createSourceProcedureCoupleEntry(
        string $procedureIdToCreateTheReportEntryFor,
        Procedure $coupledProcedure,
    ): ReportEntry {
        $messageData = [];
        $messageData['targetProcedure'] = $coupledProcedure->getName();

        return $this->createProcedureCoupleEntry($procedureIdToCreateTheReportEntryFor, $coupledProcedure, null, $messageData);
    }

    /**
     * @throws JsonException
     */
    private function createProcedureCoupleEntry(
        string $procedureIdToCreateTheReportEntryFor,
        Procedure $coupledProcedure,
        ?User $user,
        array $messageData,
    ): ReportEntry {
        $messageData['relatedInstitutionName'] = $coupledProcedure->getOrgaName();

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureIdToCreateTheReportEntryFor);
        $entry->setMessage(Json::encode($messageData, JSON_UNESCAPED_UNICODE));
        $entry->setUser($user);
        if (null === $user) {
            $entry->setUserName($this->translator->trans('anonymized'));
        }

        return $entry;
    }

    public function createUpdateEntry(
        Procedure $procedure,
        array $data,
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedure->getId());
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createMapExtendUpdateEntry(string $procedureId, $mapExtent): ReportEntry
    {
        $data = ['mapExtent' => $mapExtent];

        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($this->getCurrentUser());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createDescriptionUpdateEntry(
        string $procedureId,
        $externalDescription,
        User $user,
    ): ReportEntry {
        $message = [
            'ident'        => $procedureId,
            'group'        => ReportEntry::GROUP_PROCEDURE,
            'category'     => ReportEntry::CATEGORY_UPDATE,
            'externalDesc' => $externalDescription,
        ];

        $entry = $this->createReportEntry();
        $entry->setIdentifier($procedureId);
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($user);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createFinalMailEntry(
        string $procedureId,
        User $fromUser,
        PreparationMailVO $preparationMail,
        $statementMailAddresses,
    ): ReportEntry {
        $message = [
            'procedureId'   => $procedureId,
            'ident'         => $procedureId,
            'receiverCount' => is_countable($statementMailAddresses) ? count($statementMailAddresses) : 0,
            'mailSubject'   => $preparationMail->getMailSubject(),
            'mailBody'      => $preparationMail->getMailBody(),
        ];

        $entry = $this->createReportEntry();
        $entry->setIdentifier($procedureId);
        $entry->setCategory(ReportEntry::CATEGORY_FINAL_MAIL);
        $entry->setUser($fromUser);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PROCEDURE);

        return $reportEntry;
    }

    private function getCurrentUser(): User
    {
        try {
            $currentUser = $this->currentUserProvider->getUser();
        } catch (UserNotFoundException) {
            $currentUser = new AnonymousUser();
        }

        return $currentUser;
    }
}
