<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProcessor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\AdminProcedureResource;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use http\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class AdminProcedureStateProcesor implements ProcessorInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
        private readonly ProcedureRepository $procedureRepository,
        #[Autowire(service: PersistProcessor::class)] private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        Assert::isInstanceOf($data, AdminProcedureResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access admin procedures');
        }

        if ($operation instanceof Patch && $this->isUpdateAllowed()) {
            $procedure = $this->mapProcedureToAdminProcedureResource($data);
            $this->persistProcessor->process($procedure, $operation, $uriVariables, $context);
            $data->id = $procedure->getId();

            return $data;
        }

        return null;
    }

    private function mapProcedureToAdminProcedureResource(AdminProcedureResource $adminProcedureResource): Procedure
    {
        if (!$adminProcedureResource->id) {
            throw new InvalidArgumentException('No procedure ID provided');
        }

        $accessConditions = $this->getAccessConditions();
        $procedure = $this->procedureRepository->getEntityByIdentifier($adminProcedureResource->id, $accessConditions, ['id']);
        if (!$procedure) {
            throw new EntityIdNotFoundException(sprintf('Procedure %d not found', $adminProcedureResource->id));
        }

        $procedure->setName($adminProcedureResource->name);
        $procedure->setExternalName($adminProcedureResource->externalName);

        return $procedure;
    }

    public function isUpdateAllowed(): bool
    {
        return true;
    }

    private function getAccessConditions(): array
    {
        return $this->procedureService->getAdminProcedureConditions(
            false,
            $this->currentUser->getUser()
        );
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }
}
