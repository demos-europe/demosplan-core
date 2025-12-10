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

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Simplified ODT element processor.
 *
 * Contains all element processing logic in a single class with simple method mapping
 */
class OdtElementProcessor
{
    private array $styleMap = [];
    private array $listStyleMap = [];
    private array $headingStyleMap = [];
    private array $listContinuation = [];
    private array $listCounters = [];

    // Simple element mapping
    private const ELEMENT_HANDLERS = [
        'text:p'                   => 'processParagraph',
        'text:h'                   => 'processHeading',
        'text:span'                => 'processSpan',
        'text:note'                => 'processNote',
        'text:list'                => 'processList',
        'text:list-item'           => 'processListItem',
        'table:table'              => 'processTable',
        'table:table-row'          => 'processTableRow',
        'table:table-cell'         => 'processTableCell',
        'table:table-header-rows'  => 'processTableHeaderRows',
        'table:covered-table-cell' => 'processCoveredTableCell',
        'draw:frame'               => 'processFrame',
        'draw:image'               => 'processImage',
        'text:a'                   => 'processLink',
        'text:line-break'          => 'processLineBreak',
        'text:tab'                 => 'processTab',
        'text:s'                   => 'processSpace',
        'text:soft-page-break'     => 'processSoftPageBreak',
    ];

    private const ODT_STYLE_NAME_ATTRIBUTE = 'text:style-name';
    private const PARAGRAPH_WRAPPER_REGEX = '/^<p>(.*)<\/p>$/s';

    private string $tempDir;

    public function initialize(
        array $styleMap,
        array $listStyleMap,
        array $headingStyleMap,
        string $tempDir,
    ): void {
        $this->styleMap = $styleMap;
        $this->listStyleMap = $listStyleMap;
        $this->headingStyleMap = $headingStyleMap;
        $this->tempDir = $tempDir;
        $this->listContinuation = [];
        $this->listCounters = [];
    }

    /**
     * Process content starting from document root.
     */
    public function processContent(DOMDocument $dom): string
    {
        return $this->processChildren($dom->documentElement);
    }

    /**
     * Process child nodes - main processing logic.
     */
    public function processChildren(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if (XML_ELEMENT_NODE === $child->nodeType) {
                $html .= $this->processElement($child);
            } elseif (XML_TEXT_NODE === $child->nodeType) {
                $html .= htmlspecialchars((string) $child->nodeValue);
            }
        }

