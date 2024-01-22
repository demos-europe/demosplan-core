<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\ConditionFactory\ConditionGroupFactoryInterface;
use EDT\Querying\Contracts\FunctionInterface;
use Psr\Log\LoggerInterface;

class OwnsProcedureConditionFactory
{
    public function __construct(
        private readonly ConditionFactoryInterface&ConditionGroupFactoryInterface $conditionFactory,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly Procedure $procedure
    ) {
    }

    /**
     * The organisation of the user must be set as planning office in the procedure.
     *
     * @return FunctionInterface<bool>
     */
    public function isAuthorizedViaPlanningAgency(): FunctionInterface
    {
        $procedurePlanningOffices = $this->procedure->getPlanningOfficesIds();

        return $this->conditionFactory->propertyHasAnyOfValues(
            $procedurePlanningOffices,
            ['orga', 'id']
        );
    }

    /**
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `false`
     * then the user must be in the organisation that created the procedure.
     *
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `true`
     * then the user must either be authorized {@link OwnsProcedureConditionFactory::isAuthorizedViaPlanningAgency()
     * via their planning agency} or manually **regardless of their role**.
     *
     * @return FunctionInterface<bool>
     */
    public function isAuthorizedViaOrgaOrManually(): FunctionInterface
    {
        $orgaOwnsProcedure = $this->conditionFactory->propertyHasValue(
            $this->procedure->getOrgaId(),
            ['orga', 'id']
        );

        // T8427: allow access by manually configured users, overwriting the organisation-based access
        if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
            $planningAgencyIsAuthorized = $this->isAuthorizedViaPlanningAgency();
            $userIsAuthorized = $this->conditionFactory->propertyHasAnyOfValues(
                $this->procedure->getAuthorizedUserIds(),
                ['id']
            );

            $orgaOwnsProcedure = $this->conditionFactory->anyConditionApplies(
                $userIsAuthorized,
                $planningAgencyIsAuthorized
            );
        }

        return $orgaOwnsProcedure;
    }

    /**
     * Returns a condition to match users having the roles to theoretically own a procedure.
     *
     * @return FunctionInterface<bool>
     */
    public function hasProcedureAccessingRole(): FunctionInterface
    {
        $ownsOrgaRoleCondition = $this->conditionFactory->false();

        if (null !== $this->procedure->getOrgaId()) {
            $this->logger->debug('Permissions: Check whether orga owns procedure');
            // Fachplaner-Admin GLAUTH Kommune oder Fachplaner SB

            // Fachplaner admin oder Fachplaner Sachbearbeiter oder Plattform-Admin oder AHB-Admin

            $ownsOrgaRoleCondition = $this->conditionFactory->propertyHasAnyOfValues(
                [
                    Role::CUSTOMER_MASTER_USER,
                    Role::PLANNING_AGENCY_ADMIN,
                    Role::PLANNING_AGENCY_WORKER,
                    Role::HEARING_AUTHORITY_ADMIN,
                    Role::HEARING_AUTHORITY_WORKER,
                ],
                ['roleInCustomers', 'role', 'code']
            );
        }

        return $ownsOrgaRoleCondition;
    }

    /**
     * The user must have the {@link Role::PRIVATE_PLANNING_AGENCY} role.
     *
     * @return FunctionInterface<bool>
     */
    public function hasPlanningAgencyRole(): FunctionInterface
    {
        $planningAgencyOwnsProcedure = $this->conditionFactory->false();

        if (0 < count($this->procedure->getPlanningOfficesIds())) {
            $this->logger->debug('Procedure has PlanningOffices');

            // ist es ein PLanungsbüro?
            $planningAgencyOwnsProcedure = $this->conditionFactory->propertyHasValue(
                Role::PRIVATE_PLANNING_AGENCY,
                ['roleInCustomers', 'role', 'code']
            );
        }

        return $planningAgencyOwnsProcedure;
    }

    /**
     * @return FunctionInterface<bool>
     */
    public function isInCustomer(Customer $customer): FunctionInterface
    {
        return $this->conditionFactory->propertyHasValue(
            $customer->getId(),
            ['roleInCustomers', 'customer', 'id']
        );
    }
}
