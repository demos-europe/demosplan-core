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
 * Converts the oldest legacy draftsListJson variant to the new format expected by the segment
 * editor: segments carry only charStart/charEnd/charStartInit/charEndInit positions, with no
 * `text` snapshot at all, and textualReference has no <segment-mark> tags.
 *
 * charStartInit/charEndInit are preferred over charStart/charEnd for placement: on production
 * records, charStart/charEnd had drifted to Prosemirror document positions (unusable as raw
 * string offsets into textualReference), while charStartInit/charEndInit still matched the
 * stored textualReference verbatim. The two are never mixed field-by-field — either both Init
 * fields are present and used together, or both legacy fields are used together — since a hybrid
 * boundary would match neither variant.
 *
 * Segments whose positions are missing, out of bounds, or overlap the previous segment are left
 * unwrapped rather than guessed at — no character of textualReference is ever dropped, it just
 * isn't attributed to a segment mark.
 *
 * Handles only records whose segments have no `text` snapshot. Records with `text` are handled
 * by {@see DraftsListJsonMigrator}.
 *
 * Runs on-the-fly at read time — does not write back to the database.
 */
class DraftsListJsonPositionMigrator
{
    public function __construct(
        private readonly DraftsListJsonSegmentFields $segmentFields,
    ) {
    }

    public function needsMigration(array $data): bool
    {
        $segments = $data['data']['attributes']['segments'] ?? [];
        $textualReference = $data['data']['attributes']['textualReference'] ?? '';

        return !empty($segments)
            && array_key_exists('charStart', $segments[0])
            && !$this->segmentFields->anySegmentHasText($segments)
            && !str_contains($textualReference, '<segment-mark');
    }

    public function migrate(array $data): array
    {
        $segments = $data['data']['attributes']['segments'];
        $textualReference = $data['data']['attributes']['textualReference'];

        // Sort by the same resolved start used for placement below — sorting by a different
        // field (e.g. the raw charStart) than the one used to place segments can put a segment
        // in the wrong slot and cause it to be skipped as "overlapping" during wrapping. A
        // segment with no usable position (null start) sorts as 0; harmless, since it gets
        // skipped without advancing the cursor during wrapping regardless of its sort position.
        usort($segments, fn (array $a, array $b): int => ($this->resolvedRange($a)[0] ?? 0) - ($this->resolvedRange($b)[0] ?? 0));

        $data['data']['attributes']['textualReference'] = $this->wrapUsingPositions($segments, $textualReference);
        $data['data']['attributes']['segments'] = array_map(
            $this->segmentFields->stripPositionFields(...),
            $segments
        );

        return $data;
    }

    private function wrapUsingPositions(array $segments, string $textualReference): string
    {
        $length = mb_strlen($textualReference, 'UTF-8');
        $result = '';
        $cursor = 0;

        foreach ($segments as $segment) {
            [$start, $end] = $this->resolvedRange($segment);

            if (null === $start || null === $end || $start < $cursor || $end > $length || $start >= $end) {
                continue;
            }

            $result .= mb_substr($textualReference, $cursor, $start - $cursor, 'UTF-8');
            $text = mb_substr($textualReference, $start, $end - $start, 'UTF-8');
            $result .= sprintf('<segment-mark data-segment-id="%s">%s</segment-mark>', $segment['id'], $text);
            $cursor = $end;
        }

        $result .= mb_substr($textualReference, $cursor, null, 'UTF-8');

        return $result;
    }

    /**
     * Resolves a segment's [start, end] boundary, preferring the charStartInit/charEndInit pair
     * over charStart/charEnd. The two pairs are never mixed field-by-field — if either Init value
     * is missing or null, both legacy fields are used together instead, rather than combining one
     * Init value with one legacy value into a boundary that matches neither variant. Returns
     * [null, null] when neither pair is fully usable; callers must check for that.
     */
    private function resolvedRange(array $segment): array
    {
        $initStart = $segment['charStartInit'] ?? null;
        $initEnd = $segment['charEndInit'] ?? null;

        if (null !== $initStart && null !== $initEnd) {
            return [$initStart, $initEnd];
        }

        $legacyStart = $segment['charStart'] ?? null;
        $legacyEnd = $segment['charEnd'] ?? null;

        if (null !== $legacyStart && null !== $legacyEnd) {
            return [$legacyStart, $legacyEnd];
        }

        return [null, null];
    }
}
