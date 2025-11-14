<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use demosplan\DemosPlanCoreBundle\Tools\ODT\OdtElementProcessor;
use demosplan\DemosPlanCoreBundle\Tools\ODT\OdtFileExtractor;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTHtmlProcessorInterface;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTStyleParserInterface;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Converts ODT files to HTML with clean separation of concerns:
 * - File operations handled by OdtFileExtractor
 * - Element processing handled by OdtElementProcessor
 * - Style parsing and HTML cleanup handled by existing services
 */
class OdtImporter
{
    public function __construct(
        private readonly ODTStyleParserInterface $styleParser,
        private readonly ODTHtmlProcessorInterface $htmlProcessor,
        private readonly OdtFileExtractor $fileExtractor,
        private readonly OdtElementProcessor $elementProcessor,
    ) {
    }

    /**
     * Convert ODT file to HTML.
     */
    public function convert(string $odtFilePath): string
    {
        // Extract file content
        $fileData = $this->fileExtractor->extractContent($odtFilePath);

        // Parse styles
        $styles = $this->styleParser->parseStyles(
            $this->createDomFromContent($fileData->contentXml),
            $fileData->stylesXml
        );

        // Process content
        $html = $this->processContent($fileData, $styles);

        // Clean up and return
        $this->fileExtractor->cleanup($fileData->tempDir);

        return $this->htmlProcessor->cleanupStructuralIssues($html);
    }

    /**
     * Create DOM document from content XML.
     */
    private function createDomFromContent(?string $contentXml): DOMDocument
    {
        $dom = new DOMDocument();

        if (null !== $contentXml) {
            $dom->loadXML($contentXml);
            $this->skipOdtStructuralElements($dom);
        }

        return $dom;
    }

    /**
     * Process ODT content using simplified element processor.
     */
    private function processContent($fileData, array $styles): string
    {
        if (!$fileData->hasContent()) {
            return '<html><body></body></html>';
        }

        $dom = $this->createDomFromContent($fileData->contentXml);

        // Initialize processor with parsed styles
        $listStyleMap = $fileData->hasStyles()
            ? $this->styleParser->parseListStyles($fileData->stylesXml)
            : [];

        $this->elementProcessor->initialize(
            $styles['styleMap'] ?? [],
            $listStyleMap,
            $styles['headingStyleMap'] ?? [],
            $fileData->tempDir
        );

        // Generate HTML
        $html = '<html><body>';
        $html .= $this->elementProcessor->processContent($dom);
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Remove ODT structural elements (TOC, indexes) from the DOM before processing.
     * This ensures import starts from actual content rather than auto-generated sections.
     */
    private function skipOdtStructuralElements(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        // ODT structural elements that should be removed
        $structuralSelectors = [
            '//text:table-of-content',
            '//text:illustration-index',
            '//text:alphabetical-index',
            '//text:user-index',
            '//text:bibliography',
            '//text:table-index',
            '//text:object-index',
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
            $precedingHeading = $this->findPrecedingIndexHeading($structuralNode);
            if ($precedingHeading instanceof DOMNode) {
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
    private function findPrecedingIndexHeading(DOMNode $structuralNode): ?DOMNode
    {
        // Look for h1-h6 elements that immediately precede this structural element
        $precedingSibling = $structuralNode->previousSibling;

        // Skip whitespace nodes
        while ($precedingSibling && XML_TEXT_NODE === $precedingSibling->nodeType && '' === trim((string) $precedingSibling->nodeValue)) {
            $precedingSibling = $precedingSibling->previousSibling;
        }

        // Check if the preceding sibling is a heading (text:h element)
        if ($precedingSibling && XML_ELEMENT_NODE === $precedingSibling->nodeType && 'text:h' === $precedingSibling->nodeName) {
            // Additional check: only remove headings that are clearly index titles
            $headingText = trim($precedingSibling->textContent);
            $indexTerms = [
                'verzeichnis', 'index', 'abbildung', 'tabelle', 'literatur',
                'inhalts', 'stichwort', 'abkürzung', 'glossar',
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
            'procedure'  => $procedure,
            'category'   => $category,
            'elementId'  => $elementId,
            'path'       => $file->getRealPath(),
            'paragraphs' => $paragraphs,
        ];
    }
}
