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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;

readonly class ProcedurePhaseDefinitionService
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
