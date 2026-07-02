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

use DOMElement;
use DOMNode;
use Masterminds\HTML5;
use SplObjectStorage;

/**
 * Parses textualReference HTML containing <segment-mark> elements and
 * reconstructs the HTML of each segment.
 *
 * A single segment may be represented by multiple adjacent <segment-mark>
 * elements that share the same data-segment-id: the inline mark is closed and
 * reopened at every block boundary (paragraph, list item) and around inline
 * formatting (bold, italic). All marks of one segment are therefore merged
 * back into a single segment, preserving the surrounding ancestor structure
 * (e.g. <ul><li><p>…</p></li></ul>) so the stored text keeps the same rich-text
 * format used everywhere else for segment text.
 *
 * Reconstruction keeps, for each segment, the content inside its marks plus the
 * full ancestor chain up to the parsing container, drops everything else
 * (other segments' marks, unsegmented text), and unwraps the marks themselves.
 * Two marks under the same ancestor node collapse into it; two marks under
 * different ancestors keep both — which is exactly what tells "one paragraph
 * with a bold run" apart from "two paragraphs around a bold run".
 */
class SegmentMarkParser
{
    private const SEGMENT_MARK_TAG = 'segment-mark';
    private const SEGMENT_ID_ATTRIBUTE = 'data-segment-id';

    /**
     * Parse HTML with <segment-mark data-segment-id="..."> elements.
     *
     * @return array<int, array{segmentId: string, text: string}> One entry per
     *                                                            segment, ordered by document position
     */
    public function parse(string $html): array
    {
        if ('' === trim($html)) {
            return [];
        }

        $parser = new HTML5();
        $document = $parser->loadHTML('<html><body>'.$html.'</body></html>');
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return [];
        }

        $results = [];
        foreach ($this->collectSegmentIdsInDocumentOrder($body) as $segmentId) {
            $results[] = [
                'segmentId' => $segmentId,
                'text'      => $this->reconstructSegmentHtml($body, $segmentId),
            ];
        }

        return $results;
    }

    /**
     * Collects the distinct segment ids in the order their first mark appears.
     * Marks without an id are skipped.
     *
     * @return array<int, string>
     */
    private function collectSegmentIdsInDocumentOrder(DOMElement $body): array
    {
        $orderedIds = [];
        foreach ($this->collectSegmentMarks($body) as $mark) {
            $segmentId = $mark->getAttribute(self::SEGMENT_ID_ATTRIBUTE);
            if ('' !== $segmentId) {
                $orderedIds[$segmentId] = true;
            }
        }

        return array_keys($orderedIds);
    }

    /**
     * Rebuilds the HTML of a single segment from all its marks.
     */
    private function reconstructSegmentHtml(DOMElement $body, string $segmentId): string
    {
        /** @var DOMElement $container */
        $container = $body->cloneNode(true);

        $keptNodes = new SplObjectStorage();
        foreach ($this->collectSegmentMarks($container) as $mark) {
            if ($mark->getAttribute(self::SEGMENT_ID_ATTRIBUTE) !== $segmentId) {
                continue;
            }
            $this->keepNodeWithDescendants($mark, $keptNodes);
            $this->keepAncestors($mark, $container, $keptNodes);
        }

        $this->pruneToKeptNodes($container, $keptNodes);
        $this->unwrapSegmentMarks($container);

        return trim($this->serializeChildren($container));
    }

    /**
     * Returns all <segment-mark> descendants of the given node in document order.
     *
     * @return array<int, DOMElement>
     */
    private function collectSegmentMarks(DOMNode $node): array
    {
        $marks = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if (self::SEGMENT_MARK_TAG === $child->localName) {
                $marks[] = $child;
            }
            $marks = [...$marks, ...$this->collectSegmentMarks($child)];
        }

        return $marks;
    }

    private function keepNodeWithDescendants(DOMNode $node, SplObjectStorage $keptNodes): void
    {
        $keptNodes[$node] = true;
        foreach ($node->childNodes as $child) {
            $this->keepNodeWithDescendants($child, $keptNodes);
        }
    }

    private function keepAncestors(DOMNode $node, DOMNode $boundary, SplObjectStorage $keptNodes): void
    {
        $ancestor = $node->parentNode;
        while (null !== $ancestor && $ancestor !== $boundary) {
            $keptNodes[$ancestor] = true;
            $ancestor = $ancestor->parentNode;
        }
    }

    private function pruneToKeptNodes(DOMNode $node, SplObjectStorage $keptNodes): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (isset($keptNodes[$child])) {
                $this->pruneToKeptNodes($child, $keptNodes);
                continue;
            }
            $node->removeChild($child);
        }
    }

    /**
     * Replaces every remaining <segment-mark> with its child nodes, so the
     * marks no longer appear in the reconstructed HTML.
     */
    private function unwrapSegmentMarks(DOMNode $container): void
    {
        foreach ($this->collectSegmentMarks($container) as $mark) {
            $parent = $mark->parentNode;
            if (null === $parent) {
                continue;
            }
            while (null !== $mark->firstChild) {
                $parent->insertBefore($mark->firstChild, $mark);
            }
            $parent->removeChild($mark);
        }
    }

    private function serializeChildren(DOMNode $container): string
    {
        $document = $container->ownerDocument;
        if (null === $document) {
            return '';
        }

        $serialized = '';
        foreach ($container->childNodes as $child) {
            $serialized .= $document->saveHTML($child);
        }

        return $serialized;
    }
}
