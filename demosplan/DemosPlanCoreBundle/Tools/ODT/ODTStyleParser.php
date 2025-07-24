<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Parser for ODT style information.
 *
 * This class handles all ODT style parsing operations, including text formatting,
 * heading detection, and list style analysis.
 */
class ODTStyleParser implements ODTStyleParserInterface
{
    private const STYLE_NAME = 'style:name';

    /**
     * Parse ODT styles from content and styles XML.
     */
    public function parseStyles(DOMDocument $dom): array
    {
        $styleMap = [];
        $headingStyleMap = [];

        $xpath = new DOMXPath($dom);

        // Parse text styles
        $styleNodes = $xpath->query('//office:automatic-styles/style:style[@style:family="text"]');
        foreach ($styleNodes as $styleNode) {
            /** @var DOMElement $styleNode */
            $styleName = $styleNode->getAttribute(self::STYLE_NAME);
            if (empty($styleName)) {
                continue;
            }

            $format = $this->extractTextFormat($xpath, $styleNode);
            if (!empty($format)) {
                $styleMap[$styleName] = $format;
            }
        }

        // Parse paragraph styles for heading detection
        $paragraphStyles = $xpath->query('//office:automatic-styles/style:style[@style:family="paragraph"]');
        foreach ($paragraphStyles as $styleNode) {
            /** @var DOMElement $styleNode */
            $styleName = $styleNode->getAttribute(self::STYLE_NAME);
            if (empty($styleName)) {
                continue;
            }

            $headingLevel = $this->extractHeadingLevel($xpath, $styleNode);
            if ($headingLevel > 0) {
                $headingStyleMap[$styleName] = $headingLevel;
            }
        }

        return [
            'styleMap' => $styleMap,
            'headingStyleMap' => $headingStyleMap
        ];
    }

    /**
     * Parse list styles from styles XML.
     */
    public function parseListStyles(string $stylesXml): array
    {
        if (empty($stylesXml)) {
            return [];
        }

        $xpath = $this->createXPathForListStyles($stylesXml);
        $listStyles = $xpath->query('//text:list-style');

        $listStyleMap = [];
        foreach ($listStyles as $listStyle) {
            /** @var DOMElement $listStyle */
            $styleName = $listStyle->getAttribute(self::STYLE_NAME);
            if (empty($styleName)) {
                continue;
            }

            $isOrdered = $this->determineListOrder($xpath, $listStyle);
            $listStyleMap[$styleName] = $isOrdered;
        }

        return $listStyleMap;
    }

    /**
     * Create XPath object with registered namespaces for list style parsing.
     */
    private function createXPathForListStyles(string $stylesXml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($stylesXml);
        $xpath = new DOMXPath($dom);

        // Register namespaces for ODT
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');

        return $xpath;
    }

    /**
     * Determine if a list style represents an ordered or unordered list.
     */
    private function determineListOrder(DOMXPath $xpath, DOMElement $listStyle): bool
    {
        // Check the first level to determine if it's ordered or unordered
        // According to ODT spec: text:list-level-style-number = ordered, text:list-level-style-bullet = unordered
        $firstLevel = $xpath->query('text:list-level-style-number | text:list-level-style-bullet', $listStyle)->item(0);

        if (!($firstLevel instanceof DOMElement)) {
            return false;
        }

        return $this->isOrderedListLevel($firstLevel);
    }

    /**
     * Check if a list level element represents an ordered list.
     */
    private function isOrderedListLevel(DOMElement $levelElement): bool
    {
        if ($levelElement->nodeName === 'text:list-level-style-bullet') {
            // Bullet lists are always unordered
            return false;
        }

        if ($levelElement->nodeName === 'text:list-level-style-number') {
            // Number-based lists are ordered - check for valid numbering format
            $numFormat = $levelElement->getAttribute('style:num-format');
            // Any non-empty num-format indicates a numbered list (1, a, A, i, I, etc.)
            // Empty num-format means no numbering (like "No List (WW)" style)
            return !empty($numFormat) && $numFormat !== '';
        }

        return false;
    }

    /**
     * Extract text formatting information from a style node.
     */
    private function extractTextFormat(DOMXPath $xpath, DOMElement $styleNode): array
    {
        $properties = $xpath->query('style:text-properties', $styleNode);
        if ($properties->length === 0) {
            return [];
        }

        $textProps = $properties->item(0);
        if ($textProps === null || !$textProps instanceof DOMElement) {
            return [];
        }

        $format = [];

        if ($this->isBold($textProps)) {
            $format['bold'] = true;
        }

        if ($this->isItalic($textProps)) {
            $format['italic'] = true;
        }

        if ($this->isUnderlined($textProps)) {
            $format['underline'] = true;
        }

        return $format;
    }

    /**
     * Extract heading level from paragraph style definition.
     */
    private function extractHeadingLevel(DOMXPath $xpath, DOMElement $styleNode): int
    {
        // Check if parent style indicates heading
        $parentStyleName = $styleNode->getAttribute('style:parent-style-name');
        if ($parentStyleName && str_contains($parentStyleName, 'Heading')) {
            // Extract level from parent style name like "Heading_1", "Heading_2"
            if (preg_match('/Heading[_\s]*(\d+)/', $parentStyleName, $matches)) {
                return (int) $matches[1];
            }
            return 1; // Default to level 1 for generic heading styles
        }

        // Check text properties for heading-like formatting
        $textProps = $xpath->query('style:text-properties', $styleNode)->item(0);
        if ($textProps && $textProps instanceof DOMElement) {
            $isBold = $this->isBold($textProps);
            $fontSize = $textProps->getAttribute('fo:font-size');

            // Detect heading based on bold + large font size
            if ($isBold && $fontSize) {
                $size = (int) filter_var($fontSize, FILTER_SANITIZE_NUMBER_INT);
                if ($size >= 14) {
                    return 1; // Large bold text likely a heading
                }
            }
        }

        return 0; // Not a heading
    }

    /**
     * Check if text properties indicate bold formatting.
     */
    private function isBold(DOMElement $textProps): bool
    {
        return $textProps->getAttribute('fo:font-weight') === 'bold' ||
               $textProps->getAttribute('style:font-weight-asian') === 'bold';
    }

    /**
     * Check if text properties indicate italic formatting.
     */
    private function isItalic(DOMElement $textProps): bool
    {
        return $textProps->getAttribute('fo:font-style') === 'italic' ||
               $textProps->getAttribute('style:font-style-asian') === 'italic';
    }

    /**
     * Check if text properties indicate underlined formatting.
     */
    private function isUnderlined(DOMElement $textProps): bool
    {
        return $textProps->getAttribute('style:text-underline-style') === 'solid';
    }
}
