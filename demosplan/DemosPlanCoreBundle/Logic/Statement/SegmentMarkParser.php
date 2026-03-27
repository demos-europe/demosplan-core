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

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Parses textualReference HTML containing <segment-mark> elements
 * and extracts segment IDs with their text content.
 *
 * Each <segment-mark data-segment-id="uuid"> wraps the text of one segment.
 * The order of segments is determined by their position in the HTML document.
 */
class SegmentMarkParser
{
    /**
     * Parse HTML with <segment-mark data-segment-id="..."> elements.
     *
     * @return array<int, array{segmentId: string, text: string}> Ordered by document position
     */
    public function parse(string $html): array
    {
        if ('' === trim($html)) {
            return [];
        }

        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//segment-mark');
        $results = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $segmentId = $node->getAttribute('data-segment-id');
            if ('' === $segmentId) {
                continue;
            }

            $results[] = [
                'segmentId' => $segmentId,
                'text'      => trim(strip_tags($this->getInnerHtml($node, $dom))),
            ];
        }

        return $results;
    }

    private function getInnerHtml(DOMElement $element, DOMDocument $dom): string
    {
        $innerHTML = '';
        foreach ($element->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        return $innerHTML;
    }
}
