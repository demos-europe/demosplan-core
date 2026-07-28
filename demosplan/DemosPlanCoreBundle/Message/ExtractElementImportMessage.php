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
 * Carries everything the background worker needs to unpack an uploaded Planunterlagen archive
 * without an HTTP request: the job to report progress on, the target procedure, the acting user
 * and the hash of the uploaded ZIP in file storage.
 *
 * Only primitives are carried — the message is serialised into the queue table, so entities would
 * either not survive the round trip or would be stale by the time the worker picks them up.
 */
class ExtractElementImportMessage
{
    public function __construct(
        private readonly string $jobId,
        private readonly string $procedureId,
        private readonly string $userId,
        private readonly string $fileHash,
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

    public function getFileHash(): string
    {
        return $this->fileHash;
    }
}
