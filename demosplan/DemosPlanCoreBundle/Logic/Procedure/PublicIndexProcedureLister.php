<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class PublicIndexProcedureLister
{
    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly GlobalConfigInterface $globalConfig, private readonly OrgaService $orgaService, private readonly PermissionsInterface $permissions, private readonly ProcedureHandler $procedureHandler, private readonly TranslatorInterface $translator)
    {
    }

    public function getPublicIndexProcedureList(Request $request, string $orgaSlug = ''): array
    {
        $requestPost = ['search' => '', ...$request->request->all()];

        if ($request->query->has('search') && '' !== $request->query->get('search')) {
            $requestPost['search'] = $request->query->get('search').'*';
        }

        if ($request->query->has('postalcode') && '' !== $request->query->get('postalcode')) {
            $requestPost['locationPostCode'] = $request->query->get('postalcode');
        }

        if ($request->query->has('ars') && '' !== $request->query->get('ars')) {
            $requestPost['ars'] = $request->query->get('ars');
        }

        if ($request->query->has('location') && '' !== $request->query->get('location')) {
            $requestPost['locationName'] = $request->query->get('location');
        }

        $requestPost['subdomain'] = $this->globalConfig->getSubdomain();

        // fetch user from session
        $user = $this->currentUser->getUser();

        /*
         * Assumptions about scopes (default is external):
         * 1) Guests should see public procedures
         * 2) Logged in citizens should see public procedures
         * 3) Logged in users should only see procedures they are are invited to
         * 4) Planners should only see their own procedures
         * 5) User with both planner and institution role should see their own procedures
         *      as well as procedures they are invited to but no public procedures
         */
        // Public agencies or planners should only see "own" procedures
        // public agencies needs to be invited, planners should own procedure
        if ($user->isPublicAgency() || $user->isPlanner()) {
            $requestPost['oId'] = $user->getOrganisationId();
        }

        if (!$user->isGuestOnly() && !$user->isPlanner() && !$user->hasRole(RoleInterface::PROCEDURE_DATA_INPUT)) {
            $requestPost['participationGuestOnly'] = false;
        }

        /*
         * Ticket: T8239
         * Here it gets little complicated. Because we get the Gemeindekennzahl from Overpass API
         * and they are sometimes not complete. So if someone is looking for the gkz "12345678" and
         * we got something like "12345" from Overpass they will not match. We decided to first do
         * a request with the gkz we get from DigitalerAtlasNord. If there is no match, we search
         * from the 5th to 8th digit.
         */
        if ($request->query->has('gkz') && '' !== $request->query->get('gkz')) {
            $gkz = $request->query->get('gkz');

            $tmpRequestParams = $requestPost;
            $tmpRequestParams['municipalCode'] = $gkz;
            $this->procedureHandler->setRequestValues($tmpRequestParams);
            $procedures = $this->procedureHandler->getProcedureList();

            if (0 === (is_countable($procedures['list']['procedurelist']) ? count($procedures['list']['procedurelist']) : 0)) {
                $requestPost['municipalCode'] = [];

                for ($i = 4, $iMax = strlen((string) $gkz); $i <= $iMax; ++$i) {
                    $gzkPart = substr((string) $gkz, 0, $i + 1);
                    $requestPost['municipalCode'][] = $gzkPart;
                }

                // Need to remove old filter from esQueryProcedure.
                $this->procedureHandler->getEsQueryProcedure()->removeFilterMust('municipalCode');
            } else {
                $requestPost['municipalCode'] = $gkz;
            }
        }

        $this->procedureHandler->setRequestValues($requestPost);
        $procedures = $this->procedureHandler->getProcedureList();

        // projektspezfische Anpassung der Variablen ermöglichen
        $procedures = $this->procedureHandler->transformVariables($procedures);
        $procedures['definition'] = $this->procedureHandler->getEsQueryProcedure();
        $procedures = $this->procedureHandler->markSelectedElementInSortByField($procedures);

        if ('' !== $orgaSlug) {
            $this->permissions->checkPermission('feature_orga_slug');
            $orga = $this->orgaService->findOrgaBySlug($orgaSlug);
            $orgaId = $orga->getId();

            $procedures['list']['procedurelist'] = array_filter($procedures['list']['procedurelist'], fn ($procedure) => $procedure['orgaId'] === $orgaId);
        }

        return $procedures;
    }

    /**
     * @param array<string,mixed> $procedures
     *
     * @return array<string,mixed>
     *
     * @throws UserNotFoundException
     */
    public function reformatPhases(bool $isLoggedIn, array $procedures): array
    {
        $includePreviewed = $this->permissions->hasPermission('feature_procedure_preview');

        $procedures['externalPhases'] = $this->globalConfig->getExternalPhases('read||write', $includePreviewed);
        $procedures['internalPhases'] = $this->globalConfig->getInternalPhases('read||write', $includePreviewed);
        $procedures['useInternalFields'] = $isLoggedIn && !$this->currentUser->getUser()->hasRole(RoleInterface::CITIZEN);

        // Wenn es Verfahren gibt, dann ersetze die Label der Phasen aus der Config
        $procedures['filterName'] = [
            'phase'    => $this->translator->trans('procedure.public.phase'),
            'orgaName' => $this->translator->trans('procedure.agency'),
        ];

        if (isset($procedures['list']['procedurelist'], $procedures['list']['filters']['filters']['phase'])
            && 0 < (is_countable($procedures['list']['procedurelist']) ? count($procedures['list']['procedurelist']) : 0)) {
            foreach ($procedures['list']['filters']['filters']['phase'] as $key => $filterEntry) {
                if ($procedures['useInternalFields']) {
                    $procedures['list']['filters']['filters']['phase'][$key]['label'] = $this->globalConfig->getPhaseNameWithPriorityInternal(
                        $filterEntry['value']
                    );
                    continue;
                }
                $procedures['list']['filters']['filters']['phase'][$key]['label'] = $this->globalConfig->getPhaseNameWithPriorityExternal(
                    $filterEntry['value']
                );
            }
        }

        return $procedures;
    }
}
