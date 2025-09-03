<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DOMDocument;
use DOMNode;
use Exception;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\PhpWord;

/**
 * Handles ODT-specific HTML processing for export functionality.
 * Extracted from DocxExporter to follow Single Responsibility Principle.
 */
class OdtHtmlProcessor
{
    private const ODT_PLAIN_STYLE = 'odtPlain';
    private const ODT_BOLD_STYLE = 'odtBold';
    private const ODT_ITALIC_STYLE = 'odtItalic';
    private const ODT_UNDERLINE_STYLE = 'odtUnderline';
    private const ODT_BOLD_ITALIC_STYLE = 'odtBoldItalic';
    private const ODT_BOLD_UNDERLINE_STYLE = 'odtBoldUnderline';
    private const ODT_ITALIC_UNDERLINE_STYLE = 'odtItalicUnderline';
    private const ODT_BOLD_ITALIC_UNDERLINE_STYLE = 'odtBoldItalicUnderline';

    /**
     * Process HTML content and add it to a cell with ODT-specific formatting.
     */
    public function processHtmlForCell(Cell $cell, string $html): void
    {
        $segments = $this->parseHtmlWithDom($html);
        foreach ($segments as $segment) {
            if ("\n" === $segment['text']) {
                $cell->addTextBreak();
            } elseif ('' !== trim($segment['text'])) {
                $styleName = $this->getOdtStyleName($segment['bold'], $segment['italic'], $segment['underline']);
                $cell->addText($segment['text'], $styleName);
            }
        }
    }

    /**
     * Register all font styles needed for ODT format.
     */
    public function registerStyles(PhpWord $phpWord): void
    {
        try {
            $baseStyle = ['name' => 'Arial', 'size' => 9];

            $phpWord->addFontStyle(self::ODT_PLAIN_STYLE, $baseStyle);
            $phpWord->addFontStyle(self::ODT_BOLD_STYLE, $baseStyle + ['bold' => true]);
            $phpWord->addFontStyle(self::ODT_ITALIC_STYLE, $baseStyle + ['italic' => true]);
            $phpWord->addFontStyle(self::ODT_UNDERLINE_STYLE, $baseStyle + ['underline' => 'single']);
            $phpWord->addFontStyle(self::ODT_BOLD_ITALIC_STYLE, $baseStyle + ['bold' => true, 'italic' => true]);
            $phpWord->addFontStyle(self::ODT_BOLD_UNDERLINE_STYLE, $baseStyle + ['bold' => true, 'underline' => 'single']);
            $phpWord->addFontStyle(self::ODT_ITALIC_UNDERLINE_STYLE, $baseStyle + ['italic' => true, 'underline' => 'single']);
            $phpWord->addFontStyle(self::ODT_BOLD_ITALIC_UNDERLINE_STYLE, $baseStyle + ['bold' => true, 'italic' => true, 'underline' => 'single']);
        } catch (Exception $e) {
            // Ignore duplicate registration errors
        }
    }

    /**
     * Parse HTML using DOMDocument to extract formatted text segments.
     */
    private function parseHtmlWithDom(string $html): array
    {
        $segments = [];
        
        if ('' === trim($html)) {
            return $segments;
        }

        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        $originalErrorLevel = error_reporting(E_ERROR);
        $success = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        error_reporting($originalErrorLevel);

        if (!$success) {
            // Fallback to treating as plain text if HTML parsing fails
            $segments[] = ['text' => strip_tags($html), 'bold' => false, 'italic' => false, 'underline' => false];
            return $segments;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $this->traverseDom($body, $segments);
        } else {
            // If no body found, traverse the document node directly
            $this->traverseDom($dom, $segments);
        }

        return $segments;
    }

    /**
     * Get the appropriate ODT style name for formatting combination.
     */
    private function getOdtStyleName(bool $bold, bool $italic, bool $underline): string
    {
        return match (true) {
            $bold && $italic && $underline => self::ODT_BOLD_ITALIC_UNDERLINE_STYLE,
            $bold && $italic               => self::ODT_BOLD_ITALIC_STYLE,
            $bold && $underline            => self::ODT_BOLD_UNDERLINE_STYLE,
            $italic && $underline          => self::ODT_ITALIC_UNDERLINE_STYLE,
            $bold                          => self::ODT_BOLD_STYLE,
            $italic                        => self::ODT_ITALIC_STYLE,
            $underline                     => self::ODT_UNDERLINE_STYLE,
            default                        => self::ODT_PLAIN_STYLE,
        };
    }

    /**
     * Recursively traverse DOM nodes to extract text segments with formatting.
     */
    private function traverseDom(DOMNode $node, array &$segments, bool $bold = false, bool $italic = false, bool $underline = false): void
    {
        foreach ($node->childNodes as $child) {
            $currentBold = $bold;
            $currentItalic = $italic;
            $currentUnderline = $underline;

            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                
                // Handle paragraph breaks
                if ('p' === $tag && !empty($segments)) {
                    $segments[] = ['text' => "\n", 'bold' => false, 'italic' => false, 'underline' => false];
                }
                
                // Handle formatting tags
                if (in_array($tag, ['strong', 'b'])) {
                    $currentBold = true;
                }
                if (in_array($tag, ['em', 'i'])) {
                    $currentItalic = true;
                }
                if ('u' === $tag) {
                    $currentUnderline = true;
                }
                
                // Handle break tags
                if ('br' === $tag) {
                    $segments[] = ['text' => "\n", 'bold' => false, 'italic' => false, 'underline' => false];
                }
                
                $this->traverseDom($child, $segments, $currentBold, $currentItalic, $currentUnderline);
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->nodeValue;
                if ('' !== trim($text)) {
                    $segments[] = [
                        'text'      => $text,
                        'bold'      => $currentBold,
                        'italic'    => $currentItalic,
                        'underline' => $currentUnderline,
                    ];
                }
            }
        }
    }
}
