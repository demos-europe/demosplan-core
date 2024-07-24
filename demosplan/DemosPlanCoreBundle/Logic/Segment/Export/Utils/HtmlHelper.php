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
    public const LINK_CLASS_FOR_DARSTELLUNG_STELL = 'darstellung';
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
     * creates ImageReference objects from those URLs.
     *
     * @param string $htmlText The input HTML text.
     * @param string $class The class name to look for within the <a> tags.
     * @return array<int, ImageReference> An array containing the extracted ImageReference objects.
     */
    public function extractImageDataByClass(string $htmlText, string $class): array
    {
        $imageReferences = [];

        // The regex pattern to match <a> tags with the specified class and extract their href attributes and link text
        $pattern =
            '/<a\b[^>]*class="[^"]*\b'.preg_quote($class, '/').'\b[^"]*"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i';

        // Perform the regex match
        if (preg_match_all($pattern, $htmlText, $matches)) {
            foreach ($matches[1] as $index => $url) {
                $linkText = $matches[2][$index];
                // Create an ImageReference object with linkText as imageReference and url as imagePath
                $imageReference = new ImageReference($linkText, $url);
                $imageReferences[] = $imageReference;
            }
        }

        return $imageReferences;
    }

    /**
     * Updates the link text of all links with the specified class by appending a prefix.
     *
     * @param string $htmlText The input HTML text.
     * @param string $className The class name to look for.
     * @param string $prefix The prefix to add to the link texts.
     * @return string The updated HTML text.
     */
    public function updateLinkTextWithClass(string $htmlText, string $className, string $prefix): string
    {
        $pattern = '/(<a\b[^>]*class="[^"]*\b'.preg_quote($className, '/').'\b[^"]*"[^>]*>)(.*?)(<\/a>)/i';
        $replacement = '$1' . $prefix . '$2' . '$3';
        return preg_replace($pattern, $replacement, $htmlText);
    }
}
