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
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathException;
use Psr\Log\LoggerInterface;

use function in_array;

class ProcedureAccessEvaluator
{
    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly CustomerService $currentCustomerProvider,
        private readonly EntityFetcher $entityFetcher,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * ```
     * "invited" werden Institutionen, wenn sie eingeladen werden. Es "ownt" das Verfahren prinzipiell die
     * Orga, die das Verfahren erstellt hat, eingeschränkt durch die explizit ausgewählten
     * Nutzer*innen, der Orga, die in den Grundeinstellungen ausgewählt werden. Werden
     * Planungsbüros dem Verfahren zugewiesen, ownen sie das Verfahren auch.
     * ```.
     *
     * This implementation of this method is duplicated from {@link ProcedureAccessEvaluator::getOwnsProcedureCondition()}. Its
     * (only) advantage is that it provides logging output, instead of executing all conditions at once, potentially
     * increasing the debugging difficulty.
     *
     * **Keep in sync with {@link ProcedureAccessEvaluator::getOwnsProcedureCondition}.**
     */
    public function isOwningProcedure(User $user, Procedure $procedure): bool
    {
        // guests can not own procedures
        if ($user->isGuestOnly()) {
            return false;
        }

        $ownsProcedureConditionFactory = new OwnsProcedureConditionFactory(
            $this->conditionFactory,
            $this->globalConfig,
            $this->logger,
            $procedure
        );

        // procedure must not be deleted
        $undeletedProcedureCondition = $ownsProcedureConditionFactory->isNotDeletedProcedure();
        if (!$this->entityFetcher->objectMatches($user, $undeletedProcedureCondition)) {
            return false;
        }

        // user must be in current customer
        $currentCustomer = $this->currentCustomerProvider->getCurrentCustomer();

        // user owns via their organisation or was manually set
        $orgaOwnsProcedure = $this->conditionFactory->false();
        $procedureAccessingRole = $ownsProcedureConditionFactory->hasProcedureAccessingRole($currentCustomer);
        if ($this->entityFetcher->objectMatchesAll($user, $procedureAccessingRole)) {
            $this->logger->debug('User is FP*');
            $orgaOwnsProcedure = $ownsProcedureConditionFactory->isAuthorizedViaOrgaOrManually();
        }

        // user has planning agency role in current customer and owns via owning planning agency organisation
        $planningAgencyOwnsProcedure = $this->conditionFactory->false();
        $privatePlanningAgency = $ownsProcedureConditionFactory->hasPlanningAgencyRole($currentCustomer);
        if ($this->entityFetcher->objectMatchesAll($user, $privatePlanningAgency)) {
            $this->logger->debug('Permissions → User has role RMOPPO');
            $planningAgencyOwnsProcedure = $ownsProcedureConditionFactory->isAuthorizedViaPlanningAgency();
        }

        // user owns via owning organisation or planning agency
        if ($this->entityFetcher->objectMatchesAny($user, [
            $orgaOwnsProcedure,
            $planningAgencyOwnsProcedure,
        ])) {
            $this->logger->debug('Permissions → Orga owns procedure');

            return true;
        }

        $this->logger->debug('Permissions → Orga does not own procedure');

        return false;
    }

    /**
     * Compiles a list of conditions to check if a procedure or procedure template is owned by the given user.
     *
     * A procedure is owned by a user if all conditions in the returned list match the procedure/user respectively.
     *
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `false`
     * then the user must be in the organisation that created the procedure.
     *
     * If {@link GlobalConfigInterface::hasProcedureUserRestrictedAccess} is set to `true`
     * then the user must either be authorized {@link OwnsProcedureConditionFactory::isAuthorizedViaPlanningAgency()
     * via their planning agency} or manually **regardless of their role**.
     *
     * Applies the same logic as {@link ProcedureAccessEvaluator::isOwningProcedure()}
     * but bundles it into a list of conditions that can be executed against {@link User} entities if a
     * {@link Procedure} was given and against {@link Procedure} entities if a {@link User} was given.
     * Because of this bundling this method is missing most of the logging
     * during the evaluation of a user.
     *
     * **Keep in sync with {@link ProcedureAccessEvaluator::isOwningProcedure()}**
     *
     * @return list<FunctionInterface<bool>>
     *
     * @throws PathException
     */
    public function getOwnsProcedureConditions(Procedure|User $userOrProcedure, bool $template): array
    {
        $ownsProcedureConditionFactory = new OwnsProcedureConditionFactory(
            $this->conditionFactory,
            $this->globalConfig,
            $this->logger,
            $userOrProcedure
        );

        $currentCustomer = $this->currentCustomerProvider->getCurrentCustomer();

        // user owns via their organisation or was manually set
        $orgaOwnsProcedure = $this->conditionFactory->allConditionsApply(
            $ownsProcedureConditionFactory->isAuthorizedViaOrgaOrManually(),
            ...$ownsProcedureConditionFactory->hasProcedureAccessingRole($currentCustomer),
        );

        // user has planning agency role in current customer and owns via owning planning agency organisation
        $planningAgencyOwnsProcedure = $this->conditionFactory->allConditionsApply(
            $ownsProcedureConditionFactory->isAuthorizedViaPlanningAgency(),
            ...$ownsProcedureConditionFactory->hasPlanningAgencyRole($currentCustomer),
        );

        return [
            $ownsProcedureConditionFactory->isEitherTemplateOrProcedure($template),
            $ownsProcedureConditionFactory->isNotDeletedProcedure(),
            // user owns via owning organisation or planning agency
            $this->conditionFactory->anyConditionApplies(
                $orgaOwnsProcedure,
                $planningAgencyOwnsProcedure
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function filterNonOwnedProcedureIds(User $user, Procedure ...$procedures): array
    {
        return collect($procedures)
            ->filter(fn (Procedure $procedure): bool => $this->isOwningProcedure($user, $procedure))
            ->map(static fn (Procedure $procedure): string => $procedure->getId())
            ->all();
    }

    /**
     * Check if the user has {@link Role::PROCEDURE_DATA_INPUT} set as role and its
     * organisation is set as {@link Procedure::$dataInputOrganisations}.
     */
    public function isAllowedAsDataInputOrga(User $user, ?Procedure $procedure): bool
    {
        return $procedure instanceof Procedure
            && $user->hasRole(Role::PROCEDURE_DATA_INPUT)
            && in_array(
                $user->getOrganisationId(),
                $procedure->getDataInputOrgaIds(),
                true
            );
    }
}
