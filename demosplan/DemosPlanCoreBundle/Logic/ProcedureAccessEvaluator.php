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
use Psr\Log\LoggerInterface;

class ProcedureAccessEvaluator
{
    public function __construct(private readonly DqlConditionFactory $conditionFactory, private readonly CustomerService $currentCustomerProvider, private readonly EntityFetcher $entityFetcher, private readonly GlobalConfigInterface $globalConfig, private readonly LoggerInterface $logger)
    {
    }

    /**
     * ```
     * "invited" werden Institutionen, wenn sie eingeladen werden. Es "ownt" das Verfahren prinzipiell die
     * Orga, die das Verfahren erstellt hat, eingeschränkt durch die explizit ausgewählten
     * Nutzer*innen, der Orga, die in den Grundeinstellungen ausgewählt werden. Werden
     * Planungsbüros dem Verfahren zugewiesen, ownen sie das Verfahren auch.
     * ```.
     *
     * **Keep in sync with {@link ProcedureAccessEvaluator::getOwnsProcedureCondition}.**
     */
    public function isOwningProcedure(User $user, Procedure $procedure): bool
    {
        // procedure is deleted
        if ($procedure->isDeleted()) {
            return false;
        }

        $ownsProcedureConditionFactory = new OwnsProcedureConditionFactory(
            $this->conditionFactory,
            $this->globalConfig,
            $this->logger,
            $procedure
        );

        $currentCustomer = $this->currentCustomerProvider->getCurrentCustomer();
        $inCurrentCustomer = $ownsProcedureConditionFactory->isInCustomer($currentCustomer);

        // user owns via their organisation or was manually set
        $orgaOwnsProcedure = $this->conditionFactory->false();
        $procedureAccessingRole = $ownsProcedureConditionFactory->hasProcedureAccessingRole();
        if ($this->entityFetcher->objectMatchesAll($user, [$procedureAccessingRole, $inCurrentCustomer])) {
            $this->logger->debug('User is FP*');
            $orgaOwnsProcedure = $ownsProcedureConditionFactory->isAuthorizedViaOrgaOrManually();
        }

        // user has planning agency role in current customer and owns via owning planning agency organisation
        $planningAgencyOwnsProcedure = $this->conditionFactory->false();
        $privatePlanningAgency = $ownsProcedureConditionFactory->hasPlanningAgencyRole();
        if ($this->entityFetcher->objectMatchesAll($user, [$privatePlanningAgency, $inCurrentCustomer])) {
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
     * Applies the same logic as {@link ProcedureAccessEvaluator::isOwningProcedure()}
     * but bundles it into a single condition that can be executed against {@link User} entities.
     * Because of this bundling this method is missing most of the logging
     * during the evaluation of a user.
     *
     * **Keep in sync with {@link ProcedureAccessEvaluator::isOwningProcedure()}.**
     *
     * @return FunctionInterface<bool>
     */
    public function getOwnsProcedureCondition(Procedure $procedure): FunctionInterface
    {
        // procedure is deleted
        if ($procedure->isDeleted()) {
            return $this->conditionFactory->false();
        }

        $ownsProcedureConditionFactory = new OwnsProcedureConditionFactory(
            $this->conditionFactory,
            $this->globalConfig,
            $this->logger,
            $procedure
        );

        $currentCustomer = $this->currentCustomerProvider->getCurrentCustomer();
        $inCurrentCustomer = $ownsProcedureConditionFactory->isInCustomer($currentCustomer);

        // user owns via their organisation or was manually set
        $orgaOwnsProcedure = $this->conditionFactory->allConditionsApply(
            $inCurrentCustomer,
            $ownsProcedureConditionFactory->hasProcedureAccessingRole(),
            $ownsProcedureConditionFactory->isAuthorizedViaOrgaOrManually()
        );

        // user has planning agency role in current customer and owns via owning planning agency organisation
        $planningAgencyOwnsProcedure = $this->conditionFactory->allConditionsApply(
            $inCurrentCustomer,
            $ownsProcedureConditionFactory->hasPlanningAgencyRole(),
            $ownsProcedureConditionFactory->isAuthorizedViaPlanningAgency()
        );

        // user owns via owning organisation or planning agency
        return $this->conditionFactory->anyConditionApplies(
            $orgaOwnsProcedure,
            $planningAgencyOwnsProcedure
        );
    }

    /**
     * @return array<int, string>
     */
    public function filterNonOwnedProcedureIds(User $user, Procedure ...$procedures): array
    {
        return collect($procedures)
            ->filter(fn(Procedure $procedure): bool => $this->isOwningProcedure($user, $procedure))
            ->map(static fn(Procedure $procedure): string => $procedure->getId())
            ->all();
    }

    /**
     * Check if the user has {@link Role::PROCEDURE_DATA_INPUT} set as role and its
     * organisation is set as {@link Procedure::$dataInputOrganisations}.
     */
    public function isAllowedAsDataInputOrga(User $user, ?Procedure $procedure): bool
    {
        return null !== $procedure
            && $user->hasRole(Role::PROCEDURE_DATA_INPUT)
            && in_array(
                $user->getOrganisationId(),
                $procedure->getDataInputOrgaIds(),
                true
            );
    }
}
