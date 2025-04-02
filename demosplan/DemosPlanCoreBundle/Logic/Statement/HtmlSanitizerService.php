<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;

class HtmlSanitizerService
{
    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    /**
     * Escapes all HTML tags except for a specific set of allowed tags.
     * This ensures that text containing angle brackets (like '<Rg 29>') is properly
     * escaped while legitimate HTML tags are preserved.
     *
     * This approach:
     * 1. First HTML-encodes everything to safely escape all content
     * 2. Selectively decodes only the allowed HTML tags
     * 3. Uses HTMLPurifier indirectly (through HTMLSanitizer) for final sanitization
     *
     * @param string $inputString The input text which may contain both valid HTML and text that looks like HTML
     * @return string The sanitized text with only allowed HTML tags preserved
     */
    public function escapeDisallowedTags(string $inputString): string
    {
        // Step 1: Encode everything to safely escape all potential HTML
        $encodedString = htmlspecialchars($inputString, ENT_NOQUOTES, 'UTF-8');

        // Step 2: Define the allowed tags
        $allowedTags = [
            'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio',
            'b', 'base', 'bdi', 'bdo', 'blockquote', 'body', 'br', 'button',
            'canvas', 'caption', 'cite', 'code', 'col', 'colgroup',
            'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'dt',
            'em', 'embed',
            'fieldset', 'figcaption', 'figure', 'footer', 'form',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html',
            'i', 'iframe', 'img', 'input', 'ins',
            'label', 'legend', 'li', 'link',
            'main', 'map', 'mark', 'meta', 'meter',
            'nav', 'noscript',
            'object', 'ol', 'optgroup', 'option', 'output',
            'p', 'param', 'picture', 'pre', 'progress',
            'q',
            'rp', 'rt', 'ruby',
            's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span', 'strong', 'style', 'sub', 'summary', 'sup',
            'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track',
            'u', 'ul',
            'var', 'video',
            'wbr'
        ];

        // Step 3: Create a map of encoded tags to their original form
        $encodedToOriginalMap = [];
        foreach ($allowedTags as $tag) {
            // Opening tags
            $encodedToOriginalMap['&lt;' . $tag . '&gt;'] = '<' . $tag . '>';
            // Closing tags
            $encodedToOriginalMap['&lt;/' . $tag . '&gt;'] = '</' . $tag . '>';
        }

        // Special handling for DOCTYPE
        $encodedToOriginalMap['&lt;!DOCTYPE&gt;'] = '<!DOCTYPE>';

        // Step 4: Replace encoded allowed tags with their original form
        $decodedString = strtr($encodedString, $encodedToOriginalMap);

        // Step 5: Handle tags with attributes (a href, img, etc.)
        $tagsWithAttributes = ['a href', 'img'];

        foreach ($tagsWithAttributes as $tagWithAttr) {
            // Match pattern for tags with attributes
            $pattern = '/&lt;(' . preg_quote($tagWithAttr, '/') . '[^&]*)&gt;/';

            // Replace with actual HTML tags
            $decodedString = preg_replace_callback(
                $pattern,
                static function ($matches) {
                    return '<' . $matches[1] . '>';
                },
                $decodedString
            );
        }

        // Step 6: Use existing HTMLSanitizer service to purify the content
        // This ensures the allowed tags are valid and safe
        return $this->htmlSanitizer->purify($decodedString);
    }
}
