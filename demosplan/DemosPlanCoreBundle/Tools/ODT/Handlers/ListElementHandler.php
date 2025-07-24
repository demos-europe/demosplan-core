<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use DOMNode;

/**
 * Handler for ODT list elements - contains all list processing logic.
 *
 * This handler is a domain expert for list processing, containing all the
 * business logic for converting ODT lists to HTML, including continuation support.
 */
class ListElementHandler implements ODTElementHandler
{
    public function canHandle(string $elementName): bool
    {
        return in_array($elementName, $this->getSupportedElements(), true);
    }

    public function getSupportedElements(): array
    {
        return ['text:list', 'text:list-item'];
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function process(DOMNode $node, ProcessingContext $context): string
    {
        return match ($node->nodeName) {
            'text:list' => $this->processList($node, $context),
            'text:list-item' => $this->processListItem($node, $context),
            default => $context->processChildren($node),
        };
    }

    /**
     * Process list element - contains all list processing logic including continuation.
     */
    private function processList(DOMNode $node, ProcessingContext $context): string
    {
        if (!$node instanceof \DOMElement) {
            return $context->processChildren($node);
        }

        // Parse list attributes for continuation support
        $styleName = $node->getAttribute('text:style-name');
        $listType = $node->getAttribute('text:list-type');
        $continuesList = $node->getAttribute('text:continue-list');
        $listId = $node->getAttribute('xml:id') ?: uniqid('list_');

        // Determine if this is an ordered or unordered list using dynamic style detection
        $isOrdered = $this->getListType($styleName, $listType, $context);

        if ($isOrdered) {
            // Handle list continuation for ordered lists
            $startValue = $this->getListStartValue($listId, $continuesList, $context);
            $tag = 'ol';
            $attributes = $startValue > 1 ? ' start="' . $startValue . '"' : '';
        } else {
            // Unordered lists don't need continuation handling
            $tag = 'ul';
            $attributes = '';
        }

        // Process list content using context
        $content = $context->processChildren($node);

        // Update counters for continued lists
        if ($isOrdered) {
            $this->updateListCounters($listId, $content, $context);
        }

        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    }

    /**
     * Process list item element - handles paragraph wrapper removal.
     */
    private function processListItem(DOMNode $node, ProcessingContext $context): string
    {
        $content = $context->processChildren($node);

        // Remove paragraph wrappers from list items but preserve other block elements
        // Handle case where list item starts with a paragraph followed by other elements
        $content = preg_replace('/^<p>(.*?)<\/p>(.*)$/s', '$1$2', trim($content));
        // Also handle case where entire content is just a single paragraph
        $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($content));
        // Remove page breaks from list items as they're not meaningful in this context
        $content = str_replace('<hr class="page-break">', '', $content);

        return '<li>' . $content . '</li>';
    }

    /**
     * Determine if a list is ordered or unordered based on style analysis.
     */
    private function getListType(string $styleName, string $listType, ProcessingContext $context): bool
    {
        // First check if we have parsed style information from styles.xml
        if (!empty($styleName)) {
            $isOrdered = $context->getListStyle($styleName);
            if ($isOrdered !== null) {
                return $isOrdered;
            }
        }

        // Check for explicit list type attributes as fallback
        if ($listType === 'numbered' || $listType === 'ordered') {
            return true;
        }

        // Default to unordered - most lists should be bullet lists
        return false;
    }

    /**
     * Calculate the starting value for a list, handling continuation from previous lists.
     */
    private function getListStartValue(string $listId, string $continuesList, ProcessingContext $context): int
    {
        $listContinuation = $context->getListContinuation();
        $listCounters = $context->getListCounters();

        if (!empty($continuesList)) {
            // This list continues from another list
            $listContinuation[$listId] = $continuesList;
            $context->updateListContinuation($listContinuation);

            // Find the current count of the list we're continuing from
            $parentCount = $listCounters[$continuesList] ?? 0;
            $startValue = $parentCount + 1;

            // Initialize this list's counter to continue the sequence
            $listCounters[$listId] = $parentCount;
            $context->updateListCounters($listCounters);

            return $startValue;
        } else {
            // This is a new list sequence
            $listCounters[$listId] = 0;
            $context->updateListCounters($listCounters);
            return 1;
        }
    }

    /**
     * Update list counters based on the number of list items processed.
     */
    private function updateListCounters(string $listId, string $content, ProcessingContext $context): void
    {
        $listCounters = $context->getListCounters();
        $listContinuation = $context->getListContinuation();

        // Count the number of <li> elements in the processed content
        $itemCount = substr_count($content, '<li>');

        if ($itemCount > 0) {
            // Update the counter for this list
            $listCounters[$listId] = ($listCounters[$listId] ?? 0) + $itemCount;

            // If this list continues from another, also update the parent counter
            $continuesFrom = $listContinuation[$listId] ?? null;
            if ($continuesFrom !== null) {
                $listCounters[$continuesFrom] = $listCounters[$listId];
            }

            $context->updateListCounters($listCounters);
        }
    }
}
