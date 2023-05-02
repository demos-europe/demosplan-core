<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

interface ProjectPermissionsInterface
{
    /**
     * Define project-specific permissions which depend on a user but not a procedure.
     */
    public function projectGlobalPermissions(): void;

    /**
     * Define project-specific permissions which depend on a procedure.
     *
     * When enabling permissions for {@link Role::PLANNING_AGENCY_ADMIN},
     * {@link Role::PLANNING_AGENCY_WORKER} or {@link Role::PRIVATE_PLANNING_AGENCY}
     * almost always wrap it inside a {@link \DemosEurope\DemosplanAddon\Contracts\PermissionsInterface::ownsProcedure()}
     * condition check.
     *
     * <strong>Otherwise planners get access to foreign procedures!</strong>
     *
     * The only exception are permissions that are enabled for planners but not tied to
     * specific procedures. In those cases the check can be omitted.
     */
    public function projectProcedurePermissions(): void;
}
