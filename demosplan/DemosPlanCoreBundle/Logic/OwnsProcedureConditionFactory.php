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

            if (null === $organisationId) {
                return $this->conditionFactory->false();
            }

            /*
             * `propertyHasStringAsMember()` (DQL `MEMBER OF`) reads the `planningOffices`
             * to-many property with DIRECT access, which works when the condition is executed
             * as SQL but returns the raw, unresolved `PersistentCollection` when the condition
             * is evaluated in PHP against an already-fetched entity (e.g. EDT relationship
             * resolution), breaking the `in_array()` call in `OneOf::reduce()`.
             * `propertyHasAnyOfValues()` uses UNPACK access instead, which is resolved correctly
             * in both cases.
             */
            return $this->conditionFactory->propertyHasAnyOfValues([$organisationId], ['planningOffices', 'id']);
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
     * then the user must be authorized manually for the procedure AND must be in the
     * procedure's owning organization. The platform master blueprint is exempt from the
     * manual authorization part, see {@link self::isMasterTemplate()}.
     *
     * The returned condition will not apply role checks by itself. Use in conjunction with
     * {@link self::hasProcedureAccessingRole}.
     *
     * @return FunctionInterface<bool>
     */
    public function isAuthorizedViaOrgaOrManually(): FunctionInterface
    {
        if (!$this->globalConfig->hasProcedureUserRestrictedAccess()) {
            return $this->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure();
        }

        // When explicit authorization is enabled, require BOTH explicit authorization
        // AND owning organization match to prevent access by users who changed organizations.
        // The owning organization match is never waived, so no exemption below can grant
        // access across organizations.
        return $this->conditionFactory->allConditionsApply(
            $this->userOwnsProcedureViaOrgaOfUserThatCreatedTheProcedure(),
            $this->conditionFactory->anyConditionApplies(
                $this->isMasterTemplate(),
                $this->userIsExplicitlyAuthorized()
            )
        );
    }

    /**
     * Whether the procedure is the platform master blueprint ("Plattform-Blaupause"), the
     * per-installation singleton that all new procedures are copied from.
     *
     * This exists to exempt that blueprint from {@link self::userIsExplicitlyAuthorized()}
     * while explicit authorization is enabled. The master blueprint is seeded by migration
     * rather than created through the UI, so it never receives the creator row that
     * ProcedureService::setAuthorizedUsersToProcedure() writes for every other
     * procedure. Its authorized user list is therefore permanently empty, and
     * {@link self::userIsExplicitlyAuthorized()} resolves an empty list to a hard `false()` —
     * which would make the blueprint owned by nobody and its settings page unopenable, even
     * for the organisation that owns it. Note also that inheriting authorized users from a
     * master blueprint is explicitly refused when copying it (T15644 / T23583), which is
     * further evidence it was never meant to carry a per-user authorization list.
     *
     * Combined with the never-waived organisation match in
     * {@link self::isAuthorizedViaOrgaOrManually()} and the role check in
     * {@link self::hasProcedureAccessingRole()}, this grants the master blueprint to
     * FP-role users of the owning organisation — matching the intent of ADO #44507, which
     * made it visible to exactly that group but left it unopenable.
     *
     * @return ClauseFunctionInterface<bool>
     *
     * @throws PathException
     */
    public function isMasterTemplate(): ClauseFunctionInterface
    {
        if ($this->userOrProcedure instanceof User) {
            return $this->conditionFactory->propertyHasValue(true, ['masterTemplate']);
        }

        $procedure = $this->userOrProcedure;

        return $procedure->isMasterTemplate()
            ? $this->conditionFactory->true()
            : $this->conditionFactory->false();
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
            ...User::PLANNING_AGENCY_ROLES,
            ...User::HEARING_AUTHORITY_ROLES,
            ...User::CUSTOMER_MASTER_USER_ROLE,
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
            /*
             * Procedure::$master is persisted as an integer column but semantically boolean
             * (see ProcedureResourceType::getResourceTypeConditions()). SQL-executed conditions
             * work with either representation because MySQL loosely casts bool to int, but
             * conditions evaluated in PHP (e.g. EDT relationship resolution) use strict
             * comparison and require the int representation to match.
             */
            return $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->propertyHasValue($template, ['master']),
                $this->conditionFactory->propertyHasValue((int) $template, ['master']),
            );
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

            // see isAuthorizedViaPlanningAgency() for why UNPACK access via
            // propertyHasAnyOfValues() is used instead of propertyHasStringAsMember()
            return $this->conditionFactory->propertyHasAnyOfValues([$user->getId()], ['authorizedUsers', 'id']);
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
            $organisationId = $user->getOrganisationId();

            // User without organization cannot own any procedure
            if (null === $organisationId) {
                return $this->conditionFactory->false();
            }

            return $this->conditionFactory->propertyHasValue($organisationId, ['orga', 'id']);
        }

        $procedure = $this->userOrProcedure;
        $orgaId = $procedure->getOrgaId();

        // Procedure without organization cannot be owned
        if (null === $orgaId) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue($orgaId, ['orga', 'id']);
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
