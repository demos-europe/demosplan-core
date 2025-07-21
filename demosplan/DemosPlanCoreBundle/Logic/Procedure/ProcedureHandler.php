<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Handler\ProcedureHandlerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\NoRecipientsWithEmailException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\PublicAffairsAgentNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\PublicAffairsAgentHandler;
use demosplan\DemosPlanCoreBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryProcedure;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Sort;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\InvitationEmailResult;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Support\Collection;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

use function array_key_exists;

class ProcedureHandler extends CoreHandler implements ProcedureHandlerInterface
{
    /**
     * @var ServiceStorage
     */
    protected $serviceStorage;

    /**
     * @var ServiceOutput
     */
    protected $serviceOutput;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var Environment
     */
    protected $twig;

    protected $limitForNotification = 7;

    /** @var QueryProcedure */
    protected $esQueryProcedure;

    /** @var ContentService */
    protected $contentService;

    /** @var PublicAffairsAgentHandler */
    protected $publicAffairsAgentHandler;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(
        ContentService $contentService,
        private readonly CurrentUserService $currentUser,
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        MailService $mailService,
        MessageBagInterface $messageBag,
        private readonly OrgaService $orgaService,
        private readonly PermissionsInterface $permissions,
        private readonly PrepareReportFromProcedureService $prepareReportFromProcedureService,
        private readonly ProcedureDeleter $procedureDeleter,
        private readonly ProcedureService $procedureService,
        PublicAffairsAgentHandler $publicAffairsAgentHandler,
        QueryProcedure $esQueryProcedure,
        ServiceOutput $serviceOutput,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator,
    ) {
        parent::__construct($messageBag);
        $this->contentService = $contentService;
        $this->esQueryProcedure = $esQueryProcedure;
        $this->mailService = $mailService;
        $this->publicAffairsAgentHandler = $publicAffairsAgentHandler;
        $this->serviceOutput = $serviceOutput;
        $this->serviceStorage = $serviceStorage;
        $this->translator = $translator;
        $this->twig = $twig;
    }

    /**
     * Gib die Liste und Filter/Sortierungen der Verfahren in der öffentlichen Beteiligung zurück
     * <p>
     * The field used for sorting is determined from getRequestValues()['sort'] which should have been set
     * by the controller using its own request object. The advantage is that this (may?) delegate the sorting to the
     * database where it belongs into.
     *
     * @throws Exception
     */
    public function getProcedureList(): array
    {
        $templatefilters = [];
        $returnVars = [];
        $requestValues = $this->getRequestValues();

        // initialize storage and output
        $serviceOutput = $this->serviceOutput;

        $esQuery = $this->esQueryProcedure;

        $esQuery->addFilterMust('master', false);
        $esQuery->addFilterMust('deleted', false);

        foreach ($esQuery->getAvailableFilters() as $availableFilter) {
            if (array_key_exists($availableFilter->getName(), $requestValues)
                && !$esQuery->isFilterValueEmpty($requestValues[$availableFilter->getName()])) {
                // Field "Amtlicher Regionalschlüssel" needs to be queried as Prefixquery
                // which equals db query "LIKE $ars%"
                if ('ars' === $availableFilter->getField()) {
                    $esQuery->addFilterMustPrefix($availableFilter->getField(), $requestValues[$availableFilter->getName()]);
                    continue;
                }
                $esQuery->addFilterMust($availableFilter->getField(), $requestValues[$availableFilter->getName()]);
            }
        }

        // full text search
        if (array_key_exists('search', $requestValues) && '' !== $requestValues['search']) {
            $templatefilters['search'] = $requestValues['search'];
            $esQuery->setSearch($esQuery->getAvailableSearch()->setSearchTerm($requestValues['search']));
        } else {
            $templatefilters['search'] = null;
        }

        // ordering direction
        $sortAscDesc = 'asc';
        if (array_key_exists('sort_ascdesc', $requestValues)) {
            $sortAscDesc = $requestValues['sort_ascdesc'];
        }

        // ordering
        $sortString = null;
        if (array_key_exists('sort', $requestValues)) {
            $sortString = $requestValues['sort'];
        }
        $allSorts = $esQuery->getAvailableSorts();
        $sort = $esQuery->getAvailableSort($sortString);

        // if no sort is found, use default sort
        if (null === $sort) {
            $sort = $esQuery->getSortDefault();
        }

        $esQuery->addSort($sort);

        // generate template variable from storage result (Output)
        $outputResult = $serviceOutput->procedureListHandler($esQuery);

        if (array_key_exists('orgaSlug', $requestValues) && '' !== $requestValues['orgaSlug']) {
            $this->permissions->checkPermission('feature_orga_slug');
            $orga = $this->orgaService->findOrgaBySlug($requestValues['orgaSlug']);
            $orgaId = $orga->getId();

            $outputResult = array_filter($outputResult, static fn (array $procedure): bool => $procedure['orgaId'] === $orgaId);
        }

        // sorting by end date is a special case and requires extra steps

        $selectedSort = $sort->getFields()[0]->getName();
        if ($this->checkIfSortProceduresByEndDateIsSelected($selectedSort)) {
            $sorter = $this->sortProceduresByParticipationEndDateIfSelected($selectedSort);
            $outputResult = $sorter->sortLegacyArrays($outputResult);
        }

        $returnVars['list'] = ['procedurelist' => $outputResult];
        $returnVars['filters'] = $templatefilters;
        $returnVars['sort']['type'] = $sort;
        $returnVars['sort']['ascdesc'] = $sortAscDesc;
        $returnVars['sort']['allAvailableSelection'] = $allSorts; // sorting list, used to identify selected option
        $returnVars['sort']['selection'] = $selectedSort; // selected option

        return $returnVars;
    }

