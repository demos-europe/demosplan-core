<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

/**
 * What the processing of one delivery has collected so far.
 */
final class OcxDeliveryRun
{
    /** @var list<OcxEntryResult> */
    public array $results = [];

    /** @var list<string> files a created statement took over */
    public array $usedFileIds = [];

    /** @var array<string, true> */
    private array $seenSenderIds = [];

    /** @var array<string, true> */
    private array $seenTargetIds = [];

    /**
     * @param list<string>                                         $permittedKinds
     * @param array<string, array{sha256: string, fileId: string}> $receivedAttachments
     */
    public function __construct(
        public readonly array $permittedKinds,
        public readonly array $receivedAttachments,
    ) {
    }

    public function markSenderSeen(string $senderId): bool
    {
        return $this->markSeen($this->seenSenderIds, $senderId);
    }

    public function markTargetSeen(string $targetId): bool
    {
        return $this->markSeen($this->seenTargetIds, $targetId);
    }

    /**
     * @param array<string, true> $seen
     *
     * @return bool false if the id was seen before
     */
    private function markSeen(array &$seen, string $id): bool
    {
        if (isset($seen[$id])) {
            return false;
        }
        $seen[$id] = true;

        return true;
    }
}
