<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\AdminProcedureResource;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use EDT\JsonApi\RequestHandling\FilterParserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class AdminProcedureStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
        private readonly ProcedureRepository $procedureRepository,
        protected readonly FilterParserInterface $filterParser,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), AdminProcedureResource::class);

        // Explicit permission check - throw exception if not granted
        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access admin procedures');
        }

        // Handle collection (GET /api/admin_procedure_resources)
        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($context);
        }

        // Handle single item (GET /api/admin_procedure_resources/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?AdminProcedureResource
    {
        $procedure = $this->procedureRepository->find($id);

        if (!$procedure) {
            return null;
        }

        return $this->mapProcedureToAdminProcedureResource($procedure);
    }

    private function provideCollection(array $context = []): array
    {

        $filterParam = $context['filters'];
        if ($filterParam) {
            $filterConditions = $this->getConditions($filterParam);
            $procedures = $this->procedureRepository->getEntities($filterConditions, []);
        } else {
            // Get all procedures and filter them using the voter
            $accessConditions = $this->getAccessConditions();
            $procedures = $this->procedureRepository->getEntities($accessConditions, []);
        }

        $adminProcedures = [];
        foreach ($procedures as $procedure) {
            $adminProcedures[] = $this->mapProcedureToAdminProcedureResource($procedure);
        }

        return $adminProcedures;
    }

    private function getAccessConditions(): array
    {
        return $this->procedureService->getAdminProcedureConditions(
            false,
            $this->currentUser->getUser()
        );
    }

    private function mapProcedureToAdminProcedureResource(Procedure $procedure): AdminProcedureResource
    {
        $adminProcedure = new AdminProcedureResource();

        $adminProcedure->id = $procedure->getId();
        $adminProcedure->name = $procedure->getName();
        $adminProcedure->externalName = $procedure->getExternalName();

        return $adminProcedure;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }


    protected function getConditions($filterParam): array
    {
        if (!$filterParam) {
            return [];
        }

        $filterParam = $this->filterParser->validateFilter($filterParam);

        return $this->filterParser->parseFilter($filterParam);
    }
}
