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
 * Converts old-format draftsListJson (segments with charStart/charEnd positions, no <segment-mark>
 * tags in textualReference) to the new format expected by the segment editor.
 *
 * Old format: segments carry charStart/charEnd Prosemirror positions; textualReference is plain HTML.
 * New format: textualReference contains <segment-mark data-segment-id="..."> wrappers; no positions.
 *
 * Runs on-the-fly at read time — does not write back to the database.
 */
class DraftsListJsonMigrator
{
    public function needsMigration(array $data): bool
    {
        $segments = $data['data']['attributes']['segments'] ?? [];
        $textualReference = $data['data']['attributes']['textualReference'] ?? '';

        return !empty($segments)
            && isset($segments[0]['charStart'])
            && !str_contains($textualReference, '<segment-mark');
    }

    public function migrate(array $data): array
    {
        $segments = $data['data']['attributes']['segments'];
        $textualReference = $data['data']['attributes']['textualReference'];

        // Process in document order so substr_replace offsets stay valid after each insertion.
        // charStart may be a Prosemirror position (not an HTML offset), but relative order is preserved.
        usort($segments, static fn (array $a, array $b): int => ($a['charStart'] ?? 0) - ($b['charStart'] ?? 0));

        $offset = 0;
        foreach ($segments as $segment) {
            $text = $segment['text'] ?? '';
            if ('' === $text) {
                continue;
            }

            $pos = strpos($textualReference, $text, $offset);
            if (false === $pos) {
                // Segment text not found verbatim — leave textualReference untouched for this segment
                // rather than risk corrupting the HTML.
                continue;
            }

            $marked = sprintf('<segment-mark data-segment-id="%s">%s</segment-mark>', $segment['id'], $text);
            $textualReference = substr_replace($textualReference, $marked, $pos, strlen($text));
            $offset = $pos + strlen($marked);
        }

        $data['data']['attributes']['textualReference'] = $textualReference;

        $data['data']['attributes']['segments'] = array_map(static function (array $segment): array {
            unset(
                $segment['charStart'],
                $segment['charEnd'],
                $segment['charStartInit'],
                $segment['charEndInit'],
                $segment['hasProsemirrorIndex']
            );

            return $segment;
        }, $segments);

        return $data;
    }
}
