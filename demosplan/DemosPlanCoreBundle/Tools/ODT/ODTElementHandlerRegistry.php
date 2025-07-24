<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

/**
 * Registry for ODT element handlers.
 *
 * Manages the collection of handlers and provides lookup functionality
 * to find the appropriate handler for a given ODT element type.
 */
class ODTElementHandlerRegistry
{
    /** @var array<string, ODTElementHandler[]> */
    private array $handlers = [];

    /** @var array<string, ODTElementHandler> */
    private array $handlerCache = [];

    /**
     * Register a handler for ODT elements.
     */
    public function addHandler(ODTElementHandler $handler): void
    {
        foreach ($handler->getSupportedElements() as $elementName) {
            if (!isset($this->handlers[$elementName])) {
                $this->handlers[$elementName] = [];
            }

            $this->handlers[$elementName][] = $handler;

            // Sort by priority (highest first)
            usort($this->handlers[$elementName], function (ODTElementHandler $a, ODTElementHandler $b) {
                return $b->getPriority() <=> $a->getPriority();
            });

            // Clear cache for this element
            unset($this->handlerCache[$elementName]);
        }
    }

    /**
     * Get the best handler for the given ODT element name.
     */
    public function getHandler(string $elementName): ?ODTElementHandler
    {
        // Check cache first
        if (isset($this->handlerCache[$elementName])) {
            return $this->handlerCache[$elementName];
        }

        // Find the highest priority handler that can handle this element
        if (isset($this->handlers[$elementName])) {
            foreach ($this->handlers[$elementName] as $handler) {
                if ($handler->canHandle($elementName)) {
                    $this->handlerCache[$elementName] = $handler;
                    return $handler;
                }
            }
        }

        // No handler found
        $this->handlerCache[$elementName] = null;
        return null;
    }

    /**
     * Check if a handler exists for the given element name.
     */
    public function hasHandler(string $elementName): bool
    {
        return $this->getHandler($elementName) !== null;
    }

    /**
     * Get all registered handlers.
     *
     * @return ODTElementHandler[]
     */
    public function getAllHandlers(): array
    {
        $allHandlers = [];
        foreach ($this->handlers as $elementHandlers) {
            foreach ($elementHandlers as $handler) {
                if (!in_array($handler, $allHandlers, true)) {
                    $allHandlers[] = $handler;
                }
            }
        }
        return $allHandlers;
    }

    /**
     * Get statistics about registered handlers.
     */
    public function getStats(): array
    {
        $elementCount = count($this->handlers);
        $handlerCount = count($this->getAllHandlers());

        return [
            'supported_elements' => $elementCount,
            'total_handlers' => $handlerCount,
            'elements' => array_keys($this->handlers),
        ];
    }

    /**
     * Clear all handlers and cache.
     */
    public function clear(): void
    {
        $this->handlers = [];
        $this->handlerCache = [];
    }
}