        return $html;
    }

    /**
     * Process a single element using method mapping.
     */
    private function processElement(DOMNode $node): string
    {
        $elementName = $node->nodeName;

        // Check if we have a handler method for this element
        if (isset(self::ELEMENT_HANDLERS[$elementName])) {
            $methodName = self::ELEMENT_HANDLERS[$elementName];

            return $this->$methodName($node);
        }

        // Fallback: process children recursively
        return $this->processChildren($node);
    }

    // Element processing methods

    private function processParagraph(DOMNode $node): string
    {
        if ($this->isCaption($node)) {
            return ''; // Skip standalone captions
        }

        $content = $this->processChildren($node);

        // Check if should be heading
        $headingLevel = $this->detectHeadingLevel($node, $content);
        if ($headingLevel > 0) {
            return '<h'.$headingLevel.'>'.$content.'</h'.$headingLevel.'>';
        }

        // Handle image with caption
        if ($this->containsImageFrame($node)) {
            $caption = $this->findFollowingCaption($node);
            if ($caption instanceof DOMNode) {
                return '<figure>'.$content.'<figcaption>'.$this->processChildren($caption).'</figcaption></figure>';
            }
        }

        return '<p>'.$content.'</p>';
    }

    private function processHeading(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return $this->processChildren($node);
        }

        $level = $node->getAttribute('text:outline-level') ?: '1';
        $level = min(6, max(1, (int) $level));
        $content = $this->processChildren($node);

        return '<h'.$level.'>'.$content.'</h'.$level.'>';
    }

    private function processSpan(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return $this->processChildren($node);
        }

        $styleName = $node->getAttribute(self::ODT_STYLE_NAME_ATTRIBUTE);
        $content = $this->processChildren($node);

        if ('' === trim($content) || !$styleName) {
            return $content;
        }

        $format = $this->styleMap[$styleName] ?? [];
        if (empty($format)) {
            return $content;
        }

        // Apply formatting
        if (isset($format['bold'])) {
            $content = '<strong>'.$content.'</strong>';
        }
        if (isset($format['underline'])) {
            $content = '<u>'.$content.'</u>';
        }
        if (isset($format['italic'])) {
            $content = '<em>'.$content.'</em>';
        }

        return $content;
    }

    private function processList(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return $this->processChildren($node);
        }

        // Parse list attributes for continuation support (original handler logic)
        $styleName = $node->getAttribute(self::ODT_STYLE_NAME_ATTRIBUTE);
        $listType = $node->getAttribute('text:list-type');
        $continuesList = $node->getAttribute('text:continue-list');
        $listId = $node->getAttribute('xml:id') ?: uniqid('list_', true);

        // Determine if this is an ordered or unordered list using dynamic style detection
        $isOrdered = $this->getListType($styleName, $listType);

        if ($isOrdered) {
            // Handle list continuation for ordered lists
            $startValue = $this->getListStartValue($listId, $continuesList);
            $tag = 'ol';
            $attributes = $startValue > 1 ? ' start="'.$startValue.'"' : '';
        } else {
            // Unordered lists don't need continuation handling
            $tag = 'ul';
            $attributes = '';
        }

        // Process list content
        $content = $this->processChildren($node);

        // Update counters for continued lists
        if ($isOrdered) {
            $this->updateListCounters($listId, $content);
        }

        return '<'.$tag.$attributes.'>'.$content.'</'.$tag.'>';
    }

    private function processListItem(DOMNode $node): string
    {
        $content = $this->processChildren($node);

        // Remove paragraph wrappers from list items
        // Handle case where list item starts with a paragraph followed by other elements
        $content = preg_replace('/^<p>(.*?)<\/p>(.*)$/s', '$1$2', trim($content));
        // Also handle case where entire content is just a single paragraph
        $content = preg_replace(self::PARAGRAPH_WRAPPER_REGEX, '$1', trim((string) $content));
        // Remove page breaks from list items as they're not meaningful in this context
        $content = str_replace('<hr class="page-break">', '', $content);

        return '<li>'.$content.'</li>';
    }

    private function processTable(DOMNode $node): string
    {
        $content = $this->processChildren($node);

        return '<table>'.$content.'</table>';
    }

    private function processTableRow(DOMNode $node): string
    {
        $content = $this->processChildren($node);

        return '<tr>'.$content.'</tr>';
    }

    private function processTableCell(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return $this->processChildren($node);
        }

        $attributes = '';

        // Handle column spanning (original handler logic)
        $colspan = $node->getAttribute('table:number-columns-spanned');
        if ($colspan && $colspan > 1) {
            $attributes .= ' colspan="'.$colspan.'"';
        }

        // Handle row spanning (original handler logic)
        $rowspan = $node->getAttribute('table:number-rows-spanned');
        if ($rowspan && $rowspan > 1) {
            $attributes .= ' rowspan="'.$rowspan.'"';
        }

        // Process child content
        $content = $this->processChildren($node);

        // Remove paragraph wrappers from table cells to match expected format
        $content = preg_replace(self::PARAGRAPH_WRAPPER_REGEX, '$1', trim($content));

        return '<td'.$attributes.' >'.$content.'</td>';
    }

    private function processFrame(DOMNode $node): string
    {
        return $this->processChildren($node);
    }

    private function processImage(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return '';
        }

        $xlinkHref = $node->getAttribute('xlink:href');
        if (!$xlinkHref) {
            return '';
        }

        $imageData = $this->getImageData($xlinkHref);
        if (null === $imageData) {
            return '';
        }

        return $this->buildImageTag($xlinkHref, $imageData, $node);
    }

    private function processLink(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return $this->processChildren($node);
        }

        $href = $node->getAttribute('xlink:href');
        $content = $this->processChildren($node);

        return '<a href="'.htmlspecialchars($href).'">'.$content.'</a>';
    }

    private function processNote(DOMNode $node): string
    {
        $citation = '';
        $body = '';

        foreach ($node->childNodes as $child) {
            if ('text:note-citation' === $child->nodeName) {
                $citation = $child->textContent;
            } elseif ('text:note-body' === $child->nodeName) {
                $body = $this->processChildren($child);
            }
        }

        // Remove paragraph wrappers from footnote body but preserve other formatting
        $cleanBody = preg_replace(self::PARAGRAPH_WRAPPER_REGEX, '$1', trim($body));
        // Strip HTML tags from footnote content for title attribute (title should be plain text)
        $cleanBody = strip_tags((string) $cleanBody);
        $cleanBody = trim($cleanBody);

        return '<sup title="'.htmlspecialchars($cleanBody).'">'.$citation.'</sup>';
    }

    private function processLineBreak(DOMNode $node): string
    {
        return '<br>';
    }

    private function processTab(DOMNode $node): string
    {
        // Convert tab to space for readability
        return ' ';
    }

    private function processSpace(DOMNode $node): string
    {
        // Space element
        return ' ';
    }

    private function processSoftPageBreak(DOMNode $node): string
    {
        return '<hr class="page-break">';
    }

    private function processTableHeaderRows(DOMNode $node): string
    {
        // Process table header rows - contains header cells that should be processed
        return $this->processChildren($node);
    }

    private function processCoveredTableCell(DOMNode $node): string
    {
        // Skip covered cells - they're handled by spanning
        return '';
    }

    // Helper methods - simplified versions

    private function isCaption(DOMNode $node): bool
    {
        if (!$node instanceof DOMElement) {
            return false;
        }

        $styleName = $node->getAttribute(self::ODT_STYLE_NAME_ATTRIBUTE);
        $captionStyles = ['caption', 'Caption', 'Figure', 'Illustration', 'Abbildung'];

        foreach ($captionStyles as $style) {
            if (false !== stripos($styleName, $style)) {
                return true;
            }
        }

        return false;
    }

    private function containsImageFrame(DOMNode $node): bool
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $frames = $xpath->query('.//draw:frame[draw:image]', $node);

        return $frames->length > 0;
    }

    private function findFollowingCaption(DOMNode $imageNode): ?DOMNode
    {
        $nextSibling = $imageNode->nextSibling;

        while ($nextSibling && XML_ELEMENT_NODE !== $nextSibling->nodeType) {
            $nextSibling = $nextSibling->nextSibling;
        }

        if ($nextSibling && 'text:p' === $nextSibling->nodeName && $this->isCaption($nextSibling)) {
            return $nextSibling;
        }

        return null;
    }

    private function detectHeadingLevel(DOMNode $node, string $content): int
    {
        if (!$node instanceof DOMElement) {
            return 0;
        }

        // Check style-based detection first
        $styleName = $node->getAttribute(self::ODT_STYLE_NAME_ATTRIBUTE);
        if ($styleName && isset($this->headingStyleMap[$styleName])) {
            return $this->headingStyleMap[$styleName];
        }

        // Check content patterns
        $text = trim(strip_tags($content));

        // Skip policy items
        if (preg_match('/^\d+\s*[GZ]\s*$/u', $text)) {
            return 0;
        }

        // Heading patterns
        $patterns = [
            '/^\d+\.\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u' => 4,
            '/^\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u'      => 3,
            '/^\d+\.\d+\s*[A-ZÄÖÜ]/u'           => 2,
            '/^\d+\s*[A-ZÄÖÜ]/u'                => 1,
            '/^\(\d+\)\s*[A-ZÄÖÜ]/u'            => 2,
        ];

        foreach ($patterns as $pattern => $level) {
            if (preg_match($pattern, $text)) {
                return $level;
            }
        }

        return 0;
    }

    // List processing helper methods

    /**
     * Determine if a list is ordered or unordered based on style analysis.
     */
    private function getListType(string $styleName, string $listType): bool
    {
        // First check if we have parsed style information from styles.xml
        if ('' !== $styleName && '0' !== $styleName) {
            $isOrdered = $this->listStyleMap[$styleName] ?? null;
            if (null !== $isOrdered) {
                return $isOrdered;
            }
        }

        // Check for explicit list type attributes as fallback
        return 'numbered' === $listType || 'ordered' === $listType;
    }

    /**
     * Calculate the starting value for a list, handling continuation from previous lists.
     */
    private function getListStartValue(string $listId, string $continuesList): int
    {
        if ('' !== $continuesList && '0' !== $continuesList) {
            // This list continues from another list
            $this->listContinuation[$listId] = $continuesList;

            // Find the current count of the list we're continuing from
            $parentCount = $this->listCounters[$continuesList] ?? 0;
            $startValue = $parentCount + 1;

            // Initialize this list's counter to continue the sequence
            $this->listCounters[$listId] = $parentCount;

            return $startValue;
        }

        // This is a new list sequence
        $this->listCounters[$listId] = 0;

        return 1;
    }

    /**
     * Update list counters based on the number of list items processed.
     */
    private function updateListCounters(string $listId, string $content): void
    {
        // Count the number of <li> elements in the processed content
        $itemCount = substr_count($content, '<li>');

        if ($itemCount > 0) {
            // Update the counter for this list
            $this->listCounters[$listId] = ($this->listCounters[$listId] ?? 0) + $itemCount;

            // If this list continues from another, also update the parent counter
            $continuesFrom = $this->listContinuation[$listId] ?? null;
            if (null !== $continuesFrom) {
                $this->listCounters[$continuesFrom] = $this->listCounters[$listId];
            }
        }
    }

    // Image processing helper methods

    /**
     * Get image data from extracted temporary directory.
     * Uses the proper temp directory path created by OdtFileExtractor via DemosPlanPath.
     */
    private function getImageData(string $xlinkHref): ?string
    {
        $imagePath = $this->tempDir.DIRECTORY_SEPARATOR.ltrim($xlinkHref, '/');

        // Validate path to prevent path traversal attacks
        $realTempDir = realpath($this->tempDir);
        $realImagePath = realpath($imagePath);

        if (false !== $realTempDir && false !== $realImagePath && str_starts_with(
            $realImagePath,
            $realTempDir
        ) && file_exists($realImagePath)) {
            return file_get_contents($realImagePath);
        }

        return null;
    }

    /**
     * Build HTML img tag with dimensions and base64 data.
     */
    private function buildImageTag(string $xlinkHref, string $imageData, DOMElement $node): string
    {
        $base64Data = base64_encode($imageData);
        $imageType = pathinfo($xlinkHref, PATHINFO_EXTENSION);
        $attributes = $this->buildImageAttributes($node);

        return '<img src="data:image/'.$imageType.';base64,'.$base64Data.'"'.$attributes.' />';
    }

    /**
     * Build width and height attributes for image tag.
     */
    private function buildImageAttributes(DOMElement $node): string
    {
        $attributes = '';
        $width = $this->getImageDimension($node, 'svg:width');
        $height = $this->getImageDimension($node, 'svg:height');

        if ($width) {
            $attributes .= ' width="'.$width.'"';
        }
        if ($height) {
            $attributes .= ' height="'.$height.'"';
        }

        return $attributes;
    }

    /**
     * Get image dimension from the draw:frame parent node or the image node itself.
     */
    private function getImageDimension(DOMNode $node, string $attributeName): ?string
    {
        if (!$node instanceof DOMElement) {
            return null;
        }

        // Check the image node itself first
        $dimension = $node->getAttribute($attributeName);
        if ($dimension) {
            return $this->convertOdtDimensionToPixels($dimension);
        }

        // Check parent draw:frame node
        $parent = $node->parentNode;
        if ('draw:frame' === $parent->nodeName && $parent instanceof DOMElement) {
            $dimension = $parent->getAttribute($attributeName);
            if ($dimension) {
                return $this->convertOdtDimensionToPixels($dimension);
            }
        }

        return null;
    }

    /**
     * Convert ODT dimension units to pixels for HTML.
     */
    private function convertOdtDimensionToPixels(string $dimension): string
    {
        // Remove units and convert to approximate pixel values
        // ODT typically uses cm, in, pt, etc.
        if (!preg_match('/^(\d+(?:\.\d+)?)(.*)$/', $dimension, $matches)) {
            // If no numeric value found, return as-is (might be a percentage or other CSS value)
            return $dimension;
        }

        $value = (float) $matches[1];
        $unit = $matches[2];

        $multiplier = match ($unit) {
            'cm'    => 37.8,  // 1 cm ≈ 37.8 pixels (96 DPI)
            'in'    => 96,    // 1 inch = 96 pixels (96 DPI)
            'pt'    => 1.33,  // 1 point = 1.33 pixels (96 DPI)
            'mm'    => 3.78,  // 1 mm ≈ 3.78 pixels (96 DPI)
            'px'    => 1,     // Already in pixels
            default => 1,  // Assume pixels if unknown unit
        };

        return (string) round($value * $multiplier);
    }
}
