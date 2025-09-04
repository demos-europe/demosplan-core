<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListUserFilter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryProcedure;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function array_key_exists;
use function is_array;

/**
 * Ausgabe von Planverfahrenslisten und Editformularen dazu.
 */
class ServiceOutput
{
    /**
     * @var ProcedureService
     */
    protected $service;

    /**
     * @var ContentService
     */
    protected $contentService;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var ServiceImporter
     */
    protected $serviceImporter;

    /**
     * @var Permissions
     */
    protected $permissions;

    /**
     * @var UserService
     */
    protected $userService;

    public function __construct(
        ContentService $contentService,
        private CurrentUserService $currentUser,
        private readonly CustomerService $customerService,
        private readonly DraftStatementService $draftStatementService,
        Environment $twig,
        private readonly GlobalConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly OrgaService $orgaService,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        ServiceImporter $serviceImport,
        private readonly StatementService $statementService,
        UserService $userService
    ) {
        $this->contentService = $contentService;
        $this->permissions = $permissions;
        $this->service = $procedureService;
        $this->serviceImporter = $serviceImport;
        $this->twig = $twig;
        $this->userService = $userService;
        $this->currentUser = $currentUser;
    }

    /**
     * Get all Planningoffices.
     *
     * @return array<int, Orga>|null
     *
     * @throws Exception
     */
    public function getPlanningOffices(Customer $customer): ?array
    {
        return $this->orgaService->getPlanningOfficesList($customer);
    }

    /**
     * Get all Datainput orgs.
     *
     * @return array Orga[]
     *
     * @throws Exception
     */
    public function getDataInputOrgas()
    {
        return $this->orgaService->getDataInputOrgaList();
    }

    /**
     * Verarbeitet alle Anfragen aus der Listenansicht.
     * Liefert eine Liste von Verfahren.
     *
     * @param QueryProcedure $esQuery
     *
     * @throws Exception
     */
    public function procedureListHandler($esQuery): array
    {
        // Procedureliste abholen
        $orgaId = '';
        $user = $this->currentUser->getUser();
        if ($user instanceof User) {
            $orgaId = $user->getOrganisationId();
        }

        // when user has internal as well as external scope display both procedures
        if ($esQuery->hasScope(QueryProcedure::SCOPE_INTERNAL) || $esQuery->hasScope(QueryProcedure::SCOPE_PLANNER)) {
            $esQuery->setOrgaId($orgaId);
            $esQuery->setUserId($user->getId());
        }

        $sResult = $this->service->getPublicList($esQuery);

        // Procedureliste durchgehen und herausfinden, zu welchem Verfahren schon Stellungnahmen abgegeben wurden
        $statementFilter = new StatementListUserFilter();
        $statementFilter->setSubmitted(true)->setReleased(true);

        if ($this->permissions->hasPermission('feature_procedures_count_released_drafts')) {
            $proclistCount = count($sResult);
            for ($proclistcounter = 0; $proclistcounter < $proclistCount; ++$proclistcounter) {
                $sResult[$proclistcounter] = $this->addPhaseNames($sResult[$proclistcounter]);
                $statementResult = $this->draftStatementService->getDraftStatementList(
                    $sResult[$proclistcounter]['ident'],
                    'group',
                    $statementFilter,
                    null,
                    null,
                    $user,
                    null
                );
                if (is_array($statementResult->getResult()) && count($statementResult->getResult()) > 0) {
                    $sResult[$proclistcounter]['statementSubmitted'] = count($statementResult->getResult());
                } else {
                    $sResult[$proclistcounter]['statementSubmitted'] = 0;
                }
            }
        }

        return $sResult;
    }

