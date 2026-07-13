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

    /**
     * True if every segment ended up wrapped in a <segment-mark> in textualReference, i.e. the
     * legacy draft was fully converted to the new format. A migrator leaves a segment unwrapped
     * when its text no longer matches textualReference verbatim, or its positions are missing /
     * out of bounds / overlapping. A partially converted draft is treated as a conversion failure
     * by callers, which then drop it rather than render a document the segment editor can't sync
     * with. Each wrapped segment contributes exactly one `<segment-mark data-segment-id=` opener,
     * and textualReference is guaranteed mark-free before migration, so counting openers against
     * the segment count is exact.
     */
    public function allSegmentsWrapped(array $data): bool
    {
        $segments = $data['data']['attributes']['segments'] ?? [];
        $textualReference = $data['data']['attributes']['textualReference'] ?? '';

        return count($segments) === substr_count($textualReference, '<segment-mark data-segment-id=');
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
