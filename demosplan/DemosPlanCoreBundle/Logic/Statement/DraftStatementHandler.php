<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\NotificationReceiverRepository;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

/**
 * Speicherung von Planterunterlagen.
 */
class DraftStatementHandler extends CoreHandler
{
    /** @var DraftStatementService */
    protected $draftStatementService;

    /** @var EntityManagerInterface */
    protected $doctrine;

    /** @var FileService */
    protected $fileService;

    /** @var UserService */
    protected $userService;

    /** @var ContentService */
    protected $contentService;

    /** @var MailService */
    protected $mailService;

    /** @var ProcedureHandler */
    protected $procedureHandler;

    /** @var RouterInterface */
    protected $router;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * Constructor.
     */
    public function __construct(
        ContentService $serviceContent,
        private readonly CurrentUserInterface $currentUser,
        DraftStatementService $draftStatementService,
        EntityManagerInterface $doctrine,
        Environment $twig,
        FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        MailService $mailService,
        MessageBagInterface $messageBag,
        ProcedureHandler $procedureHandler,
        RouterInterface $router,
        TranslatorInterface $translator,
        UserService $userService,
    ) {
        parent::__construct($messageBag);
        $this->contentService = $serviceContent;
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
        $this->mailService = $mailService;
        $this->procedureHandler = $procedureHandler;
        $this->router = $router;
        $this->draftStatementService = $draftStatementService;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->userService = $userService;
    }

    /**
     * Verarbeitet alle Stellungnahme Einreichen aus der Listenansicht.
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param User  $user
     * @param array $procedure
     *
     * @return bool
     *
     * @throws Throwable
     */
    public function releaseHandler(array $idents, $user = null, $procedure = null)
    {
        $result = $this->draftStatementService->releaseDraftStatement($idents);
        try {
            $user ??= $this->currentUser->getUser();
            $this->sendNotificationEmailOnReleasedStatement($idents, $user, $procedure);
        } catch (Exception $e) {
            $this->logger->warning('Could not send notification mail ', [$e]);
        }

        return $result;
    }

    /**
     * Notify coordinator about new released statements.
     *
     * @param string[] $releasedStatements
     * @param User     $user
     * @param array    $procedure
     *
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function sendNotificationEmailOnReleasedStatement($releasedStatements, $user, $procedure)
    {
        $mailTemplateVars = [];
        $vars = [];
        // skip for citizen and guests
        if ($user->isPublicUser()) {
            return;
        }

        // get all users of orga
        $orgaUsers = $this->userService->getUsersOfOrganisation($user->getOrganisationId());

        // prepare mail
        $mailTemplateVars['statements'] = $releasedStatements;
        $mailTemplateVars['procedure'] = $procedure;

        $emailText = $this->twig->load(
            '@DemosPlanCore/DemosPlanStatement/send_notification_email_for_released_statement.html.twig'
        )
            ->renderBlock(
                'body_plain',
                [
                    'templateVars' => $mailTemplateVars,
                ]
            );

        // send email from current user, otherwise platform systemmail would be used
        $from = $user->getEmail();
        $scope = 'extern';
        $vars['mailsubject'] = $this->translator->trans(
            'email.subject.statement.released'
        );
        $vars['mailbody'] = html_entity_decode(
            $emailText,
            ENT_QUOTES,
            'UTF-8'
        );

        // Notify any coordinator
        foreach ($orgaUsers as $orgaUser) {
            try {
                // user must be coordinator
                if (!$orgaUser->hasRole(Role::PUBLIC_AGENCY_COORDINATION)) {
                    continue;
                }

                // do not send mail to self
                if ($orgaUser->getId() === $user->getId()) {
                    continue;
                }

                // does the coordinator wants to get notification emails?
                $userSettings = $this->contentService->getSettings(
                    'emailNotificationReleasedStatement',
                    SettingsFilter::whereUser($orgaUser)->lock(),
                    false
                );
                // continue if user does not want to get mails
                if (is_array($userSettings)
                    && 1 === count($userSettings)
                    && false === $userSettings[0]->getContentBool()) {
                    continue;
                }
                // by default coordinator gets mails, if not explicitly denied

                // Send email
                $this->mailService->sendMail(
                    'dm_stellungnahme',
                    'de_DE',
                    $orgaUser->getEmail(),
                    $from,
                    '',
                    '',
                    $scope,
                    $vars
                );
            } catch (Exception $e) {
                $this->logger->warning('Could not send notification mail ', [$e]);
            }
        }
    }

    /**
     * Speichert die manuelle Sortierung der DraftStatement Liste.
     *
     * @param string $procedure
     *                          Verfahrens ID
     * @param string $context
     *                          Der Bezug unter dem die manuelle Sortierung gespeichert wurde. z.B. orga:{ident} oder user:{ident} / ident = ID ohne Klammer
     * @param string $idents
     *                          Komma separierte Liste von IDs
     *
     * @throws Exception
     */
    public function manualSortHandler($procedure, $context, $idents): bool
    {
        if ('' == $idents) {
            return false;
        }

        if ('delete' === $idents) {
            $idents = '';
        }

        return $this->draftStatementService->setManualSort($procedure, $context, $idents);
    }

