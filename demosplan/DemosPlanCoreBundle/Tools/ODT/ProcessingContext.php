<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

use DOMNode;

/**
 * Context class that provides processing utilities to ODT element handlers.
 *
 * This class encapsulates the dependencies and state that handlers need
 * to process ODT elements, avoiding the need for handlers to directly
 * access the main OdtImporter class.
 */
class ProcessingContext
{
    private array $styleMap;
    private array $listStyleMap;
    private array $headingStyleMap;
    private array $listContinuation;
    private array $listCounters;
    private string $odtFilePath;
    private ODTElementHandlerRegistry $handlerRegistry;

    public function __construct(
        array $styleMap,
        array $listStyleMap,
        array $headingStyleMap,
        array $listContinuation,
        array $listCounters,
        string $odtFilePath,
        ODTElementHandlerRegistry $handlerRegistry
    ) {
        $this->styleMap = $styleMap;
        $this->listStyleMap = $listStyleMap;
        $this->headingStyleMap = $headingStyleMap;
        $this->listContinuation = $listContinuation;
        $this->listCounters = $listCounters;
        $this->odtFilePath = $odtFilePath;
        $this->handlerRegistry = $handlerRegistry;
    }

    /**
     * Process child nodes of the given node using the appropriate handlers.
     */
    public function processChildren(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $handler = $this->handlerRegistry->getHandler($child->nodeName);
                if ($handler) {
                    $html .= $handler->process($child, $this);
                } else {
                    // Fallback: process children recursively
                    $html .= $this->processChildren($child);
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->nodeValue);
            }
        }

        return $html;
    }

    /**
     * Get the style mapping for the given style name.
     */
    public function getStyle(string $styleName): array
    {
        return $this->styleMap[$styleName] ?? [];
    }

    /**
     * Get the list style information.
     */
    public function getListStyle(string $styleName): ?bool
    {
        return $this->listStyleMap[$styleName] ?? null;
    }

    /**
     * Get heading style information.
     */
    public function getHeadingStyle(string $styleName): array
    {
        return $this->headingStyleMap[$styleName] ?? [];
    }

    /**
     * Get the complete heading style map.
     */
    public function getHeadingStyleMap(): array
    {
        return $this->headingStyleMap;
    }

    /**
     * Get list continuation information.
     */
    public function getListContinuation(): array
    {
        return $this->listContinuation;
    }

    /**
     * Get list counters.
     */
    public function getListCounters(): array
    {
        return $this->listCounters;
    }

    /**
     * Update list counters.
     */
    public function updateListCounters(array $counters): void
    {
        $this->listCounters = $counters;
    }

    /**
     * Update list continuation information.
     */
    public function updateListContinuation(array $continuation): void
    {
        $this->listContinuation = $continuation;
    }

    /**
     * Get the ODT file path.
     */
    public function getOdtFilePath(): string
    {
        return $this->odtFilePath;
    }

    /**
     * Get access to the handler registry for nested processing.
     */
    public function getHandlerRegistry(): ODTElementHandlerRegistry
    {
        return $this->handlerRegistry;
    }

}
