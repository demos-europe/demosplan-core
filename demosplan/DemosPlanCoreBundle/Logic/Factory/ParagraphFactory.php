<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Factory\ParagraphFactoryInterface;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;

class ParagraphFactory implements ParagraphFactoryInterface
{
    public function __construct(private readonly ParagraphRepository $repository)
    {
    }

    public function deleteParagraphs(ProcedureInterface $procedure, ElementsInterface $element): bool
    {
        return $this->repository->deleteByProcedureIdAndElementId($procedure->getId(), $element->getId());
    }
}