    /**
     * Checks if sorting by endDate / publicParticipationEndDate is selected.
     */
    public function checkIfSortProceduresByEndDateIsSelected(string $selectedSort): bool
    {
        $array = [
            'publicParticipationEndDate',          'endDate',
            'publicParticipationEndDateTimestamp', 'endDateTimestamp',
        ];

        return in_array($selectedSort, $array, true);
    }

    /**
     * Applies custom sorting algorithm by endDate/publicParticipationEndDate (with or without timestamp),
     * which is required because default database sorting won't do in this case (T9388).
     */
    public function sortProceduresByParticipationEndDateIfSelected(string $sort): ProcedureSorterInterface
    {
        // these checks ensure that both "key" and "keyTimestamp" are accepted
        if (str_contains('endDateTimestamp', $sort)) {
            return new EndDateSorter();
        }
        if (str_contains('publicParticipationEndDateTimestamp', $sort)) {
            return new PublicParticipationEndDateSorter();
        }

        throw new \InvalidArgumentException('Invalid sorting: '.$sort);
    }

    /**
     * Marks the selected value in the "sorting" list, so that this value can easily be rendered "selected" in twig.
     * Note: This is only really required for the initial load, hence not for ajax.
     *
     * @return array
     */
    public function markSelectedElementInSortByField(array $procedures)
    {
        if (isset($procedures['sort']['selection'])) {
            /** @var Sort $option */
            foreach ($procedures['definition']->getAvailableSorts() as $option) {
                $option->setSelected(false);

                $selectedSort = $procedures['sort']['type'] instanceof Sort ?
                    $procedures['sort']['type']->getName() : $procedures['sort']['selection'];

                // this check ensures that both "key" and "keyTimestamp" are accepted
                // but kills possible keys as externalName and externalNameDesc
                if (str_contains((string) $selectedSort, $option->getName())) {
                    $option->setSelected(true);
                }
            }
        }

        return $procedures;
    }

