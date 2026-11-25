<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

/**
 * An OCX entry that cannot be applied, with the `reason` reported to the sender. The detail goes to
 * the sender as well and must not contain statement content.
 */
class OcxEntryFailedException extends RuntimeException
{
    public const INVALID_ENTRY = 'invalid_entry';
    public const ATTACHMENT_MISSING = 'attachment_missing';

    private function __construct(public readonly string $reason, string $detail)
    {
        parent::__construct($detail);
    }

    /**
     * @param list<string> $fields
     */
    public static function invalidEntry(array $fields): self
    {
        return new self(self::INVALID_ENTRY, 'Rejected by the receiver: '.implode(', ', $fields).'.');
    }

    public static function attachmentMissing(string $attachmentId): self
    {
        return new self(self::ATTACHMENT_MISSING, "Attachment {$attachmentId} is not available on the receiver.");
    }
}
