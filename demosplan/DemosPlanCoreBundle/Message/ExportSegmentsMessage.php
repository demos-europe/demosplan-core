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
 * Carries everything the background worker needs to run a grouped segments export (DOCX/ODT or ZIP)
 * without an HTTP request: the query params the sync export route reads and the acting user
 * together with their customer and procedure.
 */
class ExportSegmentsMessage
{
    /**
     * @param array<string, mixed> $queryParams JSON:API filter/search/sort plus the export options
     *                                          (tableHeaders, censoring flags, tagsFilter, ...)
     */
    public function __construct(
        private readonly string $jobId,
        private readonly string $exportType,
        private readonly string $procedureId,
        private readonly string $userId,
        private readonly string $customerId,
        private readonly array $queryParams,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getExportType(): string
    {
        return $this->exportType;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }
}
