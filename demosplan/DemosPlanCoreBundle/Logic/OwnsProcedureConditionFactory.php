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
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\ConditionFactory\ConditionGroupFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * This class provides condition instances to check if a user is authorized for a procedure. The conditions can be
 * executed in the database, avoiding the necessity to fetch all instances and evaluate them individually.
 *
 * This class can be either used to create conditions based on a **given user, to evaluate arbitrary procedure
 * instances**, or to create conditions based on a **given procedure, to evaluate arbitrary user instance**. Logically
 * these two cases require comparisons of the same properties, but their implementation differs significantly. To
 * avoid divergence in their logic, the implementations of both cases are kept as close together as possible in this
 * class and its methods.
 *
 * If a {@link Procedure} instance is given in the constructor, then the returned conditions must only be used to
 * fetch/evaluate {@link User} instances. If a {@link User} instance is given in the constructor, then the returned
 * conditions must only be used to fetch/evaluate {@link Procedure} instances.
 */
class OwnsProcedureConditionFactory
{
    /**
     * @param User|Procedure $userOrProcedure the entity that was already fetched from the database to use its property values to build conditions
     */
    public function __construct(
        private readonly ConditionFactoryInterface&ConditionGroupFactoryInterface $conditionFactory,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly User|Procedure $userOrProcedure,
    ) {
    }

    /**
     * The organisation of the user must be set as planning office in the procedure.
     *
     * Planning agencies ("Planungsbüro") get the list of procedures they are
     * authorized for (enabled via field_procedure_adjustments_planning_agency).
     *
     * Will *not* check for the role of the user. Use {@link self::hasPlanningAgencyRole()} in conjunction with this method.
     *
     * @return FunctionInterface<bool>
     *
     * @throws PathException
     */
    public function isAuthorizedViaPlanningAgency(): FunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;
            $organisationId = $user->getOrganisationId();

            return $this->conditionFactory->propertyHasStringAsMember($organisationId, ['planningOffices']);
        }

        $procedure = $this->userOrProcedure;
        $procedurePlanningOffices = $procedure->getPlanningOfficesIds();

        return [] === $procedurePlanningOffices
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($procedurePlanningOffices, ['orga', 'id']);
    }

    /**
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `false`,
     * then the user must be in the organisation that created the procedure.
     *
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `true`,
     * then the user must be authorized manually for the procedure.
     *
     * The returned condition will not apply role checks by itself. Use in conjunction with
     * {@link self::hasProcedureAccessingRole}.
     *
     * @return FunctionInterface<bool>
     */
    public function isAuthorizedViaOrgaOrManually(): FunctionInterface
    {
        // T8427: allow access by manually configured users if the config is set to `true`,
        // overwriting the organisation-based access
        return $this->globalConfig->hasProcedureUserRestrictedAccess()
            ? $this->userIsExplicitlyAuthorized()
            : $this->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();
    }

    /**
     * Returns a condition to match users having the roles in the given customer to theoretically own a procedure.
     *
     * @return list<FunctionInterface<bool>>
     *
     * @throws PathException
     */
    public function hasProcedureAccessingRole(Customer $customer): array
    {
        $relevantRoles = [
            RoleInterface::CUSTOMER_MASTER_USER,
            ...User::PLANNING_AGENCY_ROLES,
            ...User::HEARING_AUTHORITY_ROLES,
        ];

        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;

            return $user->hasAnyOfRoles($relevantRoles, $customer)
                ? [$this->conditionFactory->true()]
                : [$this->conditionFactory->false()];
        }

        $procedure = $this->userOrProcedure;

        if (null !== $procedure->getOrgaId()) {
            $this->logger->debug('Permissions: Check whether orga owns procedure');
            $ownsOrgaRoleCondition = [
                $conditions[] = [] === $relevantRoles
                    ? $this->conditionFactory->false()
                    : $this->conditionFactory->propertyHasAnyOfValues($relevantRoles, ['roleInCustomers', 'role', 'code']),
                $this->isUserInCustomer($customer),
            ];
        } else {
            $ownsOrgaRoleCondition = [$this->conditionFactory->false()];
        }

        return $ownsOrgaRoleCondition;
    }

    /**
     * The user must have the {@link RoleInterface::PRIVATE_PLANNING_AGENCY} role.
     *
     * @return list<FunctionInterface<bool>>
     */
    public function hasPlanningAgencyRole(Customer $customer): array
    {
        $relevantRole = RoleInterface::PRIVATE_PLANNING_AGENCY;

        if ($this->userOrProcedure instanceof User) {
            return $this->userOrProcedure->hasRole($relevantRole, $customer)
                ? [$this->conditionFactory->true()]
                : [$this->conditionFactory->false()];
        }

        $procedure = $this->userOrProcedure;

        if (0 < count($procedure->getPlanningOfficesIds())) {
            $this->logger->debug('Procedure has PlanningOffices');

            // ist es ein PLanungsbüro?
            $planningAgencyOwnsProcedure = [
                $this->conditionFactory->propertyHasValue($relevantRole, ['roleInCustomers', 'role', 'code']),
                $this->isUserInCustomer($customer),
            ];
        } else {
            $planningAgencyOwnsProcedure = [$this->conditionFactory->false()];
        }

        return $planningAgencyOwnsProcedure;
    }

    /**
     * @return FunctionInterface<bool>
     */
    protected function isUserInCustomer(Customer $customer): FunctionInterface
    {
        $customerId = $customer->getId();
        Assert::notNull($customerId);

        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;

            return $user->isConnectedToCustomerId($customerId)
                ? $this->conditionFactory->true()
                : $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue($customer->getId(), ['roleInCustomers', 'customer', 'id']);
    }

    /**
     * @return ClauseFunctionInterface<bool>
     */
    public function isEitherTemplateOrProcedure(bool $template): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            return $this->conditionFactory->propertyHasValue($template, ['master']);
        }

        $procedure = $this->userOrProcedure;

        return $procedure->getMaster() === $template
            ? $this->conditionFactory->true()
            : $this->conditionFactory->false();
    }

    /**
     * @return ClauseFunctionInterface<bool>
     *
     * @throws PathException
     */
    public function userIsExplicitlyAuthorized(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;

            return $this->conditionFactory->propertyHasStringAsMember($user->getId(), ['authorizedUsers']);
        }

        $procedure = $this->userOrProcedure;

        $authorizedUserIds = $procedure->getAuthorizedUserIds();

        return [] === $authorizedUserIds
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($authorizedUserIds, ['id']);
    }

    /**
     * Users that are in the same organisation as the one of the user that created a procedure, own that procedure.
     *
     * @return ClauseFunctionInterface<bool>
     *
     * @throws PathException
     */
    public function userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;

            return $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['orga']);
        }

        $procedure = $this->userOrProcedure;

        return $this->conditionFactory->propertyHasValue($procedure->getOrgaId(), ['orga', 'id']);
    }

    /**
     * @return ClauseFunctionInterface<bool>
     *
     * @throws PathException
     */
    public function isNotDeletedProcedure(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            return $this->conditionFactory->propertyHasValue(false, ['deleted']);
        }

        $procedure = $this->userOrProcedure;

        return $procedure->isDeleted()
            ? $this->conditionFactory->false()
            : $this->conditionFactory->true();
    }
}
