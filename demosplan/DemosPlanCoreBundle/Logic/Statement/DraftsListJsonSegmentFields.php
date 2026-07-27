<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

/**
 * Shared helpers for the two legacy draftsListJson migrators ({@see DraftsListJsonMigrator} and
 * {@see DraftsListJsonPositionMigrator}), which must agree on format detection so a given array
 * of segments is routed to exactly one of them.
 */
class DraftsListJsonSegmentFields
{
    /**
     * True if any segment carries a verbatim `text` snapshot. Checking all segments (not just the
     * first) matters because a record could otherwise have its first segment's `text` explicitly
     * empty while later segments do carry a reliable snapshot.
     */
    public function anySegmentHasText(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ('' !== ($segment['text'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public function stripPositionFields(array $segment): array
    {
        unset(
            $segment['charStart'],
            $segment['charEnd'],
            $segment['charStartInit'],
            $segment['charEndInit'],
            $segment['hasProsemirrorIndex']
        );

        return $segment;
    }
}
