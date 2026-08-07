<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Message;

/**
 * Carries everything the background worker needs to run a procedure export (Gesamtabzug) without an
 * HTTP request: the selected procedure ids, whether the external procedure name should be used, and
 * the acting user.
 */
class ExportProcedureMessage
{
    /**
     * @param string[] $procedureIds
     */
    public function __construct(
        private readonly string $jobId,
        private readonly array $procedureIds,
        private readonly string $userId,
        private readonly bool $useExternalProcedureName = false,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * @return string[]
     */
    public function getProcedureIds(): array
    {
        return $this->procedureIds;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function useExternalProcedureName(): bool
    {
        return $this->useExternalProcedureName;
    }
}