    /**
     * Veroeffentlichung der Stellungnahmen für andere Toebs
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param array $idents
     *
     * @return bool
     */
    public function publishHandler($idents)
    {
        $noerror = true;
        foreach ($idents as $ident) {
            $statement = [
                'ident'     => $ident,
                'showToAll' => true,
            ];
            $result = $this->draftStatementService->updateDraftStatement($statement);
            if (!array_key_exists('showToAll', $result) || true !== $result['showToAll']) {
                $noerror = false;
            }
        }

        return $noerror;
    }

    /**
     * Veröffentlichung der Stellungnahmen für andere Toebs zurücknehmen
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param array $idents
     *
     * @return bool
     */
    public function unpublishHandler($idents)
    {
        $noerror = true;
        foreach ($idents as $ident) {
            $statement = [
                'ident'     => $ident,
                'showToAll' => false,
            ];
            $result = $this->draftStatementService->updateDraftStatement($statement);
            if (!array_key_exists('showToAll', $result) || false !== $result['showToAll']) {
                $noerror = false;
            }
        }

        return $noerror;
    }

    /**
     * Einreichen der Stellungnahmen.
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param array<int, string> $draftStatementIds
     * @param bool               $gdprConsentReceived true if the GDPR consent was received
     *
     * @retrun array<int, Statement>
     *
     * @throws Exception
     */
    public function submitHandler(
        array $draftStatementIds,
        string $notificationReceiverId = '',
        bool $gdprConsentReceived = false,
    ): array {
        $county = null;
        if ('' != $notificationReceiverId) {
            /** @var NotificationReceiverRepository $countyRepo */
            $countyRepo = $this->doctrine->getRepository(NotificationReceiver::class);
            $county = $countyRepo->get($notificationReceiverId);
        }
        try {
            return $this->draftStatementService->submitDraftStatement(
                $draftStatementIds,
                $this->currentUser->getUser(),
                $county,
                $gdprConsentReceived,
                false
            );
        } catch (Exception $e) {
            $this->logger->warning('Statement could not be submitted: ', [$e]);
            throw $e;
        }
    }

    /**
     * Verarbeitet alle Löschbefehle aus der Listenansicht.
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param string $ident
     * @param string $reason
     *
     * @return bool
     *
     * @throws Exception
     */
    public function rejectHandler($ident, $reason)
    {
        return $this->draftStatementService->rejectDraftStatement($ident, $reason);
    }

    /**
     * Verarbeitet alle Löschbefehle aus der Listenansicht.
     * Liefert das Ergebnis aus dem Webservice.
     */
    public function deleteHandler(string $draftStatementId): bool
    {
        // @improve T12803
        return $this->draftStatementService->deleteDraftStatementById($draftStatementId);
    }

