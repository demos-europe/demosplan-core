<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

use DOMNode;
use DOMXPath;

/**
 * Interface for ODT HTML processing operations.
 */
interface ODTHtmlProcessorInterface
{
    /**
     * Clean up structural issues that may come from ODT conversion.
     */
    public function cleanupStructuralIssues(string $html): string;

    /**
     * Convert ODT HTML output to paragraph structure using heading-first approach.
     */
    public function convertHtmlToParagraphStructure(string $html): array;

    /**
     * Find all headings in document order with their metadata.
     */
    public function getAllHeadingsInDocumentOrder(DOMXPath $xpath): array;

    /**
     * Check if a heading node is inside a table element.
     */
    public function isHeadingInsideTable(DOMNode $node): bool;

    /**
     * Get content before the first heading.
     */
    public function getContentBeforeFirstHeading(DOMXPath $xpath, array $firstHeading): string;

    /**
     * Get content between two headings.
     */
    public function getContentBetweenHeadings(array $currentHeading, ?array $nextHeading, DOMXPath $xpath): string;

    /**
     * Process current heading node.
     */
    public function processCurrentHeading(DOMNode $node, DOMNode $currentNode, string &$content): bool;

    /**
     * Check if processing should stop at next heading.
     */
    public function shouldStopAtNextHeading(DOMNode $node, ?DOMNode $nextNode, string &$content): bool;

    /**
     * Process content node.
     */
    public function processContentNode(DOMNode $node): string;

    /**
     * Check if a node contains a specific heading node.
     */
    public function nodeContainsHeading(DOMNode $container, DOMNode $headingNode): bool;

    /**
     * Get all content from document when no headings are found.
     */
    public function getAllContent(DOMXPath $xpath): string;

    /**
     * Create paragraph from content without heading.
     */
    public function createParagraphFromContent(string $content, int $nestingLevel): array;

    /**
     * Safely serialize a DOM node to HTML string.
     */
    public function serializeNode(DOMNode $node): string;

    /**
     * Extract content from a container node that appears before a specific heading.
     */
    public function extractContentBeforeHeading(DOMNode $container, DOMNode $headingNode): string;

    /**
     * Extract content from a container node that appears after a specific heading.
     */
    public function extractContentAfterHeading(DOMNode $container, DOMNode $headingNode): string;
}
