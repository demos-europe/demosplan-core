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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ProcedurePhaseDefinitionServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;

readonly class ProcedurePhaseDefinitionService implements ProcedurePhaseDefinitionServiceInterface
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly ProcedurePhaseDefinitionRepository $procedurePhaseDefinitionRepository,
    ) {
    }

    /** @return ProcedurePhaseDefinition[] */
    public function getInternalPhaseDefinitionsForCurrentCustomer(): array
    {
        return array_values(array_filter(
            $this->findByCurrentCustomer(),
            static fn (ProcedurePhaseDefinition $d) => 'internal' === $d->getAudience()
        ));
    }

    /** @return ProcedurePhaseDefinition[] */
    public function getExternalPhaseDefinitionsForCurrentCustomer(): array
    {
        return array_values(array_filter(
            $this->findByCurrentCustomer(),
            static fn (ProcedurePhaseDefinition $d) => 'external' === $d->getAudience()
        ));
    }

    public function findById(string $id): ?ProcedurePhaseDefinition
    {
        return $this->procedurePhaseDefinitionRepository->find($id);
    }

    public function findByNameAndAudienceAndCustomer(string $name, string $audience, CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        return $this->procedurePhaseDefinitionRepository->findByNameAndAudienceAndCustomer($name, $audience, $customer);
    }

    public function findInitialDefinition(string $audience, ?CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        return $this->procedurePhaseDefinitionRepository->findInitialDefinition($audience, $customer);
    }

    public function findEvaluatingDefinition(string $audience, ?CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        return $this->procedurePhaseDefinitionRepository->findEvaluatingDefinition($audience, $customer);
    }

    /**
     * Returns available phases for a statement's audience, inserting the statement's current
     * phase at its natural position if it has been deleted and is not already in the list.
     *
     * @return ProcedurePhaseDefinition[]
     */
    public function getAvailablePhasesForStatement(Statement $statement): array
    {
        $phases = $statement->isSubmittedByCitizen()
            ? $this->getExternalPhaseDefinitionsForCurrentCustomer()
            : $this->getInternalPhaseDefinitionsForCurrentCustomer();

        $currentPhase = $statement->getPhaseDefinition();
        if ($currentPhase->isDeleted() && !in_array($currentPhase, $phases, true)) {
            $phases[] = $currentPhase;
            usort(
                $phases,
                static fn (ProcedurePhaseDefinition $a, ProcedurePhaseDefinition $b): int => $a->getOrderInAudience() <=> $b->getOrderInAudience()
            );
        }

        return $phases;
    }

    /** @return ProcedurePhaseDefinition[] */
    private function findByCurrentCustomer(): array
    {
        try {
            return $this->procedurePhaseDefinitionRepository->findByCustomerOrderedByAudience(
                $this->customerService->getCurrentCustomer()
            );
        } catch (CustomerNotFoundException) {
            return [];
        }
    }
}
