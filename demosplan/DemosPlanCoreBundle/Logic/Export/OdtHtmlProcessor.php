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
        $segments = $this->parseHtmlWithRegex($html);
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
     * Parse HTML using regex to extract formatted text segments.
     */
    private function parseHtmlWithRegex(string $html): array
    {
        $segments = [];
        // Split by paragraph tags first
        $paragraphs = preg_split('/<\/?p[^>]*>/i', $html);
        foreach ($paragraphs as $index => $paragraph) {
            if ('' === trim($paragraph)) {
                continue;
            }
            // Add line break between paragraphs (except first)
            if ($index > 0 && !empty($segments)) {
                $segments[] = ['text' => "\n", 'bold' => false, 'italic' => false, 'underline' => false];
            }
            // Process formatting within paragraph
            $this->processFormattedText($paragraph, $segments);
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
     * Process formatted text segments using regex patterns.
     */
    private function processFormattedText(string $text, array &$segments): void
    {
        // Pattern to match nested formatting tags
        $pattern = '/(<(strong|b|em|i|u)[^>]*>.*?<\/\2>)|([^<]+)/i';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    // Formatted text found
                    $content = $match[1];
                    $innerText = strip_tags($content);
                    if ('' !== trim($innerText)) {
                        $bold = preg_match('/<(strong|b)[^>]*>/i', $content);
                        $italic = preg_match('/<(em|i)[^>]*>/i', $content);
                        $underline = preg_match('/<u[^>]*>/i', $content);
                        $segments[] = [
                            'text'      => $innerText,
                            'bold'      => (bool) $bold,
                            'italic'    => (bool) $italic,
                            'underline' => (bool) $underline,
                        ];
                    }
                } elseif (!empty($match[3])) {
                    // Plain text
                    $plainText = trim($match[3]);
                    if ('' !== $plainText) {
                        $segments[] = [
                            'text'      => $plainText,
                            'bold'      => false,
                            'italic'    => false,
                            'underline' => false,
                        ];
                    }
                }
            }
        }
    }
}
