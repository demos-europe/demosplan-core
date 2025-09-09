<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureListService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PublicIndexProcedureLister;
use demosplan\DemosPlanCoreBundle\Logic\User\BrandingService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ProcedureExtension;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Elastica\Exception\NotFoundException;
use Exception;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use ReflectionException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function array_key_exists;
use function date;
use function explode;
use function is_array;
use function is_string;
use function strlen;
use function substr;

/**
 * Controller that contains methods regarding lists of procedures.
 */
class DemosPlanProcedureListController extends DemosPlanProcedureController
{
    /**
     * Public procedure search.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @param string $orgaSlug Must be empty instead of null to allow
     *                         URL generation without $orgaSlug somewhere
     *                         else in the application
     *
     * @return RedirectResponse|Response|null
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_list_search', path: '/verfahren/suche')]
    public function publicProcedureSearchAction(
        BrandingService $brandingService,
        ContentService $contentService,
        CurrentUserInterface $currentUser,
        OrgaService $orgaService,
        PermissionsInterface $permissions,
        PublicIndexProcedureLister $procedureLister,
        ProcedureListService $procedureListService,
        Request $request,
        string $orgaSlug = '')
    {
        $templateVars = [];
        try {
            if (!$permissions->hasPermission('feature_orga_slug')
                && 'DemosPlan_procedure_public_orga_index' === $request->get('_route')) {
                throw new NotFoundException('This content is not available');
            }

            $orgaRedirect = $this->handleRedirectOrgaSlug($orgaService, $orgaSlug);
            if ($orgaRedirect instanceof RedirectResponse) {
                return $orgaRedirect;
            }
            $orga = $orgaRedirect;

            // orga Branding
            if ($orga instanceof Orga && $permissions->hasPermission('area_orga_display')) {
                $orgaBranding = $brandingService->createOrgaBranding($orga);
                $templateVars['orgaBranding'] = $orgaBranding;
            }

            $user = $currentUser->getUser();

            $templateVars = $procedureLister->getPublicIndexProcedureList($request, $orgaSlug);
            $templateVars = $procedureLister->reformatPhases($currentUser->getUser()->isLoggedIn(), $templateVars);

            $templateVars = $this->collectProcedureListTemplateVars(
                $templateVars,
                $contentService,
                $user,
                $request,
                $procedureListService,
                $currentUser
            );

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanProcedure/public_index.html.twig',
                [
                    'templateVars' => $templateVars,
                    'title'        => 'procedure.list',
                    'gatewayURL'   => $this->globalConfig->getGatewayURL(),
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Orga branded index page.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @param string $orgaSlug Must be empty instead of null to allow
     *                         URL generation without $orgaSlug somewhere
     *                         else in the application
     *
     * @return RedirectResponse|Response|null
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_public_orga_index', path: '/plaene/{orgaSlug}')]
    public function publicOrgaIndexAction(
        BrandingService $brandingService,
        ContentService $contentService,
        CurrentUserInterface $currentUser,
        OrgaService $orgaService,
        PermissionsInterface $permissions,
        PublicIndexProcedureLister $procedureLister,
        ProcedureListService $procedureListService,
        Request $request,
        string $orgaSlug = '')
    {
        try {
            if (!$permissions->hasPermission('feature_orga_slug')) {
                throw new AccessDeniedException();
            }

            $orgaRedirect = $this->handleRedirectOrgaSlug($orgaService, $orgaSlug);
            if ($orgaRedirect instanceof RedirectResponse) {
                return $orgaRedirect;
            }

            $orga = $orgaRedirect;

            $user = $currentUser->getUser();

            $templateVars = $procedureLister->getPublicIndexProcedureList($request, $orgaSlug);
            $templateVars = $procedureLister->reformatPhases($currentUser->getUser()->isLoggedIn(), $templateVars);

            $templateVars['orgaSlug'] = $orgaSlug;

            // orga Branding
            if ($orga instanceof Orga && $permissions->hasPermission('area_orga_display')) {
                $orgaBranding = $brandingService->createOrgaBranding($orga);
                $templateVars['orgaBranding'] = $orgaBranding;
            }

            $templateVars = $this->collectProcedureListTemplateVars(
                $templateVars,
                $contentService,
                $user,
                $request,
                $procedureListService,
                $currentUser
            );

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanProcedure/public_index.html.twig',
                [
                    'templateVars' => $templateVars,
                    'title'        => 'procedure.public.participation',
                    'gatewayURL'   => $this->globalConfig->getGatewayURL(),
                ]
            );
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @DplanPermissions("area_admin_procedures", "area_search_submitter_in_procedures")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(path: '/verfahren/suche/stellungnahmen', methods: ['GET'], name: 'DemosPlan_procedure_search_statements')]
    public function findProceduresByStatementAuthorViewAction(ProcedureHandler $procedureHandler)
    {
        $procedures = $procedureHandler->getProceduresForAdmin();
        $procedures = $procedureHandler->convertProceduresForTwigAdminList($procedures);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_search_procedures.html.twig',
            [
                'templateVars' => ['procedures' => $procedures],
                'title'        => 'procedures.search.statements',
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_procedures")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedures_delete', path: '/verfahren/delete', methods: ['POST'], options: ['expose' => true])]
    public function deleteProceduresAction(Request $request): RedirectResponse
    {
        $this->deleteProceduresOrProcedureTemplates($request);

        return $this->redirectToRoute('DemosPlan_procedure_administration_get');
    }

    /**
     * @DplanPermissions("area_admin_procedure_templates")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_templates_delete', path: '/verfahren/blaupausen/delete', methods: ['POST'], options: ['expose' => true])]
    public function deleteMasterProceduresAction(Request $request): RedirectResponse
    {
        $this->deleteProceduresOrProcedureTemplates($request);

        return $this->redirectToRoute('DemosPlan_procedure_templates_list');
    }

    /**
     * @DplanPermissions("area_admin_procedures")
     *
     * @return StreamedResponse|RedirectResponse
     *
     * @throws Exception
     */
    #[Route(
        path: '/verfahren/export',
        name: 'DemosPlan_procedures_export',
        options: ['expose' => true],
        methods: ['POST']
    )]
    public function exportProceduresAction(ExportService $exportService, Request $request): Response
    {
        $selectedProcedures = $this->getSelectedItems($request);
        if (0 === count($selectedProcedures)) {
            $this->getMessageBag()->add('error', 'error.procedure.export.noselection');
        } else {
            return $exportService->generateProcedureExportZip($selectedProcedures, false);
        }

        return $this->redirectToRoute('DemosPlan_procedure_administration_get');
    }

    /**
     * Liste der Verfahren, die der User administriert.
     *
     * @DplanPermissions("area_admin_procedures")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_administration_post', path: '/verfahren/verwalten', methods: ['POST'])]
    #[Route(name: 'DemosPlan_procedure_administration_get', path: '/verfahren/verwalten', methods: ['GET'], options: ['expose' => true])]
    public function proceduresListAction(ProcedureListService $procedureListService): Response
    {
        $title = 'procedure.admin.list';
        $templateVars = $procedureListService->generateProcedureBaseTemplateVars([], $title);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_list.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
            ]
        );
    }

    /**
     * Liste der Verfahrens-Vorlagen, die der User administriert.
     *
     * @DplanPermissions("area_admin_procedure_templates")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_templates_list', path: '/verfahren/blaupausen', methods: ['GET'], options: ['expose' => true])]
    public function proceduresMasterListAction(
        PermissionsInterface $permissions,
        ProcedureListService $procedureListService,
        Request $request,
    ): Response {
        $templateVars = [];
        $title = 'procedure.master.admin';
        $search = $request->get('search_word');

        $filters = $this->prepareIncomingData($request, 'adminlist');
        // usually do not load any customer default blueprint
        $filters['customer'] = null;

        // Template Variable aus Storage Ergebnis erstellen(Output)
        $templateVars['list'] = $this->procedureServiceOutput->procedureTemplateAdminListHandler($filters, $search);
        $templateVars['list']['procedures'] ??= [];
        $templateVars['search'] = $search;

        $templateVars = $procedureListService->generateProcedureBaseTemplateVars($templateVars, $title);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_list_masters.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
            ]
        );
    }

    /**
     * JSON-String der Verfahren in der öffentlichen Beteiligung.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_public_list_json', path: '/list/json', options: ['expose' => true])]
    public function publicProcedureListJsonAction(
        CurrentUserInterface $currentUser,
        ContentService $contentService,
        ProcedureExtension $procedureExtension,
        ProcedureHandler $procedureHandler,
        Request $request,
    ) {
        try {
            $requestPost = $request->request->all();

            // fetch user from session
            $user = $currentUser->getUser();

            // always check subdomain for procedures
            $requestPost['subdomain'] = $this->getGlobalConfig()->getSubdomain();

            // Public agencies or planners should only see "own" procedures
            // public agencies needs to be invited, planners should own procedure
            if ($user->isPublicAgency() || $user->isPlanner()) {
                $requestPost['oId'] = $user->getOrganisationId();
            }

            if ($this->isNeitherGuestOnlyNorPlanner($user)) {
                $requestPost['participationGuestOnly'] = false;
            }

            // filter for municipalCode is a special case because we must be able to find procedures
            // by full fledged Gemeindekennziffer (gkz like 01062090) by only having municipality
            // gkz like 01062. Therefore we need to model filter as query string to be able to use
            // wildcard. The filter itself needs to be unset
            if (array_key_exists('municipalCode', $requestPost) && 0 < strlen((string) $requestPost['municipalCode'])) {
                // if user searched for something add municipalCode as an AND-Search, not OR (default search)
                $delimiter = '' !== $request->request->get('search') ? ' AND ' : ' ';
                $requestPost['search'] .= $delimiter.$requestPost['municipalCode'].'*';
                unset($requestPost['municipalCode']);
            }

            $procedureHandler->setRequestValues(
                $requestPost
            );

            $serviceOutput = $procedureHandler->getProcedureList();

            $isNotCitizen = !$user->hasRole(Role::CITIZEN);
            $serviceOutput['useInternalFields'] = $currentUser->getUser()->isLoggedIn() && $isNotCitizen;

            $userMarkedParticipated = $contentService->getSettings(
                'markedParticipated',
                SettingsFilter::whereUser($currentUser->getUser())->lock()
            );
            foreach ($userMarkedParticipated as $setting) {
                $serviceOutput['participatedProcedures'][] = $setting['procedureId'];
            }

            $htmlContent = $this->renderTemplate(
                '@DemosPlanCore/DemosPlanProcedure/public_index_list.html.twig',
                [
                    'templateVars' => $serviceOutput,
                ]
            )->getContent();

            // Bereite die Daten für die Aktualisierung der Karte auf
            $mapVars = [];

            $dateConvert = static fn ($date) => is_string($date)
                ? date('d.m.Y', substr($date, 0, -3))
                : date('d.m.Y', $date);
            foreach ($serviceOutput['list']['procedurelist'] as $procedure) {
                $coordinates = explode(
                    ',',
                    (string) $procedure['settings']['coordinate']
                );
                // es wurde keine Koordinate eingetragen
                if (2 !== count($coordinates)) {
                    continue;
                }

                $mapVars[] = [
                    'coordinateX'                  => $coordinates[0],
                    'coordinateY'                  => $coordinates[1],
                    'externalName'                 => $procedureExtension->getNameFunction($procedure),
                    'publicParticipationStartDate' => $dateConvert($procedureExtension->getStartDate($procedure)),
                    'publicParticipationEndDate'   => $dateConvert($procedureExtension->getEndDate($procedure)),
                    'publicParticipationPhaseName' => $procedureExtension->getPhase($procedure),
                    'externalDesc'                 => $procedure['externalDesc'],
                    'publicParticipationContact'   => $procedure['publicParticipationContact'],
                    'procedureUrl'                 => $this->generateUrl(
                        'DemosPlan_procedure_public_detail',
                        ['procedure' => $procedure['ident']]
                    ),
                    'procedureId'                  => $procedure['ident'],
                ];
            }

            $response = [
                'code'           => 100,
                'success'        => true,
                'mapVars'        => $mapVars,
                'responseHtml'   => $htmlContent,
                'procedureCount' => is_countable($serviceOutput['list']['procedurelist']) ? count($serviceOutput['list']['procedurelist']) : 0,
            ];

            // return result as JSON
            return new Response(Json::encode($response));
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * (Umkreis-)Suche nach Verfahren in der öffentlichen Beteiligung.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @return Response
     */
    #[Route(name: 'DemosPlan_procedure_public_suggest_procedure_location_json', path: '/suggest/procedureLocation/json', options: ['expose' => true])]
    public function searchProcedureJsonAction(
        Request $request,
        CurrentProcedureService $currentProcedureService,
        LocationService $locationService,
    ) {
        $this->profilerStart('Proj4ProfilerInit');
        $proj4 = new Proj4php();
        // Create two different projections.
        $proj3857 = new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $proj4);
        $proj4326 = new Proj(MapService::EPSG_4326_PROJECTION_LABEL, $proj4);
        $this->profilerStop('Proj4ProfilerInit');

        try {
            $requestGet = $request->query->all();
            $limit = $requestGet['maxResults'] ?? 50;

            $maxExtent = null;
            if ($request->query->has('filterByExtent') && false !== $request->query->get('filterByExtent')) {
                try {
                    $maxExtent = Json::decodeToMatchingType($request->query->get('filterByExtent'));
                } catch (Exception $e) {
                    $this->logger->error('Could not get mapExtent to generate autocomplete.', [$e]);
                    $maxExtent = null;
                }
            }

            $this->profilerStart('searchCity');
            $locationResponse = $locationService->searchCity($requestGet['query'], $limit, $maxExtent);
            $this->profilerStop('searchCity');

            $result = $locationResponse['body'];

            $maxSuggestions = $requestGet['maxResults'] ?? (is_countable($result) ? count($result) : 0);
            // Es gibt Ergebnisse, aber weniger als maxResults
            if ((is_countable($result) ? count($result) : 0) < $maxSuggestions) {
                $maxSuggestions = is_countable($result) ? count($result) : 0;
            }
            $this->profilerStart('Proj4Profiler');

            // mapExtent aka boundingBox. Points has to be in there
            $procedure = $currentProcedureService->getProcedureArray();
            if (null === $procedure) {
                throw new Exception('Cannot get procedureId.');
            }

            $filteredSuggestions = [];

            foreach ($result as $entry) {
                $pointSrc = new Point($entry['lon'], $entry['lat'], $proj4326);
                // Transform the point between datums.
                $pointDest = $proj4->transform($proj3857, $pointSrc)->toArray();
                $entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['x'] = $pointDest[0];
                $entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['y'] = $pointDest[1];
                if (null !== $maxExtent) {
                    if ($entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['x'] > $maxExtent[0]
                        && $entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['x'] < $maxExtent[2]
                        && $entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['y'] > $maxExtent[1]
                        && $entry[MapService::PSEUDO_MERCATOR_PROJECTION_LABEL]['y'] < $maxExtent[3]) {
                        $filteredSuggestions[] = [
                            'value' => $entry['postcode'].' '.$entry['name'],
                            'data'  => $entry,
                        ];
                    }
                } else {
                    $filteredSuggestions[] = [
                        'value' => $entry['postcode'].' '.$entry['name'],
                        'data'  => $entry,
                    ];
                }
            }

            $filteredSuggestions = array_slice($filteredSuggestions, 0, $maxSuggestions);

            $this->profilerStop('Proj4Profiler');
            $response = [
                'suggestions' => $filteredSuggestions,
            ];

            // return result as JSON
            return $this->renderJson($response);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }

    /**
     * Redirects to procedure filtering by orga slug.
     *
     * @DplanPermissions("area_public_participation")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_procedure_public_orga_id_index', path: '/oid/{orgaId}')]
    public function publicOrgaIdIndexAction(OrgaHandler $orgaHandler, string $orgaId)
    {
        try {
            $orga = $orgaHandler->getOrga($orgaId);

            if (null === $orga) {
                $this->getMessageBag()->add('error', 'error.organisation.not.existent');
                throw new BadRequestHttpException('orgaId not found', null, 404);
            }

            return $this->redirectToRoute('DemosPlan_procedure_public_orga_index', ['orgaSlug' => $orga->getCurrentSlug()->getName()]);
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * If received $orgaSlug finds no match, throws a NotFoundException.
     * if it finds an Orga but it's no more its current Slug, returns a ResponseRedirect with the current slug.
     * Otherwise returns null.
     *
     * @return RedirectResponse|Orga|null
     *
     * @throws NonUniqueResultException
     */
    protected function handleRedirectOrgaSlug(OrgaService $orgaService, string $orgaSlug)
    {
        if ('' !== $orgaSlug) {
            try {
                $orga = $orgaService->findOrgaBySlug($orgaSlug);
                if (!$orga->isSlugCurrent($orgaSlug)) {
                    return $this->redirectToRoute(
                        'DemosPlan_procedure_public_orga_index',
                        ['orgaSlug' => $orga->getCurrentSlug()->getName()]
                    );
                }

                return $orga;
            } catch (NoResultException) {
                throw $this->createNotFoundException('The orga does not exist');
            }
        }

        return null;
    }

    private function isNeitherGuestOnlyNorPlanner(User $user): bool
    {
        return !$user->isGuestOnly() && !$user->isPlanner();
    }

    private function deleteProceduresOrProcedureTemplates(Request $request): void
    {
        try {
            $selectedProcedures = $this->getSelectedItems($request);
            if (0 === count($selectedProcedures)) {
                $this->getMessageBag()->add('error', 'error.procedure.deleted.noselection');
            } else {
                $this->procedureService->deleteProcedure($selectedProcedures);
            }
        } catch (Exception) {
            $this->getMessageBag()->add('error', 'error.procedure.onDelete');
        }
    }

    private function getSelectedItems(Request $request): array
    {
        $selectedProcedures = $request->get('procedure_selected');

        return is_array($selectedProcedures) ? $selectedProcedures : [];
    }

    /**
     * @throws ReflectionException
     * @throws UserNotFoundException
     */
    private function collectProcedureListTemplateVars(
        array $templateVars,
        ContentService $contentService,
        User $user,
        Request $request,
        ProcedureListService $procedureListService,
        CurrentUserInterface $currentUser,
    ): array {
        // Füge die letzten aktuellen Mitteilungen hinzu
        $templateVars['list']['newslist'] = [];
        try {
            $globalNews = $contentService->getContentList($user, 3);
            $templateVars['list']['newslist'] = $globalNews;
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not add News to procedurelist: ', [$e]);
            throw $e;
        }

        $templateVars['gkz'] = false;
        if ($request->query->has('gkz')) {
            $templateVars['searchResultsHeader'] = $procedureListService->getSearchByGkzResultsTitle(
                $request->query->get('gkz'),
                $templateVars
            );
            $templateVars['gkz'] = $request->query->get('gkz');
        }

        $templateVars['participatedProcedures'] = [];
        $userMarkedParticipated = $contentService->getSettings(
            'markedParticipated',
            SettingsFilter::whereUser($currentUser->getUser())->lock()
        );
        foreach ($userMarkedParticipated as $setting) {
            $templateVars['participatedProcedures'][] = $setting['procedureId'];
        }

        if ($request->query->has('ars')) {
            $templateVars['searchResultsHeader'] = $procedureListService->getSearchByArsResultsTitle(
                $request->query->get('ars'),
                $templateVars
            );
            $templateVars['ars'] = $request->query->get('ars');
        }

        return $templateVars;
    }
}
