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
 * Carries everything the background worker needs to run an Abwägungstabelle export without an
 * HTTP request: the fully resolved export parameters, the acting user, and the session filter
 * hash list (the only request-scoped value the exporter reads that cannot be rebuilt from the DB).
 */
class ExportAssessmentTableMessage
{
    public function __construct(
        private readonly string $jobId,
        private readonly string $exportFormat,
        private readonly array $parameters,
        private readonly string $userId,
        private readonly string $procedureId,
        private readonly array $hashList = [],
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getExportFormat(): string
    {
        return $this->exportFormat;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getHashList(): array
    {
        return $this->hashList;
    }
}