    /**
     * Verarbeitet alle eingegebenen Daten aus dem Neu-Formular
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param string $procedureId
     * @param array  $data
     *
     * @return array|bool
     *
     * @throws Exception
     */
    public function newHandler($procedureId, $data)
    {
        if (!array_key_exists('action', $data)) {
            return false;
        }

        if (!in_array($data['action'], ['statementnew', 'statementpublicnew'])) {
            return false;
        }

        // fülle $statement mit den bereinigten Werten aus $data
        $statement = $this->getStatementStandardData($procedureId, $data);

        $statement = $this->addStatementUserData($statement);

        if ($this->currentUser->hasPermission('feature_draft_statement_add_address_to_institutions')) {
            $statement = $this->addUserAddressData($statement);
        }

        return $this->draftStatementService->addDraftStatement($statement);
    }

    /**
     * Verarbeitet alle eingegebenen Daten aus der Detailseite/Editseite.
     * Liefert das Ergebnis aus dem Webservice.
     *
     * @param array $data
     *
     * @return array|false
     *
     * @throws Exception
     */
    public function updateDraftStatement($data)
    {
        $statement = [];

        if (!array_key_exists('action', $data)) {
            return false;
        }

        if ('statementedit' !== $data['action']) {
            return false;
        }

        // Array auf
        if (array_key_exists('r_ident', $data)) {
            $statement['ident'] = $data['r_ident'];
        }

        if (array_key_exists('r_text', $data)) {
            $statement['text'] = strip_tags((string) $data['r_text'], '<a><br><em><i><li><mark><ol><p><s><span><strike><strong><u><ul>');
        }

        if (array_key_exists('r_paragraphID', $data) && 'undefined' !== $data['r_paragraphID']) {
            $statement['paragraphId'] = $data['r_paragraphID'];
        }

        if (array_key_exists('r_elementID', $data)) {
            $statement['elementId'] = $data['r_elementID'];
        }

        if (array_key_exists('r_documentID', $data)) {
            $statement['documentId'] = $data['r_documentID'];
        }

        if (array_key_exists('r_represents', $data)) {
            $statement['represents'] = $data['r_represents'];
        }

        if (array_key_exists('r_uploaddocument', $data)) {
            if ((is_string($data['r_uploaddocument']) && 0 < strlen($data['r_uploaddocument']))
                || (is_array($data['r_uploaddocument']) && 0 < count($data['r_uploaddocument']))) {
                $statement['files'] = $data['r_uploaddocument'];
            }
        }

        if (array_key_exists('delete_file', $data) && isset($statement['ident'])) {
            $statement['files_to_remove'] = $data['delete_file'];
        }

        $statement['publicAllowed'] = $this->isPublicAllowed($data);

        if (array_key_exists('r_isNegativeReport', $data) && 1 == $data['r_isNegativeReport']) {
            $statement['negativ'] = true;
        } else {
            $statement['negativ'] = false;
        }

        if (array_key_exists('userStreet', $data)) {
            $statement['uStreet'] = $data['userStreet'];
        }
        if (array_key_exists('houseNumber', $data)) {
            $statement['houseNumber'] = $data['houseNumber'];
        }
        if (array_key_exists('userPostalCode', $data)) {
            $statement['uPostalCode'] = $data['userPostalCode'];
        }
        if (array_key_exists('userCity', $data)) {
            $statement['uCity'] = $data['userCity'];
        }
        if (array_key_exists('userName', $data)) {
            $statement['uName'] = $data['userName'];
        }
        if (array_key_exists('userEmail', $data)) {
            $statement['uEmail'] = $data['userEmail'];
        }
        if (array_key_exists('feedback', $data)) {
            $statement['feedback'] = $data['feedback'];
        }
        if (array_key_exists('uFeedback', $data)) {
            $statement['uFeedback'] = $data['uFeedback'];
        }

        $statement = $this->draftStatementService->extractGeoData($data, $statement);

        if (!array_key_exists('r_elementID', $data) || '' != $data['r_elementID']) {
            $statement['elementId'] = $this->draftStatementService->determineStatementCategory($data['procedureId'], $data);
        }

        return $this->draftStatementService->updateDraftStatement($statement);
    }

