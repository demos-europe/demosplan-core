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

use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;

class ProcedurePhaseDefinitionResolver
{
    /** @var array<string, string>|null id → name, null means not yet loaded */
    private ?array $idToNameMap = null;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly ProcedurePhaseDefinitionRepository $repository,
    ) {
    }

    public function getNameById(string $id): string
    {
        if (null === $this->idToNameMap) {
            $this->idToNameMap = [];
            try {
                $definitions = $this->repository->findByCustomerOrderedByAudience(
                    $this->customerService->getCurrentCustomer(),
                    false
                );
            } catch (CustomerNotFoundException) {
                $definitions = $this->repository->findBy(['customer' => null]);
            }
            foreach ($definitions as $definition) {
                if (null !== $definition->getId()) {
                    $this->idToNameMap[$definition->getId()] = $definition->getName();
                }
            }
        }

        return $this->idToNameMap[$id] ?? '';
    }
}
