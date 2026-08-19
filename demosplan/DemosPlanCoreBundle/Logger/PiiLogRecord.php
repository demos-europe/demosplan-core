<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logger;

use DateTimeImmutable;

/**
 * Frozen DTO carrying everything {@see PiiLogWriter} needs to insert one row.
 *
 * Built by {@see PiiAwareLogger} from the PSR-3 call arguments + auto-resolved
 * context (current user, current procedure, request id, source context).
 */
final readonly class PiiLogRecord
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public int $level,
        public string $levelName,
        public string $channel,
        public string $message,
        public ?string $piiContextJson,
        public ?string $nonPiiContextJson,
        public string $contentHash,
        public ?string $requestId,
        public ?string $procedureId,
        public ?string $orgaId,
        public string $sourceContext,
    ) {
    }
}
