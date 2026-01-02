<?php

namespace demosplan\DemosPlanCoreBundle\StateProcessor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\AdminProcedureResource;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Exception;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class AdminProcedureStateProcesor implements ProcessorInterface
{

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
        private readonly ProcedureRepository $procedureRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        Assert::isInstanceOf($data, AdminProcedureResource::class);

        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access admin procedures');
        }

        if ($operation instanceof Patch && $this->isUpdateAllowed()) {
            $procedure = $this->mapProcedureToAdminProcedureResource($data);



        }

    }

    private function mapProcedureToAdminProcedureResource(AdminProcedureResource $adminProcedureResource): Procedure
    {
        if ($adminProcedureResource->id) {
            $accessConditions = $this->getAccessConditions();
            $procedure = $this->procedureRepository->getEntityByIdentifier($adminProcedureResource->id, $accessConditions, ['id']);
            if (!$procedure) {
                throw new EntityIdNotFoundException(sprintf('Procedure %d not found', $adminProcedureResource->id));
            }
        }

        $procedure->setName($adminProcedureResource->name);
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