    /**
     * Get single Procedure.
     *
     * @param string $ident
     * @param bool   $asObject
     *
     * @return Procedure|array|null Procedure
     *
     * @throws Exception
     */
    public function getProcedure($ident, $asObject = true)
    {
        try {
            if ($asObject) {
                return $this->procedureService->getProcedure($ident);
            }

            return $this->procedureService->getSingleProcedure($ident);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Procedures: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get single Procedure by Id. If no Procedure found throws Execption.
     *
     * @param string $ident
     *
     * @return Procedure Procedure
     *
     * @throws Exception
     */
    public function getProcedureWithCertainty($ident): Procedure
    {
        $procedure = $this->getProcedure($ident, true);
        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($ident);
        }

        return $procedure;
    }

    /**
     * Gebe die Liste der Regionen/Abonnements für den Benachrichtigungsservice eiens Users aus.
     *
     * @param string $userId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSubscriptionList($userId)
    {
        $outputResult = $this->serviceOutput;

        return $outputResult->getSubscriptionList($userId);
    }

    /**
     * Füge eine Region/Abonnement für den Benachrichtigungsservice hinzu.
     *
     * @param string $postalCode
     * @param string $city
     * @param int    $distance
     *
     * @throws MessageBagException
     */
    public function addSubscription($postalCode, $city, $distance, User $user): void
    {
        $this->procedureService->addSubscription($postalCode, $city, $distance, $user);

        // generiere eine Erfolgsmeldung
        $confirmmsg = $this->translator->trans('confirm.notification.saved');
        $this->getMessageBag()->add('confirm', $confirmmsg);
    }

    /**
     * Lösche Regionen/Abonnements aus dem Benachrichtigungsservice.
     *
     * @return int Count of successfully deleted subscriptions
     *
     * @throws Exception
     */
    public function deleteSubscriptions(array $idents): int
    {
        return count(array_filter($idents, $this->procedureService->deleteSubscription(...)));
    }

    /**
     * @throws Exception
     * @throws NoRecipientsWithEmailException
     * @throws MissingDataException           Thrown if email title or email text is empty
     */
    public function sendInvitationEmails(array $procedure, array $request): InvitationEmailResult
    {
        $helperServices = $this->getHelperServices();
        if (!array_key_exists('serviceMail', $helperServices)
            || !array_key_exists('serviceDemosPlan', $helperServices)
        ) {
            throw new \InvalidArgumentException('Mandatory Parameter missing');
        }

        $outputResult = $this->serviceOutput->procedureMemberListHandler($procedure['id'], null);

        $providedEmailTitle = $request['r_emailTitle'] ?? '';
        $providedEmailText = $request['r_emailText'] ?? '';

        if ('' === trim((string) $providedEmailTitle)) {
            throw new MissingDataException('Emailsubject is missing');
        }

        if ('' === trim((string) $providedEmailText)) {
            throw new MissingDataException('Emailtext is missing');
        }

        // generiere einen Verteiler und hole Daten zu den die Empfängern
        $recipientsWithNoEmail = [];
        $recipientsWithEmail = [];
        [$recipientsWithEmail, $recipientsWithNoEmail] = $this->getEmailRecipients(
            $outputResult['orgas'],
            $request['orga_selected'],
            $recipientsWithEmail,
            $recipientsWithNoEmail
        );
        // generiere Protokolleintrag, bereits hier, da bei Nicht-Mailversand auch ein Eintrag gemacht wird.
        $procedureAsArray = $this->serviceOutput->getProcedureWithPhaseNames($procedure['id']);
        $procedurePhase = $procedureAsArray['phase'];

        if (empty($recipientsWithEmail)) {
            throw new NoRecipientsWithEmailException('No recipient was selected');
        }

        $agencyMainEmailAddress = $procedure['agencyMainEmailAddress'] ?? '';

        foreach ($recipientsWithEmail as $recipientData) {
            // Send invitation mail for each selected public agency organisation:

            $this->sendPublicAgencyInvitationMail(
                $recipientData['email2'],
                $agencyMainEmailAddress,
                $providedEmailTitle,
                $providedEmailText,
            );

            // Send invitation mail for each cc-email-addresses
            if (isset($recipientData['ccEmails']) && is_array($recipientData['ccEmails'])) {
                foreach ($recipientData['ccEmails'] as $ccEmailAddress) {
                    $this->sendPublicAgencyInvitationMail(
                        $ccEmailAddress,
                        $agencyMainEmailAddress,
                        $providedEmailTitle,
                        $providedEmailText,
                    );
                }
            }

            // speichere den Versand in der Datenbank
            try {
                $this->procedureService->addInstitutionMail($procedure['id'], $recipientData['ident'], $procedurePhase);
            } catch (Exception $exception) {
                $this->logger->warning('Add Institutionmail failed', [$exception]);
            }
        }

        $ccEmailAddresses = $request['r_emailCc'] ?? [];
        // send planning agency emails
        $ccMailAddresses = $this->getPlaningAgencyCCEmailRecipients($procedure, $ccEmailAddresses);
        foreach ($ccMailAddresses as $singleCC) {
            $this->sendPublicAgencyInvitationMail($singleCC, $agencyMainEmailAddress, $providedEmailTitle, $providedEmailText);
        }

        try {
            foreach ($ccEmailAddresses as $ccEmailAddress) {
                $recipientsWithEmail[] = [
                    'ident'     => '',
                    'nameLegal' => $ccEmailAddress,
                    'email2'    => $ccEmailAddress,
                ];
            }

            $this->prepareReportFromProcedureService->addReportInvite(
                $recipientsWithEmail,
                $procedure['id'],
                $procedurePhase,
                $providedEmailTitle
            );
        } catch (Exception $e) {
            $this->logger->warning('Add Report in sendInvitationEmails() failed Message: ', [$e]);
        }

        return InvitationEmailResult::create(
            // Namen der eingeladenen Institutionen für Erfolsgmeldung
            array_column($recipientsWithEmail, 'nameLegal'),
            // Wenn zum Teil Empfänger ausgewählt wurden, die keine Beteiligungsemail hinterlegt haben, speicher diese für die Fehlermeldung
            array_column($recipientsWithNoEmail, 'nameLegal')
        );
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    public function sendMailToAddresses(Procedure $procedure, array $recipientEmailAddresses, $data): void
    {
        $vars = [];
        $vars['mailsubject'] = $data['r_emailTitle'];
        $vars['mailbody'] = $data['r_emailText'];
        $from = $procedure->getAgencyMainEmailAddress();
        $userEmail = $this->currentUser->getUser()->getEmail();

        $data['r_emailCc'] = array_key_exists('r_emailCc', $data) ? explode(', ', (string) $data['r_emailCc']) : [];
        // fill cc field:
        $cc = $data['r_emailCc'];
        $cc[] = $from;
        if (0 < strlen($userEmail)) {
            $cc[] = $userEmail;
        }
        $cc = array_unique($cc);

        $this->mailService->sendMail(
            'dm_toebeinladung',
            'de_DE',
            $recipientEmailAddresses,
            $from,
            $cc,
            '',
            'extern',
            $vars
        );

        try {
            $this->prepareReportFromProcedureService->addReportEmailToAddresses(
                $recipientEmailAddresses,
                $data['r_emailCc'],  // refs T11918: only email addresses which was explicit set in CC field by user
                $procedure->getId(),
                $procedure->getPhase(),
                $vars['mailsubject']
            );
        } catch (Exception $e) {
            $this->logger->warning('Add Report in sendMailToAddresses() failed Message: ', [$e]);
        }
    }

    public function transformVariables($templateVars)
    {
        if ($this->permissions->hasPermission('feature_procedure_external_desc_crop')) {
            foreach ($templateVars['list']['procedurelist'] as $key => $procedure) {
                // Wenn die Beschreibung länger ist als 285 zeichen, dann kürze sie
                if (strlen((string) $procedure['externalDesc']) > 285) {
                    $teaserExternalDesc = substr((string) $procedure['externalDesc'], 0, 285).'...';
                    // Schneide sie hinter einem Wort ab
                    $teaserExternalDesc_end = strrchr($teaserExternalDesc, ' ');
                    // Ersetze die ungekürzte TemplateVariable mit der gekürzten
                    $templateVars['list']['procedurelist'][$key]['externalDesc'] = str_replace($teaserExternalDesc_end, ' ...', $teaserExternalDesc);
                }
            }
        }

        return $templateVars;
    }

    /**
     * Automatic notification of deadlines for PublicAgencies.
     *
     * @throws Exception
     * @throws Throwable
     */
    public function sendNotificationEmailOfDeadlineForPublicAgencies()
    {
        // Feature for soon ending phases enabled?
        if ($this->permissions->hasPermission('feature_notification_ending_phase')) {
            // How many days in advance should notification been sent?
            $daysToGo = $this->limitForNotification;
            // Look only fpr participation phases for publicAgencies
            $internalPhases = $this->getDemosplanConfig()->getInternalPhasesAssoc();
            $phases = [];
            foreach ($internalPhases as $phase) {
                if ('write' === $phase['permissionset']) {
                    $phases[] = $phase['key'];
                }
            }
            // Get all procedures with given phases and time limit
            $resultProcedures = $this->getAllProceduresWithSoonEndingPhases($phases, $daysToGo);

            // Get all involved public Agencies of these procedures
            foreach ($resultProcedures as $procedure) {
                $this->getLogger()->info('Soon ending procedures found for procedure', [$procedure->getName()]);
                $involvedPublicAgencies = $procedure->getOrganisation();
                // Do they want to have a notification email? ->Info saved in Settings
                $recipients = $this->serviceOutput->checkNotificationFlagAndReturnEmailsOfAgencies($involvedPublicAgencies);

                // if there are recipients go further
                if (0 < count($recipients)) {
                    // save same for the mailtemplate
                    $procedure->setPhaseName($this->getDemosplanConfig()->getPhaseNameWithPriorityInternal($procedure->getPhase()));
                    $mailTemplateVars['procedure'] = $procedure;
                    $mailTemplateVars['daysToGo'] = $daysToGo;

                    // fetch the EmailTemplate
                    $emailText = $this->twig->load(
                        '@DemosPlanCore/DemosPlanProcedure/administration_send_notification_email_ending_phase.html.twig'
                    )->renderBlock(
                        'body_plain',
                        [
                            'templateVars' => $mailTemplateVars,
                        ]
                    );
                    $from = '';
                    $scope = 'extern';
                    $vars['mailsubject'] = $this->translator->trans(
                        'email.subject.admin.notification.ending.phase',
                        ['procedure_name' => $procedure->getName()]
                    );
                    $vars['mailbody'] = html_entity_decode(
                        $emailText,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    // Send email
                    $mailService = $this->mailService;
                    try {
                        $mailService->sendMail(
                            'dm_stellungnahme',
                            'de_DE',
                            $recipients,
                            $from,
                            '',
                            '',
                            $scope,
                            $vars
                        );
                    } catch (Exception) {
                        // error notice, something went wrong:-)
                        $this->logger->error('Notification Mail For Ending Phase could not be sent', [$procedure]);
                    }
                    // Success notice
                    $this->logger->info('Sent Notification Mail For Ending Phase');
                } else {
                    // Notice of no recipients available
                    $this->logger->notice('No recipients for notification Mail For Ending Phase found.');
                }
            }
        }
    }

    /**
     * Get all procedures within given time and phases.
     *
     * @param string[] $phaseKeys
     * @param bool     $internal  check for institution phases. false checks public phases
     *
     * @return Procedure[]|string[]
     */
    public function getAllProceduresWithSoonEndingPhases(array $phaseKeys, int $exactlyDaysToGo, bool $idsOnly = false, $internal = true): array
    {
        $procedures = [];
        try {
            // Fetch all procedure with soon ending phases
            $procedures = $this->procedureService->getListOfProceduresEndingSoon($exactlyDaysToGo, $internal);
        } catch (Exception) {
            $this->getLogger()->error('Could not get procedureList with soon ending phases');
        }

        // Choose all procedures with given phases
        $proceduresWithSoonEndingPhase = [];
        foreach ($procedures as $procedure) {
            $phase = $internal ? $procedure->getPhase() : $procedure->getPublicParticipationPhase();
            if (in_array($phase, $phaseKeys, true)) {
                $proceduresWithSoonEndingPhase[] = $idsOnly ? $procedure->getId() : $procedure;
            }
        }

        if (0 === count($proceduresWithSoonEndingPhase)) {
            $this->getLogger()->info('No soon ending procedures found');
        }

        return $proceduresWithSoonEndingPhase;
    }

    /**
     * Purge pseudo deleted procedures from database.
     *
     * @param int $limit
     *
     * @throws Exception
     */
    public function purgeDeletedProcedures($limit = 1): int
    {
        $proceduresPurged = 0;
        foreach ($this->procedureService->getDeletedProcedures($limit) as $deletedProcedure) {
            $procedureId = $deletedProcedure->getId();
            try {
                $this->procedureDeleter->beginTransactionAndDisableForeignKeyChecks();
                $this->procedureDeleter->deleteProcedures([$procedureId], false);
                $this->procedureDeleter->commitTransactionAndEnableForeignKeyChecks();
                ++$proceduresPurged;
            } catch (Exception $e) {
                $this->logger->warning("Delete Procedure '$procedureId' failed", [$e]);
            }
            /** @var NotificationReceiverRepository $notificationReceiverRepository */
            $notificationReceiverRepository = $this->entityManager->getRepository(NotificationReceiver::class);
            $notificationReceiverRepository->deleteReceiversForProcedure($deletedProcedure->getId());
        }

        return $proceduresPurged;
    }

    /**
     * Gib die Subscriptioneinträge aus, deren Auswahl von PLZ und Radius auf
     * das Verfahren zutrifft.
     *
     * @param string $procedureId
     * @param bool   $useDistance
     *
     * @throws Exception
     */
    public function getProcedureSubscriptionList($procedureId, $useDistance = true)
    {
        return $this->procedureService->getProcedureSubscriptionList($procedureId, $useDistance);
    }

    /**
     * Lösche einen oder mehrere Textbausteine.
     */
    public function deleteBoilerplates(array $boilerplates): bool
    {
        $allBoilerplatesDeleted = true;
        foreach ($boilerplates as $boilerplateId) {
            try {
                $this->procedureService->deleteBoilerplate($boilerplateId);
            } catch (Exception) {
                $allBoilerplatesDeleted = false;
            }
        }

        return $allBoilerplatesDeleted;
    }

    /**
     * @return QueryProcedure
     */
    public function getEsQueryProcedure()
    {
        return $this->esQueryProcedure;
    }

    /**
     * Get list of procedures without localization (postalcode, gemeindekennzahl etc).
     *
     * @return Setting[]|array|null
     */
    public function getProcedureLocalizationQueue()
    {
        return $this->procedureService->getProcedureLocalizationQueue();
    }

    /**
     * Removes procedure von localizationQueue.
     *
     * @param string $procedureId
     */
    public function removeProcedureFromLocalizationQueue($procedureId)
    {
        $this->procedureService->removeProcedureFromLocalizationQueue($procedureId);
    }

    /**
     * @param string $procedureId
     */
    public function markParticipated($procedureId)
    {
        try {
            $settings = $this->contentService->getSettings(
                'markedParticipated',
                SettingsFilter::whereProcedureId($procedureId)
                    ->andUser($this->currentUser->getUser())
                    ->lock(),
                false
            );
            if (is_array($settings) && 0 === count($settings)) {
                $this->contentService->setSetting('markedParticipated', [
                    'procedureId' => $procedureId,
                    'userId'      => $this->currentUser->getUser()->getId(),
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('AJAX ERROR: ContentService is unable to change Settings (ProcedureHandler->markParticipated()): ', [$e]);
        }
    }

    /**
     * @param string $procedureId
     */
    public function unmarkParticipated($procedureId)
    {
        try {
            $settings = $this->contentService->getSettings(
                'markedParticipated',
                SettingsFilter::whereProcedureId($procedureId)
                    ->andUser($this->currentUser->getUser())
                    ->lock(),
                false
            );
            if (is_array($settings)) {
                foreach ($settings as $setting) {
                    $this->contentService->deleteSetting($setting);
                }
            }
        } catch (Exception $e) {
            $this->logger->error('AJAX ERROR: ContentService is unable to change Settings (ProcedureHandler->unmarkParticipated()): ', [$e]);
        }
    }

    /**
     * @param array{data: list<array{type: non-empty-string, id: non-empty-string}>} $resourceLinkage
     *
     * @throws ProcedureNotFoundException          Thrown if no Procedure resource was found for the given procedureId
     * @throws PublicAffairsAgentNotFoundException thrown if the PublicAffairsAgent for at least one of the IDs provided in the given ResourceLinkage was not found
     * @throws InvalidArgumentException            thrown if at least one item in the given
     *                                             linkage object is not of type 'publicAffairsAgent'
     * @throws Exception
     */
    public function addInvitedPublicAffairsAgents(string $procedureId, array $resourceLinkage)
    {
        $procedure = $this->getProcedureWithCertainty($procedureId);
        $publicAffairsAgents = $this->publicAffairsAgentHandler->getFromResourceLinkage($resourceLinkage['data']);
        $this->procedureService->addOrganisations($procedure, $publicAffairsAgents);
    }

    /**
     * @param User|null $user
     *
     * @return bool true if the given $user is not null and is authorized for the given procedure
     */
    public function isUserExistentAndAuthorized(string $procedureId, $user): bool
    {
        if ($user instanceof User) {
            return $this->procedureService->isUserAuthorized($procedureId, $user);
        }

        return false;
    }

    /**
     * @param array<int, Procedure> $procedures
     *
     * @return array<int, array{id: string, name: string}> A list of undeleted, non-template procedures
     */
    public function convertProceduresForTwigAdminList(array $procedures): array
    {
        return array_map(static fn (Procedure $procedure): array => [
            'id'   => $procedure->getId(),
            'name' => $procedure->getName(),
        ], $procedures);
    }

    /**
     * @return array<int, Procedure> A list of undeleted, non-template procedures
     *
     * @throws Exception
     */
    public function getProceduresForAdmin(): array
    {
        $user = $this->currentUser->getUser();

        return $this->procedureService->getProcedureAdminList(
            [],
            null,
            $user,
            null,
            false,
            false,
            false
        );
    }

    /**
     * @throws Exception
     */
    public function isProcedureInCustomer(string $procedureId, string $subdomain): bool
    {
        /** @var ProcedureService $procedureService */
        $procedureService = $this->procedureService;

        return $procedureService->isProcedureInCustomer($procedureId, $subdomain);
    }

    /**
     * OnEndOfParticipationPhaseOfYesterdays.
     *
     * @throws Exception
     */
    public function switchToEvaluationPhasesOnEndOfParticipationPhase(): Collection
    {
        $changedInternalProcedures = collect([]);
        $changedExternalProcedures = collect([]);

        // internal:
        $internalWritePhaseKeys = $this->getDemosplanConfig()->getInternalPhaseKeys('write');
        $endedInternalProcedures = $this->procedureService->getProceduresWithEndedParticipation($internalWritePhaseKeys);

        $internalPhaseKey = 'evaluating';
        $internalPhaseName = $this->getDemosplanConfig()->getPhaseNameWithPriorityInternal($internalPhaseKey);
        // T17248: necessary because of different phasekeys per project:
        if ($internalPhaseKey === $internalPhaseName) { // not found?
            $internalPhaseKey = 'analysis';
            $internalPhaseName = $this->getDemosplanConfig()->getPhaseNameWithPriorityInternal($internalPhaseKey);
        }

        /** @var Procedure $endedInternalProcedure */
        foreach ($endedInternalProcedures as $endedInternalProcedure) {
            if (null !== $endedInternalProcedure->getEndDate()
                && !$endedInternalProcedure->getMaster() && !$endedInternalProcedure->isDeleted()) {
                $endedInternalProcedure->setPhaseKey($internalPhaseKey);
                $endedInternalProcedure->setPhaseName($internalPhaseName);
                $endedInternalProcedure->setCustomer($endedInternalProcedure->getCustomer());

                $updatedProcedure = $this->procedureService->updateProcedureObject($endedInternalProcedure);
                $changedInternalProcedures->push($updatedProcedure);
            }
        }

        // external:
        $externalWritePhaseKeys = $this->getDemosplanConfig()->getExternalPhaseKeys('write');
        $endedExternalProcedures = $this->procedureService->getProceduresWithEndedParticipation($externalWritePhaseKeys, false);

        $externalPhaseKey = 'evaluating';
        $externalPhaseName = $this->getDemosplanConfig()->getPhaseNameWithPriorityExternal($externalPhaseKey);
        // T17248: necessary because of different phasekeys per project:
        if ($externalPhaseKey === $externalPhaseName) { // not found?
            $externalPhaseKey = 'analysis';
            $externalPhaseName = $this->getDemosplanConfig()->getPhaseNameWithPriorityExternal($externalPhaseKey);
        }

        /** @var Procedure $endedExternalProcedure */
        foreach ($endedExternalProcedures as $endedExternalProcedure) {
            if (null !== $endedExternalProcedure->getPublicParticipationEndDate()
                && !$endedExternalProcedure->getMaster() && !$endedExternalProcedure->isDeleted()) {
                $endedExternalProcedure->setPublicParticipationPhase($externalPhaseKey);
                $endedExternalProcedure->setPublicParticipationPhaseName($externalPhaseName);
                $endedExternalProcedure->setCustomer($endedExternalProcedure->getCustomer());

                $updatedProcedure = $this->procedureService->updateProcedureObject($endedExternalProcedure);
                $changedExternalProcedures->push($updatedProcedure);
            }
        }

        // Success notice
        $this->getLogger()->info('Switched phases to evaluation of '.$changedInternalProcedures->count().' internal/toeb procedures.');
        $this->getLogger()->info('Switched phases to evaluation of '.$changedExternalProcedures->count().' external/public procedures.');

        return $changedExternalProcedures->merge($changedInternalProcedures)->unique();
    }

    /**
     * @param array<string, mixed> $procedure
     *
     * @return array<int, string>
     *
     * @throws UserNotFoundException
     */
    private function getPlaningAgencyCCEmailRecipients(array $procedure, array $formEmailCC): array
    {
        $ccEmailAddresses = collect();
        $ccEmailAddresses->add($procedure['agencyMainEmailAddress'] ?? '');
        $userEmail = $this->currentUser->getUser()->getEmail();
        if ('' !== $userEmail) {
            $ccEmailAddresses->add($userEmail);
        }
        // All additional agency mails
        foreach ($procedure['agencyExtraEmailAddresses'] ?? '' as $additionalAddress) {
            $ccEmailAddresses->add(trim((string) $additionalAddress));
        }
        // alle E-Mail-Adressen aus dem CC-Feld
        if (0 < count($formEmailCC)) {
            foreach ($formEmailCC as $mailAddress) {
                $ccEmailAddresses->add(trim((string) $mailAddress));
            }
        }

        return $ccEmailAddresses->toArray();
    }

    private function getEmailRecipients(
        array $orgas,
        array $orgaSelected,
        array $recipientsWithEmail,
        array $recipientsWithNoEmail): array
    {
        /** @var Orga $orgaData */
        foreach ($orgas as $orgaData) {
            if (in_array($orgaData->getId(), $orgaSelected, true)) {
                if (0 < strlen(trim($orgaData->getEmail2()))) {
                    $recipientOrga = [
                        'ident'     => $orgaData->getId(),
                        'nameLegal' => $orgaData->getName(),
                        'email2'    => $orgaData->getEmail2(),
                    ];
                    // Füge eventuelle CC-Email für Beteiligung hinzu
                    if (0 < strlen(trim($orgaData->getCcEmail2()))) {
                        $ccEmailAdresses = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $orgaData->getCcEmail2());
                        $recipientOrga['ccEmails'] = $ccEmailAdresses;
                    }
                    $recipientsWithEmail[] = $recipientOrga;
                } else {
                    $recipientsWithNoEmail[] = [
                        'ident'     => $orgaData->getId(),
                        'nameLegal' => $orgaData->getName(),
                    ];
                }
            }
        }

        return [$recipientsWithEmail, $recipientsWithNoEmail];
    }

    private function sendPublicAgencyInvitationMail(
        string $to,
        string $from,
        string $emailTitle,
        string $emailText,
    ): void {
        $vars = [];
        try {
            $vars['mailsubject'] = $emailTitle;
            $vars['mailbody'] = $emailText;

            $this->mailService->sendMail(
                'dm_toebeinladung',
                'de_DE',
                $to,
                $from,
                [],
                '',
                'extern',
                $vars
            );
        } catch (Exception $exception) {
            $this->logger->error('sendPublicAgencyInvitationMail to '.$to.' failed', [$exception]);
            $this->getMessageBag()->add('error', 'error.email.send.distinct.address',
                ['eMailAddress' => $to]
            );
        }
    }
}
