<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use DOMNode;
use DOMXPath;

/**
 * Handler for ODT text elements - contains all text processing logic.
 *
 * This handler is a domain expert for text processing, containing all the
 * business logic for converting ODT text elements to HTML.
 */
class TextElementHandler implements ODTElementHandler
{
    private const TEXT_P = 'text:p';
    private const TEXT_STYLE_NAME = 'text:style-name';

    public function canHandle(string $elementName): bool
    {
        return in_array($elementName, $this->getSupportedElements(), true);
    }

    public function getSupportedElements(): array
    {
        return [self::TEXT_P, 'text:h', 'text:span', 'text:note'];
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function process(DOMNode $node, ProcessingContext $context): string
    {
        return match ($node->nodeName) {
            self::TEXT_P => $this->processParagraph($node, $context),
            'text:h' => $this->processHeading($node, $context),
            'text:span' => $this->processSpan($node, $context),
            'text:note' => $this->processNote($node, $context),
            default => $context->processChildren($node),
        };
    }

    /**
     * Process paragraph element - contains paragraph processing logic.
     */
    private function processParagraph(DOMNode $node, ProcessingContext $context): string
    {
        // Check if this paragraph is a caption
        if ($this->isCaption($node)) {
            // Caption paragraphs are handled by processFrame, skip standalone ones
            return '';
        }

        // Process paragraph content using context
        $content = $context->processChildren($node);

        // Check if this paragraph should be converted to a heading
        $headingLevel = $this->detectHeadingLevel($node, $content, $context);
        if ($headingLevel > 0) {
            return '<h' . $headingLevel . '>' . $content . '</h' . $headingLevel . '>';
        }

        // Check if this paragraph contains an image frame
        if ($this->containsImageFrame($node)) {
            // Look ahead for a caption paragraph
            $caption = $this->findFollowingCaption($node);
            if ($caption) {
                // Wrap image and caption in a figure element
                return '<figure>' . $content . '<figcaption>' . $context->processChildren($caption) . '</figcaption></figure>';
            }
        }

        return '<p>' . $content . '</p>';
    }

    /**
     * Process heading element - contains heading processing logic.
     */
    private function processHeading(DOMNode $node, ProcessingContext $context): string
    {
        if (!$node instanceof \DOMElement) {
            return $context->processChildren($node);
        }

        $level = $node->getAttribute('text:outline-level') ?: '1';
        $level = min(6, max(1, (int) $level)); // Ensure level is between 1-6
        $content = $context->processChildren($node);
        return '<h' . $level . '>' . $content . '</h' . $level . '>';
    }

    /**
     * Process span element - contains text formatting logic.
     */
    private function processSpan(DOMNode $node, ProcessingContext $context): string
    {
        if (!$node instanceof \DOMElement) {
            return $context->processChildren($node);
        }

        $styleName = $node->getAttribute(self::TEXT_STYLE_NAME);
        $content = $context->processChildren($node);

        // If content is empty, don't apply any formatting to avoid empty tags
        if (trim($content) === '') {
            return $content;
        }

        // If we have no style information, return content as-is
        if (!$styleName) {
            return $content;
        }

        $format = $context->getStyle($styleName);
        if (empty($format)) {
            return $content;
        }

        // Apply formatting based on parsed style properties in the expected order
        if (isset($format['bold'])) {
            $content = '<strong>' . $content . '</strong>';
        }

        // Apply underline first, then italic to get the expected nesting
        if (isset($format['underline'])) {
            $content = '<u>' . $content . '</u>';
        }

        if (isset($format['italic'])) {
            $content = '<em>' . $content . '</em>';
        }

        return $content;
    }

    /**
     * Process note element - contains footnote/endnote processing logic.
     */
    private function processNote(DOMNode $node, ProcessingContext $context): string
    {
        $citation = '';
        $body = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'text:note-citation') {
                $citation = $child->textContent;
            } elseif ($child->nodeName === 'text:note-body') {
                $body = $context->processChildren($child);
            }
        }

