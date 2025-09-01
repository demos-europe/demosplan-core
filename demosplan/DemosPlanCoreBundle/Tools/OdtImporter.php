<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use DOMDocument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use ZipArchive;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandlerRegistry;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers\TextElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers\TableElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers\ListElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers\MediaElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers\SimpleElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTStyleParser;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTStyleParserInterface;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTHtmlProcessor;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTHtmlProcessorInterface;

class OdtImporter
{
    private string $odtFilePath;
    private array $styleMap = [];
    private array $listStyleMap = [];
    private array $headingStyleMap = [];
    private array $listContinuation = [];  // Maps list IDs to their continuation relationships
    private array $listCounters = [];      // Tracks current count for each list sequence
    private ?ODTElementHandlerRegistry $handlerRegistry = null;
    private ?ProcessingContext $processingContext = null;

    public function __construct(
        private readonly ODTStyleParserInterface $styleParser,
        private readonly ODTHtmlProcessorInterface $htmlProcessor,
        private readonly ?ZipArchive $zipArchive = null
    ) {
    }

    public function convert(string $odtFilePath): string
    {
        $zip = $this->zipArchive ?? new ZipArchive();
        if ($zip->open($odtFilePath) === true) {
            // save path as property to be used later
            $this->odtFilePath = $odtFilePath;
            $contentXml = $zip->getFromName('content.xml');
            $stylesXml = $zip->getFromName('styles.xml');
            //extract all pictures to a temporary folder
            $zip->extractTo(dirname($odtFilePath) . '/tmp');
            $zip->close();

            if ($contentXml !== false) {
                $html = $this->transformToHtml($contentXml, $stylesXml);
            } else {
                // Return basic HTML structure even when content.xml is missing
                $html = '<html><body></body></html>';
            }
            
            $fs = new Filesystem();
            $fs->remove(dirname($odtFilePath) . '/tmp');

            return $html;
        }

        throw new \Exception('Unable to open ODT file.');
    }

    private function transformToHtml(string $contentXml, ?string $stylesXml = null): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($contentXml);

        // Skip ODT structural elements (TOC, indexes) before processing
        $this->skipOdtStructuralElements($dom);

        // Parse styles first to understand formatting (including styles from styles.xml)
        $styleData = $this->styleParser->parseStyles($dom, $stylesXml);
        $this->styleMap = $styleData['styleMap'];
        $this->headingStyleMap = $styleData['headingStyleMap'];

        // Parse list styles from styles.xml to determine ordered vs unordered
        if ($stylesXml !== null && $stylesXml !== false) {
            $this->listStyleMap = $this->styleParser->parseListStyles($stylesXml);
        }

        // Initialize the handler registry and processing context
        $this->initializeHandlers();

        $html = '<html><body>';
        $html .= $this->processNodes($dom->documentElement);
        $html .= '</body></html>';

        // Clean up structural issues that may come from ODT
        $html = $this->htmlProcessor->cleanupStructuralIssues($html);

        return $html;
    }

    /**
     * Remove ODT structural elements (TOC, indexes) from the DOM before processing.
     * This ensures import starts from actual content rather than auto-generated sections.
     */
    private function skipOdtStructuralElements(DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        
        // ODT structural elements that should be removed
        $structuralSelectors = [
            '//text:table-of-content',
            '//text:illustration-index', 
            '//text:alphabetical-index',
            '//text:user-index',
            '//text:bibliography',
            '//text:table-index',
            '//text:object-index'
        ];
        
        // First, collect all structural elements to identify their boundaries
        $structuralNodes = [];
        foreach ($structuralSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $structuralNodes[] = $node;
            }
        }
        
        // Also find headings that immediately precede structural elements
        // These are usually index titles like "Abbildungsverzeichnis", "Verzeichnis der Themenkarten"
        $nodesToRemove = $structuralNodes;
        foreach ($structuralNodes as $structuralNode) {
            $precedingHeading = $this->findPrecedingIndexHeading($structuralNode, $xpath);
            if ($precedingHeading) {
                $nodesToRemove[] = $precedingHeading;
            }
        }
        
        // Remove all identified nodes
        foreach ($nodesToRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }
    
    /**
     * Find a heading that immediately precedes a structural index element.
     * These headings are typically index titles and should also be removed.
     */
    private function findPrecedingIndexHeading(\DOMNode $structuralNode, \DOMXPath $xpath): ?\DOMNode
    {
        // Look for h1-h6 elements that immediately precede this structural element
        $precedingSibling = $structuralNode->previousSibling;
        
        // Skip whitespace nodes
        while ($precedingSibling && $precedingSibling->nodeType === XML_TEXT_NODE && trim($precedingSibling->nodeValue) === '') {
            $precedingSibling = $precedingSibling->previousSibling;
        }
        
        // Check if the preceding sibling is a heading (text:h element)
        if ($precedingSibling && $precedingSibling->nodeType === XML_ELEMENT_NODE && $precedingSibling->nodeName === 'text:h') {
            // Additional check: only remove headings that are clearly index titles
            $headingText = trim($precedingSibling->textContent);
            $indexTerms = [
                'verzeichnis', 'index', 'abbildung', 'tabelle', 'literatur', 
                'inhalts', 'stichwort', 'abkürzung', 'glossar'
            ];
            
            foreach ($indexTerms as $term) {
                if (str_contains(strtolower($headingText), $term)) {
                    return $precedingSibling;
                }
            }
        }
        
        return null;
    }

    /**
     * Initialize the handler registry and processing context for ODT element processing.
     */
    private function initializeHandlers(): void
    {
        // Create the handler registry
        $this->handlerRegistry = new ODTElementHandlerRegistry();

        // Register all handlers
        $this->handlerRegistry->addHandler(new TextElementHandler());
        $this->handlerRegistry->addHandler(new TableElementHandler());
        $this->handlerRegistry->addHandler(new ListElementHandler());
        $this->handlerRegistry->addHandler(new MediaElementHandler());
        $this->handlerRegistry->addHandler(new SimpleElementHandler());

        // Create the processing context
        $this->processingContext = new ProcessingContext(
            $this->styleMap,
            $this->listStyleMap,
            $this->headingStyleMap,
            $this->listContinuation,
            $this->listCounters,
            $this->odtFilePath,
            $this->handlerRegistry
        );

    }

    private function processNodes(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                // Try to find a handler for this element
                $handler = $this->handlerRegistry->getHandler($child->nodeName);
                if ($handler) {
                    $html .= $handler->process($child, $this->processingContext);
                } else {
                    // Fallback: process children recursively (same as default case)
                    $html .= $this->processNodes($child);
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->nodeValue);
            }
        }

        return $html;
    }

























    /**
     * Import ODT file and convert to paragraph structure.
     * This method provides a consistent interface matching DocxImporter.
     */
    public function importOdt(File $file, string $elementId, string $procedure, string $category): array
    {
        // Convert ODT to HTML (existing functionality)
        $html = $this->convert($file->getRealPath());

        // Convert HTML to paragraph structure (new functionality)
        $paragraphs = $this->htmlProcessor->convertHtmlToParagraphStructure($html);

        // Return same structure as DocxImporter
        return [
            'procedure' => $procedure,
            'category' => $category,
            'elementId' => $elementId,
            'path' => $file->getRealPath(),
            'paragraphs' => $paragraphs,
        ];
    }




























}
