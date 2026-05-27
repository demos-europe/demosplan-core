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
            } elseif ('' !== trim((string) $segment['text'])) {
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
        } catch (Exception) {
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
        // Handle XML/HTML parsing errors safely
        $originalInternalErrors = libxml_use_internal_errors(true);
        try {
            $success = $dom->loadHTML(
                '<?xml encoding="utf-8" ?>'.$html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_use_internal_errors($originalInternalErrors);
            libxml_clear_errors();
        }

        if (!$success) {
            // Fallback to treating as plain text if HTML parsing fails
            $segments[] = $this->createTextSegment(strip_tags($html), false, false, false);

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
            if (XML_ELEMENT_NODE === $child->nodeType) {
                $this->processElementNode($child, $segments, $bold, $italic, $underline);
            } elseif (XML_TEXT_NODE === $child->nodeType) {
                $this->processTextNode($child, $segments, $bold, $italic, $underline);
            }
        }
    }

    /**
     * Process an element node and handle formatting tags.
     */
    private function processElementNode(DOMNode $child, array &$segments, bool $bold, bool $italic, bool $underline): void
    {
        $tag = strtolower($child->nodeName);

        $this->handleSpecialTags($tag, $segments);

        $formatting = $this->updateFormattingForTag($tag, $bold, $italic, $underline);

        $this->traverseDom($child, $segments, $formatting['bold'], $formatting['italic'], $formatting['underline']);
    }

    /**
     * Handle special tags like paragraph breaks and line breaks.
     */
    private function handleSpecialTags(string $tag, array &$segments): void
    {
        if ('p' === $tag && [] !== $segments) {
            $segments[] = $this->createLineBreakSegment();
        }

        if ('br' === $tag) {
            $segments[] = $this->createLineBreakSegment();
        }
    }

    /**
     * Create a line break segment.
     */
    private function createLineBreakSegment(): array
    {
        return ['text' => "\n", 'bold' => false, 'italic' => false, 'underline' => false];
    }

    /**
     * Update formatting based on HTML tag.
     */
    private function updateFormattingForTag(string $tag, bool $bold, bool $italic, bool $underline): array
    {
        $currentBold = $bold || in_array($tag, ['strong', 'b']);
        $currentItalic = $italic || in_array($tag, ['em', 'i']);
        $currentUnderline = $underline || ('u' === $tag);

        return [
            'bold'      => $currentBold,
            'italic'    => $currentItalic,
            'underline' => $currentUnderline,
        ];
    }

    /**
     * Process a text node and add it to segments.
     */
    private function processTextNode(DOMNode $child, array &$segments, bool $bold, bool $italic, bool $underline): void
    {
        $text = $child->nodeValue;
        if ('' !== trim((string) $text)) {
            $segments[] = $this->createTextSegment($text, $bold, $italic, $underline);
        }
    }

    /**
     * Create a text segment with formatting information.
     */
    private function createTextSegment(string $text, bool $bold, bool $italic, bool $underline): array
    {
        return [
            'text'      => $text,
            'bold'      => $bold,
            'italic'    => $italic,
            'underline' => $underline,
        ];
    }
}
