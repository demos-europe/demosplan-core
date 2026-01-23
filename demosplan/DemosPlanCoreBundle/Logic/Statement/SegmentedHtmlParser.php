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
 * Parses HTML with segment and text section markers into structured data.
 * Supports both <segment-mark> tags and legacy data-attribute format.
 */
class SegmentedHtmlParser
{
    /**
     * Parse HTML with <segment-mark> tags or data-segment-order attributes.
     *
     * @param string $html The HTML content to parse
     *
     * @return array Array of parsed segments and text sections, sorted by order
     */
    public function parse(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        // Wrap in a container to ensure proper parsing
        $wrappedHtml = '<div>'.$html.'</div>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $results = [];

        // Find all <segment-mark> elements (NEW format)
        $segmentMarkNodes = $xpath->query('//segment-mark');
        foreach ($segmentMarkNodes as $node) {
            if ($node instanceof DOMElement) {
                $order = (int) ($node->getAttribute('data-order') ?: $node->getAttribute('order') ?: 0);
                $textRaw = $this->getInnerHtml($node, $dom);
                $text = trim(strip_tags($textRaw));

                $results[] = [
                    'order'   => $order,
                    'type'    => 'segment',
                    'text'    => $text,
                    'textRaw' => $textRaw,
                ];
            }
        }

        // LEGACY: Find all elements with data-segment-order attribute
        if (empty($results)) {
            $segmentNodes = $xpath->query('//*[@data-segment-order]');
            foreach ($segmentNodes as $node) {
                if ($node instanceof DOMElement) {
                    $order = (int) $node->getAttribute('data-segment-order');
                    $textRaw = $this->getInnerHtml($node, $dom);
                    $text = trim(strip_tags($textRaw));

                    $results[] = [
                        'order'   => $order,
                        'type'    => 'segment',
                        'text'    => $text,
                        'textRaw' => $textRaw,
                    ];
                }
            }
        }

        // Find all elements with data-section-order attribute (text sections)
        $sectionNodes = $xpath->query('//*[@data-section-order]');
        foreach ($sectionNodes as $node) {
            if ($node instanceof DOMElement) {
                // Skip segment nodes (they have data-segment-id)
                if ($node->hasAttribute('data-segment-id')) {
                    continue;
                }

                $order = (int) $node->getAttribute('data-section-order');
                $textRaw = $this->getInnerHtml($node, $dom);
                $text = trim(strip_tags($textRaw));

                $results[] = [
                    'order'   => $order,
                    'type'    => 'textSection',
                    'text'    => $text,
                    'textRaw' => $textRaw,
                ];
            }
        }

        // Sort by order
        usort($results, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $results;
    }

    /**
     * Get the inner HTML of a DOM element (excluding the element's tags).
     *
     * @param DOMElement  $element The element to extract inner HTML from
     * @param DOMDocument $dom     The document object
     *
     * @return string The inner HTML content
     */
    private function getInnerHtml(DOMElement $element, DOMDocument $dom): string
    {
        $innerHTML = '';
        foreach ($element->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        return $innerHTML;
    }
}
