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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\AdminProcedureResource;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminProcedureResourceType;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminProcedureStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProcedureRepository $procedureRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (AdminProcedureResource::class !== $resourceClass) {
            return null;
        }

        // Handle single item (GET /api/admin_procedure_resources/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        // Handle collection (GET /api/admin_procedure_resources)
        return $this->provideCollection($context);
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
        // Get all procedures and filter them using the voter
        $procedures = $this->procedureRepository->findAll();

        $adminProcedures = [];
        foreach ($procedures as $procedure) {
            $adminProcedures[] = $this->mapProcedureToAdminProcedureResource($procedure);
        }

        return $adminProcedures;
    }


    private function mapProcedureToAdminProcedureResource(Procedure $procedure): AdminProcedureResource
    {
        $adminProcedure = new AdminProcedureResource();

        $adminProcedure->id = $procedure->getId();
        $adminProcedure->name = $procedure->getName();
        $adminProcedure->externalName = $procedure->getExternalName();
        /*$adminProcedure->creationDate = $procedure->getCreatedDate();
        $adminProcedure->publicParticipation = $procedure->getPublicParticipation();

        // Phase-related dates
        if ($procedure->getPhase()) {
            $adminProcedure->internalStartDate = $procedure->getPhase()->getStartDate();
            $adminProcedure->internalEndDate = $procedure->getPhase()->getEndDate();
            $adminProcedure->internalPhaseIdentifier = $procedure->getPhase()->getKey();
        }

        if ($procedure->getPublicParticipationPhase()) {
            $adminProcedure->externalStartDate = $procedure->getPublicParticipationPhase()->getStartDate();
            $adminProcedure->externalEndDate = $procedure->getPublicParticipationPhase()->getEndDate();
            $adminProcedure->externalPhaseIdentifier = $procedure->getPublicParticipationPhase()->getKey();
        }

        // Statement counts (optimize performance in real implementation)
        $procedureId = $procedure->getId();
        $originalCounts = $this->procedureService->getOriginalStatementsCounts([$procedureId]);
        $statementCounts = $this->procedureService->getStatementsCounts([$procedureId]);

        $adminProcedure->originalStatementsCount = $originalCounts[$procedureId] ?? 0;
        $adminProcedure->statementsCount = $statementCounts[$procedureId] ?? 0;*/

        return $adminProcedure;
    }
}
