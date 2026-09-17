<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Export;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementsExportedEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class StatementsExportedEvent extends DPlanEvent implements StatementsExportedEventInterface
{
    /**
     * @param array<int, StatementInterface> $statements
     */
    public function __construct(
        protected ProcedureInterface $procedure,
        protected array $statements,
        protected string $format,
        protected bool $citizenDataCensored,
        protected bool $institutionDataCensored,
        protected bool $obscured,
    ) {
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function getStatements(): array
    {
        return $this->statements;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function isCitizenDataCensored(): bool
    {
        return $this->citizenDataCensored;
    }

    public function isInstitutionDataCensored(): bool
    {
        return $this->institutionDataCensored;
    }

    public function isObscured(): bool
    {
        return $this->obscured;
    }
}