        // Remove paragraph wrappers from footnote body but preserve other formatting
        $cleanBody = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($body));
        // Strip HTML tags from footnote content for title attribute (title should be plain text)
        $cleanBody = strip_tags($cleanBody);
        $cleanBody = trim($cleanBody);

        return '<sup title="' . htmlspecialchars($cleanBody) . '">' . $citation . '</sup>';
    }

    /**
     * Check if a paragraph is a caption based on its style.
     */
    private function isCaption(DOMNode $node): bool
    {
        if (!$node instanceof \DOMElement) {
            return false;
        }

        $styleName = $node->getAttribute(self::TEXT_STYLE_NAME);

        // Common caption style names in ODT files
        $captionStyles = ['caption', 'Caption', 'Figure', 'Illustration', 'Abbildung'];

        foreach ($captionStyles as $style) {
            if (stripos($styleName, $style) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a paragraph contains an image frame.
     */
    private function containsImageFrame(DOMNode $node): bool
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $frames = $xpath->query('.//draw:frame[draw:image]', $node);
        return $frames->length > 0;
    }

    /**
     * Find the caption paragraph that follows an image paragraph.
     */
    private function findFollowingCaption(DOMNode $imageNode): ?DOMNode
    {
        $nextSibling = $imageNode->nextSibling;

        // Skip text nodes (whitespace) to find the next element
        while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
            $nextSibling = $nextSibling->nextSibling;
        }

        // Check if the next element is a caption paragraph
        if ($nextSibling && $nextSibling->nodeName === self::TEXT_P && $this->isCaption($nextSibling)) {
            return $nextSibling;
        }

        return null;
    }

    /**
     * Detect if a paragraph should be converted to a heading based on content patterns.
     */
    private function detectHeadingLevel(DOMNode $node, string $content, ProcessingContext $context): int
    {
        if (!$node instanceof \DOMElement) {
            return 0;
        }

        // First check style-based detection
        $styleName = $node->getAttribute(self::TEXT_STYLE_NAME);
        $headingStyleMap = $context->getHeadingStyleMap();
        if ($styleName && isset($headingStyleMap[$styleName])) {
            return $headingStyleMap[$styleName];
        }

        // Then check content patterns
        $patternLevel = $this->detectHeadingByPattern($content);
        if ($patternLevel > 0) {
            return $patternLevel;
        }

        // Finally check formatting-based detection
        return $this->detectHeadingByFormatting();
    }

    /**
     * Detect heading level based on content patterns.
     */
    private function detectHeadingByPattern(string $content): int
    {
        $text = trim(strip_tags($content));

        // Skip policy items (single number followed by G or Z, with or without space)
        if (preg_match('/^\d+\s*[GZ]\s*$/u', $text)) {
            return 0; // Keep as paragraph
        }

        // Generic numerical patterns (avoiding specific language patterns)
        // Note: \s* allows for optional whitespace to handle both "7 Title" and "7Title" cases
        $patterns = [
            '/^\d+\.\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u' => 4, // "3.1.1.1 Sub-sub-subsection" or "3.1.1.1Sub-sub-subsection"
            '/^\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u' => 3, // "3.1.1 Sub-subsection" or "3.1.1Sub-subsection"
            '/^\d+\.\d+\s*[A-ZÄÖÜ]/u' => 2,     // "2.1 Subsection" or "2.1Subsection"
            '/^\d+\s*[A-ZÄÖÜ]/u' => 1,           // "1 Section Title" or "1Section"
            '/^\(\d+\)\s*[A-ZÄÖÜ]/u' => 2,      // "(1) Parenthetical heading" or "(1)Parenthetical"
        ];

        foreach ($patterns as $pattern => $level) {
            if (preg_match($pattern, $text)) {
                return $level;
            }
        }

        return 0;
    }

    /**
     * Detect heading level based on formatting analysis.
     */
    private function detectHeadingByFormatting(): int
    {
        // This method would analyze the formatting of the content
        // For now, return 0 (no heading detected)
        // Could be extended to analyze font sizes, bold formatting, etc.
        return 0;
    }
}
