<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\CurrentProcedureServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CurrentProcedureService implements CurrentProcedureServiceInterface
{
    /** @var Procedure|null */
    protected $procedure;

    /** @var array */
    protected $procedureArray = [];

    /**
     * @var ProcedureToLegacyConverter
     */
    private $procedureToLegacyConverter;

    public function __construct(ProcedureToLegacyConverter $procedureToLegacyConverter)
    {
        $this->procedureToLegacyConverter = $procedureToLegacyConverter;
    }

    /**
     * @return Procedure|null null if no valid procedure ID was given in the request
     */
    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    /**
     * @throws ProcedureNotFoundException
     */
    public function getProcedureWithCertainty(string $exceptionMessage = ''): Procedure
    {
        if (null === $this->procedure) {
            throw new ProcedureNotFoundException($exceptionMessage);
        }

        return $this->procedure;
    }

    /**
     * @throws AccessDeniedException thrown if no procedure ID could be determined,
     *                               which can (probably) be blamed on the request
     */
    public function getProcedureIdWithCertainty(): string
    {
        if (null === $this->procedure) {
            throw new AccessDeniedException('No valid procedure ID was send; REQUEST DENIED!');
        }

        return $this->procedure->getId();
    }

    public function setProcedure(Procedure $procedure): void
    {
        $this->procedure = $procedure;
    }

    public function getProcedureArray(): array
    {
        if ([] === $this->procedureArray) {
            $this->procedureArray = $this->procedureToLegacyConverter->convertToLegacy($this->procedure);
        }

        return $this->procedureArray;
    }
}