    /**
     * Verarbeitet alle Anfragen aus der Listenansicht.
     *
     * @param mixed $search
     *
     * @return array
     *
     * @throws Exception
     */
    public function procedureTemplateAdminListHandler(array $filter, $search)
    {
        if (0 === count($filter)) {
            throw new InvalidArgumentException('provide at least one filter');
        }

        if ('' === $search) {
            $search = null;
        }

        $user = $this->currentUser->getUser();
        $sResult = $this->service->getProcedureAdminList(
            $filter,
            $search,
            $user,
            null,
            true,
            true,
            false
        );
        $procedureList = $sResult['result'] ?? [];
        $filters = $sResult['filterSet']['filters'] ?? [];
        $activeFilters = $sResult['filterSet']['activeFilters'] ?? [];

        foreach ($procedureList as $key => $procedure) {
            // Füge den Phasennamen aus der Config hinzu
            $procedureList[$key] = $this->addPhaseNames($procedure);
        }

        $result = [];
        if (0 !== (is_countable($procedureList) ? count($procedureList) : 0)) {
            $result['procedures'] = $procedureList;
        }
        if (0 !== (is_countable($filters) ? count($filters) : 0)) {
            $result['filters'] = $filters;
        }
        if (0 !== (is_countable($activeFilters) ? count($activeFilters) : 0)) {
            $result['activeFilters'] = $activeFilters;
        }
        if (!empty($sResult['result'])) {
            $result['sort'] = $sResult['sortingSet'];
        }

        return $result;
    }

    /**
     * Liefert die Liste der im Verfahren eingetragene Organisationen.
     *
     * @param string $procedureId
     * @param array  $filters
     *
     * @return array{procedure: array|null, orgas: array}
     *
     * @throws Exception
     */
    public function procedureMemberListHandler($procedureId, $filters): array
    {
        $procedureAsArray = $this->service->getSingleProcedure($procedureId);

        if (!array_key_exists('organisation', $procedureAsArray)) {
            return ['procedure' => $procedureAsArray];
        }

        $oStatement = [];
        if ((is_countable($procedureAsArray['organisation']) ? count($procedureAsArray['organisation']) : 0) > 0) {
            $filters['original'] = 'IS NULL';
            $statements = $this->statementService->getStatementsByProcedureId(
                $procedureId,
                $filters,
                null,
                null,
                1_000_000
            );

            foreach ($statements->getResult() as $statement) {
                if (array_key_exists($statement['oId'], $oStatement)) {
                    ++$oStatement[$statement['oId']];
                } else {
                    $oStatement[$statement['oId']] = 1;
                }
            }
        }
        $orgaIdents = [];
        foreach ($procedureAsArray['organisation'] as $key) {
            $orgaIdents[] = $key;
        }

        $orgaRes = [];
        try {
            $orgaRes = $this->orgaService->getOrganisationsByIds($orgaIdents);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der Orgas: ', [$e]);
        }
        usort(
            $orgaRes,
            static fn (Orga $a, Orga $b) => strcmp(strtolower((string) $a->getName()), strtolower((string) $b->getName()))
        );

        return [
            'procedure'      => $procedureAsArray,
            'orgas'          => $orgaRes,
            // return the numbers of statemements per orga to be able to show the stats in public
            // agency list for planners
            'orgaStatements' => $oStatement,
        ];
    }

    /**
     * Liefert eine reduzierte Liste der im Verfahren eingetragenen Organisationen.
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function getMembersOfProcedure($procedureId): array
    {
        $sResult = $this->service->getSingleProcedure($procedureId);
        $orga = [];

        foreach ($sResult['organisation'] as $key) {
            try {
                $orgaRes = $this->orgaService->getOrga($key);
                $orga[] = $orgaRes;
            } catch (Exception) {
            }
        }
        usort(
            $orga,
            static fn (Orga $a, Orga $b) => strcmp(strtolower((string) $a->getName()), strtolower((string) $b->getName()))
        );

        return $orga;
    }

    /**
     * Speichere ab, dass die Institution eine Emaileinladung bekommen hat.
     *
     * @param string $ident
     * @param string $phase
     *
     * @throws Exception
     */
    public function getInvitationEmailSentList($ident, $phase): array
    {
        return $this->service->getInstitutionMailList($ident, $phase);
    }

