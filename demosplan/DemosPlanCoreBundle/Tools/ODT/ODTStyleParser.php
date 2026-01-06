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
    public function parseStyles(DOMDocument $dom, ?string $stylesXml = null): array
    {
        $styleMap = [];
        $headingStyleMap = [];

        $xpath = new DOMXPath($dom);

        // Parse automatic styles from content.xml
        $this->parseStylesFromXPath($xpath, '//office:automatic-styles/style:style', $styleMap, $headingStyleMap);

        // Parse main styles from styles.xml if available
        if (null !== $stylesXml && ('' !== $stylesXml && '0' !== $stylesXml)) {
            $stylesDom = new DOMDocument();
            $stylesDom->loadXML($stylesXml);
            $stylesXPath = new DOMXPath($stylesDom);
            $this->parseStylesFromXPath($stylesXPath, '//office:styles/style:style', $styleMap, $headingStyleMap);
        }

        return [
            'styleMap'        => $styleMap,
            'headingStyleMap' => $headingStyleMap,
        ];
    }

    /**
     * Parse styles from a specific XPath query.
     */
    private function parseStylesFromXPath(DOMXPath $xpath, string $query, array &$styleMap, array &$headingStyleMap): void
    {
        // Parse text styles
        $textStyleNodes = $xpath->query($query.'[@style:family="text"]');
        foreach ($textStyleNodes as $styleNode) {
            /** @var DOMElement $styleNode */
            $styleName = $styleNode->getAttribute(self::STYLE_NAME);
            if (empty($styleName)) {
                continue;
            }

            $format = $this->extractTextFormat($xpath, $styleNode);
            if ([] !== $format) {
                $styleMap[$styleName] = $format;
            }
        }

        // Parse paragraph styles for heading detection
        $paragraphStyles = $xpath->query($query.'[@style:family="paragraph"]');
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
    }

    /**
     * Parse list styles from styles XML.
     */
    public function parseListStyles(string $stylesXml): array
    {
        if ('' === $stylesXml || '0' === $stylesXml) {
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
        if ('text:list-level-style-bullet' === $levelElement->nodeName) {
            // Bullet lists are always unordered
            return false;
        }

        if ('text:list-level-style-number' === $levelElement->nodeName) {
            // Number-based lists are ordered - check for valid numbering format
            $numFormat = $levelElement->getAttribute('style:num-format');

            // Any non-empty num-format indicates a numbered list (1, a, A, i, I, etc.)
            // Empty num-format means no numbering (like "No List (WW)" style)
            return !empty($numFormat) && '' !== $numFormat;
        }

        return false;
    }

    /**
     * Extract text formatting information from a style node.
     */
    private function extractTextFormat(DOMXPath $xpath, DOMElement $styleNode): array
    {
        $properties = $xpath->query('style:text-properties', $styleNode);
        if (0 === $properties->length) {
            return [];
        }

        $textProps = $properties->item(0);
        if (null === $textProps || !$textProps instanceof DOMElement) {
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

        // Analyze style properties to detect heading characteristics
        return $this->analyzeStyleForHeading($xpath, $styleNode);
    }

    /**
     * Analyze style properties dynamically to determine if it's a heading.
     */
    private function analyzeStyleForHeading(DOMXPath $xpath, DOMElement $styleNode): int
    {
        $textPropsNode = $xpath->query('style:text-properties', $styleNode)->item(0);
        $paraPropsNode = $xpath->query('style:paragraph-properties', $styleNode)->item(0);

        $textProps = ($textPropsNode instanceof DOMElement) ? $textPropsNode : null;
        $paraProps = ($paraPropsNode instanceof DOMElement) ? $paraPropsNode : null;

        $fontSize = $this->extractFontSize($textProps);
        $headingScore = $this->calculateHeadingScore($textProps, $paraProps, $fontSize);

        return $this->determineHeadingLevel($headingScore, $fontSize);
    }

    /**
     * Extract font size from text properties.
     */
    private function extractFontSize(?DOMElement $textProps): int
    {
        if (!$textProps instanceof DOMElement) {
            return 12; // Default base font size
        }

        $fontSizeAttr = $textProps->getAttribute('fo:font-size');

        return $fontSizeAttr ? (int) filter_var($fontSizeAttr, FILTER_SANITIZE_NUMBER_INT) : 12;
    }

    /**
     * Calculate heading score based on text and paragraph properties.
     */
    private function calculateHeadingScore(?DOMElement $textProps, ?DOMElement $paraProps, int $fontSize): int
    {
        $score = 0;

        $score += $this->getTextPropertiesScore($textProps, $fontSize);
        $score += $this->getParagraphPropertiesScore($paraProps);

        return $score;
    }

    /**
     * Get heading score from text properties.
     */
    private function getTextPropertiesScore(?DOMElement $textProps, int $fontSize): int
    {
        if (!$textProps instanceof DOMElement) {
            return 0;
        }

        $score = 0;

        // Font size analysis
        if ($fontSize >= 18) {
            $score += 3; // Large font strongly indicates heading
        } elseif ($fontSize >= 14) {
            $score += 2; // Medium-large font indicates heading
        }

        // Font weight analysis
        if ($this->isBold($textProps)) {
            $score += 2; // Bold text indicates heading
        }

        return $score;
    }

    /**
     * Get heading score from paragraph properties.
     */
    private function getParagraphPropertiesScore(?DOMElement $paraProps): int
    {
        if (!$paraProps instanceof DOMElement) {
            return 0;
        }

        $score = 0;

        // Check for keep-with-next (headings often have this)
        if ('always' === $paraProps->getAttribute('fo:keep-with-next')) {
            ++$score;
        }

        // Check for distinctive margins
        $marginTop = $paraProps->getAttribute('fo:margin-top');
        $marginBottom = $paraProps->getAttribute('fo:margin-bottom');

        if ($marginTop && $this->parseMargin($marginTop) > 0.3) { // > 3mm top margin
            ++$score;
        }

        if ($marginBottom && $this->parseMargin($marginBottom) > 0.15) { // > 1.5mm bottom margin
            ++$score;
        }

        return $score;
    }

    /**
     * Determine heading level based on score and font size.
     */
    private function determineHeadingLevel(int $headingScore, int $fontSize): int
    {
        if ($headingScore >= 4) {
            // Very likely a heading - determine level by font size
            if ($fontSize >= 20) {
                return 1;
            }
            if ($fontSize >= 16) {
                return 2;
            }

            return 3;
        }

        if ($headingScore >= 3) {
            // Likely a heading
            return $fontSize >= 18 ? 2 : 3;
        }

        return 0; // Not a heading
    }

    /**
     * Parse margin value and convert to cm.
     */
    private function parseMargin(string $margin): float
    {
        if (str_ends_with($margin, 'cm')) {
            return (float) str_replace('cm', '', $margin);
        }

        if (str_ends_with($margin, 'pt')) {
            return (float) str_replace('pt', '', $margin) * 0.0353; // Convert pt to cm
        }

        return 0.0;
    }

    /**
     * Check if text properties indicate bold formatting.
     */
    private function isBold(DOMElement $textProps): bool
    {
        return 'bold' === $textProps->getAttribute('fo:font-weight')
               || 'bold' === $textProps->getAttribute('style:font-weight-asian');
    }

    /**
     * Check if text properties indicate italic formatting.
     */
    private function isItalic(DOMElement $textProps): bool
    {
        return 'italic' === $textProps->getAttribute('fo:font-style')
               || 'italic' === $textProps->getAttribute('style:font-style-asian');
    }

    /**
     * Check if text properties indicate underlined formatting.
     */
    private function isUnderlined(DOMElement $textProps): bool
    {
        return 'solid' === $textProps->getAttribute('style:text-underline-style');
    }
}
