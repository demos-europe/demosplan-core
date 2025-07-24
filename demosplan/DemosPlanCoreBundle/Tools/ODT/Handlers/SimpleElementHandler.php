<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use DOMNode;

/**
 * Handler for simple ODT elements with inline processing.
 *
 * Extracted from the original OdtImporter processNodes() switch statement.
 * Handles elements that have simple inline transformations.
 */
class SimpleElementHandler implements ODTElementHandler
{
    public function canHandle(string $elementName): bool
    {
        return in_array($elementName, $this->getSupportedElements(), true);
    }

    public function getSupportedElements(): array
    {
        return ['text:s', 'text:tab', 'text:soft-page-break'];
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function process(DOMNode $node, ProcessingContext $context): string
    {
        return match ($node->nodeName) {
            'text:s' => ' ', // Space element
            'text:tab' => ' ', // Convert tab to space for readability
            'text:soft-page-break' => '<hr class="page-break">',
            default => $context->processChildren($node),
        };
    }
}
