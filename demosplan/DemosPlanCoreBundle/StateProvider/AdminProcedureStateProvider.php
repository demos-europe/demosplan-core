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
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class AdminProcedureStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
        private readonly ProcedureRepository $procedureRepository,
        protected readonly DrupalFilterParser $filterParser,
        private readonly JsonApiSortingParser $sortingParser
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

        // Get all procedures and filter them using the voter
        $accessConditions = $this->getAccessConditions();
        $filterConditions = $this->getFilterConditions($context);
        $sortMethods = $this->getSortMethods($context);
        $conditions = array_merge($accessConditions, $filterConditions);
        $procedures = $this->procedureRepository->getEntities($conditions, $sortMethods);

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
        $adminProcedureResource = new AdminProcedureResource();

        $adminProcedureResource->id = $procedure->getId();
        $adminProcedureResource->name = $procedure->getName();
        $adminProcedureResource->externalName = $procedure->getExternalName();
        $adminProcedureResource->createdDate = $procedure->getCreatedDate();

        // Internal Phase Information
        $internalPhase = $procedure->getPhaseObject();
        $adminProcedureResource->internalStartDate = $internalPhase->getStartDate();
        $adminProcedureResource->internalEndDate = $internalPhase->getEndDate();
        $adminProcedureResource->internalPhaseIdentifier = $internalPhase->getKey();

        $procedureId = $procedure->getId();
        $originalCounts = $this->procedureService->getOriginalStatementsCounts([$procedureId]);
        $adminProcedureResource->originalStatementsCount = $originalCounts[$procedureId] ?? 0;

        $statementCounts = $this->procedureService->getStatementsCounts([$procedureId]);
        $adminProcedureResource->statementsCounts = $statementCounts[$procedureId] ?? 0;

        // Missing attributes - Phase Translation Keys
        // Note: This requires GlobalConfig to get phase translations
        // For now using the identifier as fallback
        $adminProcedureResource->internalPhaseTranslationKey = $internalPhase->getKey();

        // Missing attributes - External Phase Information
        $externalPhase = $procedure->getPublicParticipationPhaseObject();
        $adminProcedureResource->externalStartDate = $externalPhase->getStartDate();
        $adminProcedureResource->externalEndDate = $externalPhase->getEndDate();
        $adminProcedureResource->externalPhaseIdentifier = $externalPhase->getKey();

        // Missing attributes - External Phase Translation Key
        // Note: This requires GlobalConfig to get phase translations
        // For now using the identifier as fallback
        $adminProcedureResource->externalPhaseTranslationKey = $externalPhase->getKey();


        return $adminProcedureResource;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }


    protected function getFilterConditions(array $context = []): array
    {
        if (!$context) {
            return [];
        }

        if (!array_key_exists('request', $context)) {
            return [];
        }

        $filterParam = $context['request']->query->all('filter');
        //$filterParam = $this->filterParser->validateFilter($filterParam);

        return $this->filterParser->parseFilter($filterParam);
    }

    private function getSortMethods(array $context): array
    {
        if (!$context) {
            return [];
        }

        if (!array_key_exists('request', $context)) {
            return [];
        }

        $sortQueryParamValue = $context['request']->query->get('sort');

        return $this->sortingParser->createFromQueryParamValue($sortQueryParamValue);
    }
}
