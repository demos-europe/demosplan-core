<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Export;

use DateTime;

/**
 * Shared shape of the job rows that track a background export, so the outcome of a job can be
 * recorded independently of which export produced it.
 */
interface AsyncExportJobInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function getId(): ?string;

    public function getStatus(): string;

    public function setStatus(string $status): void;

    public function getErrorMessage(): ?string;

    public function setErrorMessage(?string $errorMessage): void;

    public function getFileHash(): ?string;

    public function getFileName(): ?string;

    public function getModifiedDate(): DateTime;

    public function setModifiedDate(DateTime $modifiedDate): void;
}
