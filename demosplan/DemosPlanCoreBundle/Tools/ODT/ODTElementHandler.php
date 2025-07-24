<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

use DOMNode;

/**
 * Interface for handling specific ODT element types during conversion to HTML.
 *
 * Each handler is responsible for converting one or more ODT element types
 * to their corresponding HTML representation.
 */
interface ODTElementHandler
{
    /**
     * Determines if this handler can process the given ODT element.
     *
     * @param string $elementName The ODT element name (e.g., 'text:a', 'table:table')
     * @return bool True if this handler can process the element
     */
    public function canHandle(string $elementName): bool;

    /**
     * Process the ODT element and return its HTML representation.
     *
     * @param DOMNode $node The ODT element node to process
     * @param ProcessingContext $context Context providing access to processing utilities
     * @return string The HTML representation of the element
     */
    public function process(DOMNode $node, ProcessingContext $context): string;

    /**
     * Get the priority of this handler when multiple handlers can handle the same element.
     * Higher numbers have higher priority.
     *
     * @return int Priority value (higher = higher priority)
     */
    public function getPriority(): int;

    /**
     * Get a list of ODT element names this handler supports.
     *     * @return array<string> Array of ODT element names
     */
    public function getSupportedElements(): array;
}