    /**
     * Fill Statementarray with Values.
     *
     * @param string $procedureId
     * @param array  $data
     *
     * @return array $statement
     *
     * @throws Exception
     */
    public function getStatementStandardData($procedureId, $data)
    {
        $statement = [];

        if (array_key_exists('r_text', $data)) {
            $data['r_text'] = str_replace("\r\n", '', (string) $data['r_text']);
            $statement['text'] = strip_tags(
                $data['r_text'],
                '<a><br><em><i><li><mark><ol><p><s><span><strike><strong><u><ul>'
            );
        }

        // formNames from participationArea
        if (array_key_exists('r_paragraph_id', $data)) {
            $statement['paragraphId'] = $data['r_paragraph_id'];
        }

        if (array_key_exists('r_document_id', $data)) {
            $statement['documentId'] = $data['r_document_id'];
        }

        if (array_key_exists('r_element_id', $data)) {
            $statement['elementId'] = $data['r_element_id'];
        }

        // old formNames
        if (array_key_exists('r_paragraphID', $data)) {
            $statement['paragraphId'] = $data['r_paragraphID'];
        }

        if (array_key_exists('r_documentID', $data)) {
            $statement['documentId'] = $data['r_documentID'];
        }

        if (array_key_exists('r_elementID', $data)) {
            $statement['elementId'] = $data['r_elementID'];
        }

        if (array_key_exists('r_polygon', $data)) {
            $statement['polygon'] = $data['r_polygon'];
        }

        if (array_key_exists('r_represents', $data)) {
            $statement['represents'] = $data['r_represents'];
        }

        if (array_key_exists('r_uploaddocument', $data) && '' != $data['r_uploaddocument']) {
            $statement['files'] = $data['r_uploaddocument'];
        }

        $statement['publicAllowed'] = false;
        if (array_key_exists('r_makePublic', $data) && 'off' !== $data['r_makePublic']) {
            $statement['publicAllowed'] = true;
        }

        if (array_key_exists('r_isNegativeReport', $data) && 1 == $data['r_isNegativeReport']) {
            $statement['negativ'] = true;
        }

        $statement['elementId'] = $this->draftStatementService->determineStatementCategory($procedureId, $data);

        $statement['pId'] = $procedureId;

        return $this->draftStatementService->extractGeoData($data, $statement);
    }

    /**
     * Adds User Metadata to Statement.
     */
    protected function addStatementUserData(array $data): array
    {
        $user = $this->currentUser->getUser();
        $userData = [
            'uId'       => $user->getId(),
            'uName'     => $user->getFullname(),
            'dId'       => $user->getDepartmentId(),
            'dName'     => $user->getDepartmentNameLegal(),
            'oId'       => $user->getOrganisationId(),
            'oName'     => $user->getOrganisationNameLegal(),
            'private'   => !$user->isPublicAgency(), //Indicates visibility to other organisation members
        ];

        return array_merge($data, $userData);
    }

    /**
     * Verarbeitet alle Anfragen aus der Listenansicht.
     * Liefert eine Liste von Verfahren.
     *
     * @param string     $procedure
     * @param string     $scope
     * @param string     $search
     * @param array|null $sort
     * @param User       $user
     * @param string     $manualSortScope
     * @param bool       $toLegacy
     *
     * @throws Exception
     *
     * @internal param $ Array ProcedureID filter search sort
     */
    public function statementListHandler(
        $procedure,
        $scope,
        StatementListUserFilter $filter,
        $search,
        $sort,
        $user,
        $manualSortScope,
        $toLegacy = true,
    ): StatementListHandlerResult {
        $sResult = $this->draftStatementService->getDraftStatementList(
            $procedure,
            $scope,
            $filter,
            $search,
            $sort,
            $user,
            $manualSortScope,
            $toLegacy
        );

        $activeSort = ToBy::create('', '');
        $sortingSet = $sResult->getSortingSet();

        if (is_array($sortingSet)) {
            foreach ($sortingSet as $sort) {
                if (true === $sort['active']) {
                    $activeSort = ToBy::create($sort['name'], $sort['sorting']);
                }
            }
        }

        $activeFilters = [];

        return new StatementListHandlerResult(
            $sResult->getResult(),
            $sResult->getFilterSet(),
            $sortingSet,
            $activeSort,
            $sResult->getManuallySorted() ?? false,
            $activeFilters
        );
    }

