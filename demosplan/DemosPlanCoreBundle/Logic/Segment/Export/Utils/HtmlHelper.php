<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ImageReference;

class HtmlHelper
{
    public const LINK_CLASS_FOR_DARSTELLUNG_STELL = 'pdf_importer_image';

    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    public function getHtmlValidText(string $text): string
    {
        /** @var string $text $text */
        $text = str_replace('<br>', '<br/>', $text);

        // strip all a tags without href
        $pattern = '/<a(?![^>]*\bhref=)([^>]*)>(.*?)<\/a>/i';
        $text = preg_replace($pattern, '$2', $text);

        // avoid problems in phpword parser
        return $this->htmlSanitizer->purify($text);
    }

    /**
     * Extracts URLs from the given HTML text that are contained within <a> tags with the specified class, and
     * creates ImageReference objects from those URLs. The link text of the <a> tags is used as the image reference.
     * The image reference is prefixed with the specified prefix.
     *
     * @return array<int, ImageReference> an array containing the extracted ImageReference objects
     */
    public function extractImageDataByClass(string $htmlText, string $class, string $prefix): array
    {
        $imageReferences = [];

        // The regex pattern to match <a> tags with the specified class and extract their href attributes and link text
        // irrespective of the order of class and href attributes
        $pattern = '/<a\b(?=[^>]*\bclass="[^"]*\b'.preg_quote($class, '/').'\b[^"]*")(?=[^>]*\bhref="([^"]*)")[^>]*>(.*?)<\/a>/i';

        // Perform the regex match
        if (preg_match_all($pattern, $htmlText, $matches)) {
            foreach ($matches[1] as $index => $url) {
                $linkText = $matches[2][$index];
                // Create an ImageReference object with linkText as imageReference and url as imagePath
                $srcParts = explode('/', $url);
                $hash = $srcParts[array_key_last($srcParts)];
                $imageReference = new ImageReference($prefix.$linkText, $url, $hash);
                $imageReferences[] = $imageReference;
            }
        }

        return $imageReferences;
    }

    /**
     * Updates the link text of all links with the specified class by appending a prefix, changing the href value based
     * on the link text, changing the text color to blue and underlining the text.
     */
    public function updateLinkTextWithClass(string $htmlText, string $className, string $prefix): string
    {
        $pattern = '/<a\b(?=[^>]*\bclass="[^"]*\b'
            .preg_quote($className, '/').'\b[^"]*")(?=[^>]*\bhref="([^"]*)")[^>]*>(.*?)<\/a>/i';
        if (preg_match_all($pattern, $htmlText, $matches)) {
            foreach ($matches[2] as $index => $linkText) {
                $replacement = '<a class="'.$className
                    .'" href="#'.$prefix.$linkText.'" style="color: blue; text-decoration: underline;">'
                    .$prefix.$linkText.'</a>';
                $htmlText = str_replace($matches[0][$index], $replacement, $htmlText);
            }
        }

        return $htmlText;
    }

    /**
     * Removes all <a> tags with the specified class from the given HTML text.
     * The inner text of the removed tags is replaced with the specified prefix.
     */
    public function removeLinkTagsByClass(string $htmlText, string $className, string $prefix): string
    {
        // Regex pattern to match <a> tags with the specified class and capture their inner text
        $pattern = '/<a\b[^>]*class="[^"]*\b'.preg_quote($className, '/').'\b[^"]*"[^>]*>(.*?)<\/a>/i';
        // Replacement string to keep only the inner text
        $replacement = $prefix.'$1';

        return preg_replace($pattern, $replacement, $htmlText);
    }
}
