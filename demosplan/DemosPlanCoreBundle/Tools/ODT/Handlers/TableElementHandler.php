<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use DOMNode;

/**
 * Handler for ODT table elements - contains all table processing logic.
 *
 * This handler is a domain expert for table processing, containing all the
 * business logic for converting ODT table elements to HTML.
 */
class TableElementHandler implements ODTElementHandler
{
    public function canHandle(string $elementName): bool
    {
        return in_array($elementName, $this->getSupportedElements(), true);
    }

    public function getSupportedElements(): array
    {
        return [
            'table:table',
            'table:table-row',
            'table:table-header-rows',
            'table:table-column',
            'table:table-cell',
            'table:covered-table-cell'
        ];
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function process(DOMNode $node, ProcessingContext $context): string
    {
        return match ($node->nodeName) {
            'table:table' => '<table>' . $context->processChildren($node) . '</table>',
            'table:table-row' => '<tr>' . $context->processChildren($node) . '</tr>',
            'table:table-header-rows' => $context->processChildren($node), // Process table header rows - contains header cells that should be processed
            'table:table-column' => '', // Table column definitions - skip as they don't contain content
            'table:table-cell' => $this->processTableCell($node, $context),
            'table:covered-table-cell' => '', // Skip covered cells - they're handled by spanning
            default => $context->processChildren($node),
        };
    }

    /**
     * Process table cell element - contains all table cell logic.
     */
    private function processTableCell(DOMNode $node, ProcessingContext $context): string
    {
        if (!$node instanceof \DOMElement) {
            return $context->processChildren($node);
        }

        $attributes = '';

        // Handle column spanning
        $colspan = $node->getAttribute('table:number-columns-spanned');
        if ($colspan && $colspan > 1) {
            $attributes .= ' colspan="' . $colspan . '"';
        }

        // Handle row spanning
        $rowspan = $node->getAttribute('table:number-rows-spanned');
        if ($rowspan && $rowspan > 1) {
            $attributes .= ' rowspan="' . $rowspan . '"';
        }

        // Process child content using context
        $content = $context->processChildren($node);

        // Remove paragraph wrappers from table cells to match expected format
        $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($content));

        return '<td' . $attributes . ' >' . $content . '</td>';
    }
}
