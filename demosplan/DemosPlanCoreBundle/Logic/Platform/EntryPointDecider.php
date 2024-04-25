<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Platform;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\EntrypointRoute;
use Psr\Log\LoggerInterface;

class EntryPointDecider implements EntryPointDeciderInterface
{
    /**
     * @var GlobalConfig
     */
    protected $globalConfig;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    public function __construct(GlobalConfigInterface $globalConfig, LoggerInterface $logger, PermissionsInterface $permissions)
    {
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->permissions = $permissions;
    }

    /**
     * Determine the public index page.
     */
    public function determinePublicEntrypoint(): EntrypointRoute
    {
        $entrypointRoute = new EntrypointRoute();

        $publicIndexRoute = $this->globalConfig->getPublicIndexRoute();
        if ('' !== $publicIndexRoute) {
            $doRedirect = !str_contains($publicIndexRoute, '::');

            ($doRedirect)
                ? $entrypointRoute->setRoute($publicIndexRoute)
                : $entrypointRoute->setController($publicIndexRoute);

            $entrypointRoute->setDoRedirect($doRedirect);
            $entrypointRoute->setParameters($this->globalConfig->getPublicIndexRouteParameters());
        }

        $entrypointRoute->lock();

        return $entrypointRoute;
    }

    public function determineEntryPointForUser(User $user): EntrypointRoute
    {
        $entrypointRoute = new EntrypointRoute();
        $entrypointRoute->setDoRedirect(true);

        // DO NOT LIGHTLY MOVE CASES IN THIS SWITCH AS THE ORDER IN WHICH THE CASES ARE CHECKED IS IMPORTANT
        switch (true) {
            case $user->hasRole(Role::GUEST):
                $entrypointRoute->setController('demosplan\DemosPlanCoreBundle\Controller\Platform\EntrypointController::indexAction');
                $entrypointRoute->setDoRedirect(false);
                $this->logger->info('Entrypoint guest');
                break;

            case $user->hasAnyOfRoles(
                [
                    Role::PLANNING_AGENCY_ADMIN,
                    Role::PLANNING_AGENCY_WORKER,
                    Role::PRIVATE_PLANNING_AGENCY,
                ]
            ):
                $entrypointRoute->setRoute('DemosPlan_procedure_administration_get');
                $this->logger->info('Entrypoint planner');
                break;

            case $user->isHearingAuthority():
                $entrypointRoute->setRoute('DemosPlan_procedure_administration_get');
                $this->logger->info('Entrypoint hearing authority');
                break;

            case $user->hasRole(Role::CONTENT_EDITOR):
                $entrypointRoute->setRoute('DemosPlan_faq_administration_faq');
                $this->logger->info('Entrypoint content editor');
                break;

            case $user->hasRole(Role::ORGANISATION_ADMINISTRATION)
            && $this->permissions->hasPermission('area_manage_users'):
                $entrypointRoute->setRoute('DemosPlan_user_list');
                $this->logger->info('Entrypoint organisation administration');
                break;

            case $user->hasRole(Role::ORGANISATION_ADMINISTRATION)
            && $this->permissions->hasPermission('area_institution_tag_manage'):
                $entrypointRoute->setRoute('DemosPlan_get_institution_tag_management');
                $this->logger->info('Entrypoint organisation administration');
                break;

            case $user->isCitizen() || $user->isPublicAgency():
                $entrypointRoute->setController('demosplan\DemosPlanCoreBundle\Controller\Platform\EntrypointController::indexAction');
                $entrypointRoute->setDoRedirect(false);
                $this->logger->info('Entrypoint public user');
                break;

            case $user->hasRole(Role::PLANNING_SUPPORTING_DEPARTMENT):
                $entrypointRoute->setRoute('DemosPlan_statement_fragment_list_fragment_reviewer');
                $this->logger->info('Entrypoint supporting department');
                break;

            case $user->hasRole(Role::PLATFORM_SUPPORT)
            && !$this->permissions->hasPermission('area_organisations_view'):
                $entrypointRoute->setRoute('DemosPlan_statistics');
                $this->logger->info('Entrypoint support statistics');
                break;

            case $user->hasAnyOfRoles(
                [
                    Role::PLATFORM_SUPPORT,
                    Role::CUSTOMER_MASTER_USER,
                ]
            ) && $this->permissions->hasPermissions(['area_organisations_view', 'area_organisations_view_of_customer'], 'OR'):
                $entrypointRoute->setRoute('DemosPlan_orga_list');
                $this->logger->info('Entrypoint support orga list');
                break;

            case $user->hasRole(Role::PROCEDURE_DATA_INPUT):
                $entrypointRoute->setRoute('DemosPlan_procedure_list_data_input_orga_procedures');
                $this->logger->info('Entrypoint data input');
                break;

            case $user->hasRole(Role::PUBLIC_AGENCY_SUPPORT):
                $entrypointRoute->setRoute('DemosPlan_user_mastertoeblist');
                $this->logger->info('Entrypoint public agency support');
                break;

            case $user->hasRole(Role::BOARD_MODERATOR):
                $entrypointRoute->setRoute('DemosPlan_forum_development');
                $this->logger->info('Entrypoint moderator');
                break;

            default:
                $entrypointRoute->setRoute('DemosPlan_user_portal');
                $this->logger->info('Entrypoint default');
                break;
        }

        $entrypointRoute->lock();

        return $entrypointRoute;
    }
}
