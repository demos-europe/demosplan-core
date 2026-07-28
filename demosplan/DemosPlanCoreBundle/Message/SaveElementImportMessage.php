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
 * Carries what the background worker needs to turn an already extracted Planunterlagen import into
 * documents: the job holding the extracted file tree, the target procedure, the acting user, and
 * the choices the user made on the review page (renamed titles, publish flag).
 *
 * The file tree itself is not carried here — it can hold tens of thousands of entries and lives on
 * the job row, which the handler loads by id.
 */
class SaveElementImportMessage
{
    /**
     * @param array<string,mixed> $requestPost the review form values, as submitted
     */
    public function __construct(
        private readonly string $jobId,
        private readonly string $procedureId,
        private readonly string $userId,
        private readonly array $requestPost,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @return array<string,mixed>
     */
    public function getRequestPost(): array
    {
        return $this->requestPost;
    }
}