    /**
     * Returns a single procedure.
     *
     * @param string $procedureId the UUIDv4 of the procedure to return
     */
    public function getProcedureWithPhaseNames($procedureId): array
    {
        $sResult = $this->service->getSingleProcedure($procedureId);
        // Füge den Phasennamen aus der Config hinzu
        return $this->addPhaseNames($sResult);
    }

    /**
     * Hole die Liste der Regionen/Abonnements eines Users.
     *
     * @param string $userId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSubscriptionList($userId)
    {
        $filter = ['user' => $userId];

        return $this->service->getSubscriptionList($filter);
    }

    /**
     * Check, if Public Agencies activated NotificationFlag and fetch their email.
     *
     * @param ArrayCollection<int, Orga>|array<int, Orga> $publicAgencies
     */
    public function checkNotificationFlagAndReturnEmailsOfAgencies($publicAgencies): array
    {
        $publicAgenciesToBeNotified = [];

        // Do they want to have a notification email? ->Info saved in Settings
        /** @var Orga $publicAgency */
        foreach ($publicAgencies as $publicAgency) {
            try {
                $settingForEndingPhase = $this->contentService->getSettings('emailNotificationEndingPhase');
                foreach ($settingForEndingPhase as $settingStatement) {
                    if ($publicAgency->getId() === $settingStatement['orgaId'] && 'true' === $settingStatement['content']) {
                        // if they want notification, fetch their participationEmail and save it
                        $publicAgenciesToBeNotified[] = $publicAgency->getEmail2();
                    }
                }
            } catch (Exception) {
                $this->logger->warning('Key emailNotificationEndingPhase für Settings nicht vorhanden');
            }
        }

        return $publicAgenciesToBeNotified;
    }