    /**
     * Gibt die Liste mit fuer andere Toeb veröffentlichten Stellungnahmen aus
     * Liefert eine Liste von Verfahren.
     *
     * @param string     $procedure
     * @param string     $search
     * @param array|null $sort
     *
     * @throws Exception
     */
    public function statementOtherCompaniesListHandler(
        $procedure,
        $search,
        StatementListUserFilter $filter,
        $sort,
    ): StatementListHandlerResult {
        $sResult = $this->draftStatementService->getDraftStatementListFromOtherCompanies(
            $procedure,
            $filter,
            $search,
            $sort,
            $this->currentUser->getUser()
        );

        $activeSort = ToBy::create('', '');

        if (is_array($sResult->getSortingSet())) {
            foreach ($sResult->getSortingSet() as $sort) {
                if (true === $sort['active']) {
                    $activeSort = ToBy::create($sort['name'], $sort['sorting']);
                }
            }
        }

        return new StatementListHandlerResult(
            $sResult->getResult(),
            $sResult->getFilterSet(),
            $sResult->getSortingSet(),
            $activeSort,
            $sResult->getManuallySorted() ?? false,
            $sResult->getSortingSet(),
        );
    }

    public function getNotificationReceiversByProcedure($procedureId)
    {
        return $this->draftStatementService->getNotificationReceiversByProcedure($procedureId);
    }

    /**
     * Returns a single {@link DraftStatement}.
     *
     * @param string $ident UUIDv4 of the DraftStatement to get
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function getSingleDraftStatement($ident)
    {
        return $this->draftStatementService->getSingleDraftStatement($ident);
    }

    /**
     * Creates Emails (MailSend-Entries) for unsubmitted DraftStatements of Procedures,
     * which current phase is a writing phase and is going to end in specific amount of days.
     * Per Procedure and user will be one email created, which contains the amount of unsubmitted DraftStatements
     * of the respective user.
     *
     * @param bool $internal true, if emails for all internal draft statements will be sent, otherwise emails for all external draft statements will be sent
     *
     * @return int number of created MailSend-Entries
     *
     * @throws Exception
     * @throws Throwable
     */
    public function createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser(int $exactlyDaysToGo, bool $internal): int
    {
        if ($internal) {
            $internalWritePhaseKeys = $this->getDemosplanConfig()->getInternalPhaseKeys('write');
            $soonEndingProcedureIds = $this->getProcedureHandler()
                ->getAllProceduresWithSoonEndingPhases($internalWritePhaseKeys, $exactlyDaysToGo, true);
        } else {
            $externalWritePhaseKeys = $this->getDemosplanConfig()->getExternalPhaseKeys('write');
            $soonEndingProcedureIds = $this->getProcedureHandler()
                ->getAllProceduresWithSoonEndingPhases($externalWritePhaseKeys, $exactlyDaysToGo, true, false);
        }

        $draftStatements = $this->draftStatementService
            ->getUnsubmittedDraftStatementsOfProcedures($soonEndingProcedureIds, $internal);

        return $this->createEMailsOfDraftStatementsForUsers($draftStatements, $exactlyDaysToGo);
    }

    protected function getProcedureHandler(): ProcedureHandler
    {
        return $this->procedureHandler;
    }

