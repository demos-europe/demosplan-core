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
     * Extracts URLs from the given HTML text that are contained within <a> tags with the specified class.
     *
     * @return array<int, string> An array containing the extracted URLs.
     */
    public static function extractUrlsByClass(string $htmlText, string $class): array
    {
        $urls = [];

        // The regex pattern to match <a> tags with class="darstellung" and extract their href attributes
        // It looks for <a> tags with any attributes, but specifically captures a tag if it has the class 'darstellung'
        // and then captures the value of the href attribute
        $pattern = '/<a\b[^>]*class="[^"]*\b'.$class.'\b[^"]*"[^>]*href="([^"]*)"[^>]*>/i';

        // Perform the regex match
        if (preg_match_all($pattern, $htmlText, $matches)) {
            $urls = $matches[1];  // Extract the URLs from the matches
        }

        return $urls;
    }
}