    /**
     * @param string $procedure
     * @param array  $filters
     * @param string $title
     * @param array  $selectedOrgas
     *
     * @return bool|string
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePdfForMemberList($procedure, $filters, $title, $selectedOrgas = [])
    {
        // Template Variable aus Storage Ergebnis erstellen(Output)
        $templateVars = $this->procedureMemberListHandler($procedure, $filters);

        // Zeige den Namen des aktuellen internen Verfahrensschritts an
        if (isset($templateVars['procedure']['phase']) && 0 < strlen((string) $templateVars['procedure']['phase'])) {
            $templateVars['procedure']['phaseName'] = $this->config->getPhaseNameWithPriorityInternal(
                $templateVars['procedure']['phase']
            );
        }

        // an welche Institutionen wurde eine Email geschickt?
        $templateVars['orgaInvitationemailSent'] = [];
        $invitationEmailSent = $this->getInvitationEmailSentList(
            $templateVars['procedure']['ident'],
            $templateVars['procedure']['phase']
        );
        if (is_array($invitationEmailSent['result']) && 0 < count($invitationEmailSent['result'])) {
            foreach ($invitationEmailSent['result'] as $invitedOrga) {
                if (array_key_exists('organisation', $invitedOrga) && $invitedOrga['organisation'] instanceof Orga) {
                    $templateVars['orgaInvitationemailSent'][] = $invitedOrga['organisation']->getId();
                }
            }
        }

        // Only orgas, who have been selected
        $selectedOrgasOnly = [];
        if (0 < count($selectedOrgas)) {
            /** @var Orga $orga */
            foreach ($templateVars['orgas'] as $orga) {
                if (in_array($orga->getId(), $selectedOrgas, true)) {
                    $selectedOrgasOnly[] = $orga;
                }
            }
            $templateVars['orgas'] = $selectedOrgasOnly;
        }

        $content = $this->twig->render(
            '@DemosPlanCore/DemosPlanProcedure/administration_list_export.tex.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
                'procedure'    => $procedure,
            ]
        );
        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content));
        $file = base64_decode($response);

        $this->logger->debug('Got Response: '.DemosPlanTools::varExport($file, true));

        return $file;
    }

    /**
     * @param string $procedureId
     * @param string $title
     *
     * @return bool|string
     *
     * @throws Exception
     */
    public function generatePdfForTitlePage($procedureId, $title)
    {
        $templateVars = [];
        $templateVars['procedure'] = $this->service->getSingleProcedure(
            $procedureId
        );
        $content = $this->twig->render(
            '@DemosPlanCore/DemosPlanProcedure/title_page_export.tex.twig',
            [
                'procedure'    => $templateVars['procedure'],
                'templateVars' => $templateVars,
                'title'        => $title,
            ]
        );

        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content));
        $pdf = base64_decode($response);

        $this->logger->debug('Got Response: '.DemosPlanTools::varExport($pdf, true));

        return $pdf;
    }

    /**
     * May add a variety of fields to the given array, depending on circumstances like the current user.
     *
     * @param array<string, mixed> $templateVars
     *
     * @return array<string, mixed> the given array, potentially enriched with `plisProcedures`
     *
     * @throws UserNotFoundException
     */
    public function fillTemplateVars(array $templateVars): array
    {
        // Template Variable aus Storage Ergebnis erstellen(Liste an Blaupausen fuer das Pulldown)
        $masterListResult = $this->procedureTemplateAdminListHandler(
            [
                'customer' => null,
            ],
            null
        );
        $templateVars['list'] = $masterListResult;

        $templateVars['isCustomerMasterBlueprintExisting'] =
            $this->service->isCustomerMasterBlueprintExisting(
                $this->customerService->getCurrentCustomer()->getId()
            );

        return $templateVars;
    }

    /**
     * Füge den sprechenden Namen der Phase aus den Parametern hinzu.
     *
     * @param array $procedure
     *
     * @return mixed
     */
    protected function addPhaseNames($procedure)
    {
        // Institutions-Beteiligung
        $procedure['phaseName'] = '';
        $internalPhases = $this->config->getInternalPhasesAssoc();
        if (isset($procedure['phase']) && isset($internalPhases[$procedure['phase']])) {
            $procedure['phaseName'] = $internalPhases[$procedure['phase']]['name'];
        }
        // Öffentlichkeitsbeteiligung
        $procedure['publicParticipationPhaseName'] = '';
        $externalPhases = $this->config->getExternalPhasesAssoc();
        if (isset($procedure['publicParticipationPhase']) && isset($externalPhases[$procedure['publicParticipationPhase']])) {
            $procedure['publicParticipationPhaseName'] = $externalPhases[$procedure['publicParticipationPhase']]['name'];
        }

        return $procedure;
    }

    /**
     * Returns the Procedure which has, or has had, the received slug.
     *
     * @throws NonUniqueResultException
     */
    public function getProcedureBySlug(string $slug, User $user): ?Procedure
    {
        $procedure = $this->service->getProcedureBySlug($slug);

        // check whether user may enter Procedure
        $this->permissions->setProcedure($procedure);

        /**
         * This influences which phase is used for the permission check: the one for public affairs
         * agents or the one for citizens. If the participation of public affairs agents is disabled
         * their phase will remain in 'configuration' internally, thus disallowing any public
         * affairs agents roles from accessing slug URLs. To avoid this, we make a special exception
         * here and set the scope to {@link Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL}
         * (citizen) if public affairs agents participation is disabled.
         */
        $permissionScope = $user->isPublicUser() || !$this->permissions->hasPermission('feature_institution_participation')
            ? Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL
            : Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL;

        if (!$this->permissions->hasPermissionsetRead($permissionScope) && !$this->permissions->ownsProcedure()) {
            return null;
        }

        return $procedure;
    }
}