    /**
     * Creates Emails (MailSend-Entries) for given DraftStatements.
     * Per Procedure and user will be one email created, which contains the amount of given DraftStatements of user.
     *
     * @param DraftStatement[] $draftStatements
     * @param int              $exactlyDaysToGo
     *
     * @throws Exception
     * @throws Throwable
     */
    protected function createEMailsOfDraftStatementsForUsers($draftStatements, $exactlyDaysToGo): int
    {
        $numberOfCreatedMails = 0;
        $procedures = [];

        // group by procedures and user/toeb:
        foreach ($draftStatements as $draftStatement) {
            $user = $draftStatement->getUser();
            // only for users, who has enabled notification for unsubmitted draft statements:
            if ($user->getFlag(UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value)) {
                $procedures[$draftStatement->getProcedureId()]['procedure'] = $draftStatement->getProcedure();
                $procedures[$draftStatement->getProcedureId()]['users'][$draftStatement->getUId()]['user'] = $user;
                $procedures[$draftStatement->getProcedureId()]['users'][$draftStatement->getUId()]['draftStatements'][] = $draftStatement->getUser();
            }
        }

        foreach ($procedures as $procedureAndUsers) {
            /** @var Procedure $procedure */
            $procedure = $procedureAndUsers['procedure'];

            $link = $this->router
                ->generate('DemosPlan_statement_list_draft', ['procedure' => $procedure->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL);

            $procedureName = $procedure->getExternalName();
            $endDate = $procedure->getEndDate()->format('d.m.Y');
            foreach ($procedureAndUsers['users'] as $userAndDraftStatements) {
                /** @var User $user */
                $user = $userAndDraftStatements['user'];

                $emailText = $this->twig->load(
                    '@DemosPlanCore/DemosPlanStatement/send_notification_email_for_unsubmitted_draft_statements.html.twig'
                )->renderBlock(
                    'body_plain',
                    [
                        'templateVars' => [
                            'numberOfUnsubmittedDraftStatements' => count($userAndDraftStatements['draftStatements']),
                            'userFullName'                       => $user->getFullname(),
                            'procedureName'                      => $procedureName,
                            'projectName'                        => $this->globalConfig->getProjectName(),
                            'link'                               => $link,
                            'exactlyDaysToGo'                    => $exactlyDaysToGo,
                            'endDate'                            => $endDate,
                        ],
                    ]
                );

                $this->sendNewDraftStatementNotification(
                    'email.subject.unsubmitted.draft_statements',
                    $emailText,
                    $user->getEmail()
                );

                ++$numberOfCreatedMails;
            }
        }

        return $numberOfCreatedMails;
    }

    /**
     * @param string $subjectTransKey
     * @param string $emailText
     * @param string $recipients
     *
     * @throws Exception
     */
    protected function sendNewDraftStatementNotification($subjectTransKey, $emailText, $recipients): void
    {
        $variables = [];
        $variables['mailsubject'] = $this->translator->trans($subjectTransKey);
        $variables['mailbody'] = html_entity_decode(
            $emailText,
            ENT_QUOTES,
            'UTF-8'
        );
        // Send email
        $this->mailService->sendMail(
            'dm_stellungnahme',
            'de_DE',
            $recipients,
            '',
            '',
            '',
            'extern',
            $variables
        );
    }

    /**
     * @throws UserNotFoundException
     */
    public function findCurrentUserDraftStatements(string $procedureId): array
    {
        return $this->draftStatementService->findCurrentUserDraftStatements($procedureId);
    }

    /**
     * @param array<string, string> $data
     */
    private function isPublicAllowed(array $data): bool
    {
        return array_key_exists('r_makePublic', $data)
            && false != $data['r_makePublic']
            && 'false' !== $data['r_makePublic'];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function addUserAddressData(array $data): array
    {
        $orga = $this->currentUser->getUser()->getOrga();
        $userAddressData = [
            'uCity'         => $orga->getCity(),
            'uPostalCode'   => $orga->getPostalcode(),
            'uStreet'       => $orga->getStreet(),
            'houseNumber'   => $orga->getHouseNumber(),
        ];

        return [...$data, ...$userAddressData];
    }
}
