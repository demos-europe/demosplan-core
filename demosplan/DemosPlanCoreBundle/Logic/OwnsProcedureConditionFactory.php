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
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\ConditionFactory\ConditionGroupFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
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
        private readonly User|Procedure $userOrProcedure
    ) {
    }

    /**
     * Requires that all the following evaluates to `true`:
     * * call to {@link GlobalConfig::isProcedureAuthorizationViaCreatingOrgaEnabled}
     * * the accessing user has at least one of the following roles in the given customer: {@link Role::CUSTOMER_MASTER_USER}, {@link User::PLANNING_AGENCY_ROLES}, {@link User::HEARING_AUTHORITY_ROLES}
     * * the accessing user is in the same organisation as the user that created the procedure in question
     *
     * @return list<ClauseFunctionInterface<bool>> all conditions that must evaluate to `true`
     */
    public function isAuthorizedViaCreatingOrga(Customer $customer): array
    {
        if (!$this->globalConfig->isProcedureAuthorizationViaCreatingOrgaEnabled()) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure(),
            $this->procedureOrgaIdNotNull(),
            ...$this->hasProcedureAccessingRole($customer),
        ];
    }

    /**
     * Requires that all the following evaluates to `true`:
     * * call to {@link GlobalConfig::isProcedureAuthorizationViaExplicitUserListEnabled}
     * * the accessing user has at least one of the following roles in the given customer: {@link Role::CUSTOMER_MASTER_USER}, {@link User::PLANNING_AGENCY_ROLES}, {@link User::HEARING_AUTHORITY_ROLES}
     * * the accessing user is listed in {@link Procedure::$authorizedUsers} of the procedure in question
     *
     * @return list<ClauseFunctionInterface<bool>> all conditions that must evaluate to `true`
     */
    public function isAuthorizedViaExplicitUserList(Customer $customer): array
    {
        if (!$this->globalConfig->isProcedureAuthorizationViaExplicitUserListEnabled()) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->userIsExplicitlyAuthorized(),
            $this->procedureOrgaIdNotNull(),
            ...$this->hasProcedureAccessingRole($customer),
        ];
    }

    /**
     * Requires that all the following evaluates to `true`:
     * * the accessing user has the following role in the given customer: {@link RoleInterface::PRIVATE_PLANNING_AGENCY}
     * * the accessing user is in one of the organisations listed in {@link Procedure::$planningOffices} of the procedure in question
     *
     * @return list<ClauseFunctionInterface<bool>> all conditions that must evaluate to `true`
     */
    public function isAuthorizedViaPlanningAgencyStandardRole(Customer $currentCustomer): array
    {
        return [
            $this->isAuthorizedViaPlanningAgency(),
            ...$this->hasPlanningAgencyRole($currentCustomer),
        ];
    }

    /**
     * Requires that all the following evaluates to `true`:
     * * call to {@link GlobalConfig::isProcedureAuthorizationViaPlannerInExplicitPlanningAgencyListEnabled}
     * * the accessing user has at least one of the following roles in the given customer: {@link Role::CUSTOMER_MASTER_USER}, {@link User::PLANNING_AGENCY_ROLES}, {@link User::HEARING_AUTHORITY_ROLES}
     * * the accessing user is in one of the organisations listed in {@link Procedure::$planningOffices} of the procedure in question
     *
     * @return list<ClauseFunctionInterface<bool>> all conditions that must evaluate to `true`
     */
    public function isAuthorizedViaPlanningAgencyPlannerRole(Customer $customer): array
    {
        if (!$this->globalConfig->isProcedureAuthorizationViaPlannerInExplicitPlanningAgencyListEnabled()) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->isAuthorizedViaPlanningAgency(),
            $this->procedureOrgaIdNotNull(),
            ...$this->hasProcedureAccessingRole($customer),
        ];
    }

    /**
     * @return ClauseFunctionInterface<bool>
     */
    protected function procedureOrgaIdNotNull(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            return $this->conditionFactory->propertyIsNotNull(['orga', 'id']);
        }

        $procedure = $this->userOrProcedure;

        return null === $procedure->getOrgaId()
            ? $this->conditionFactory->false()
            : $this->conditionFactory->true();
    }

    /**
     * The organisation of the user must be set as planning office in the procedure.
     *
     * Private planning agencies ("Planungsbüro") get the list of procedures they are
     * authorized for (enabled via field_procedure_adjustments_planning_agency).
     *
     * Will *not* check for the role of the user. Use {@link self::hasPlanningAgencyRole()} in conjunction with this method.
     *
     * @return ClauseFunctionInterface<bool>
     */
    protected function isAuthorizedViaPlanningAgency(): ClauseFunctionInterface
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
     * Returns a condition to match users having the roles in the given customer to theoretically own a procedure if
     * not in a private planning agency.
     *
     * @return list<ClauseFunctionInterface<bool>>
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
                $this->conditionFactory->propertyHasAnyOfValues($relevantRoles, ['roleInCustomers', 'role', 'code']),
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
     * @return list<ClauseFunctionInterface<bool>>
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
     * @return ClauseFunctionInterface<bool>
     */
    protected function isUserInCustomer(Customer $customer): ClauseFunctionInterface
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
    protected function userIsExplicitlyAuthorized(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            $user = $this->userOrProcedure;

            return $this->conditionFactory->propertyHasStringAsMember($user->getId(), ['authorizedUsers']);
        }

        $procedure = $this->userOrProcedure;

        return $this->conditionFactory->propertyHasAnyOfValues($procedure->getAuthorizedUserIds(), ['id']);
    }

    /**
     * Users that are in the same organisation as the one of the user that created a procedure, own that procedure.
     *
     * @return ClauseFunctionInterface<bool>
     *
     * @throws PathException
     */
    protected function userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure(): ClauseFunctionInterface
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
