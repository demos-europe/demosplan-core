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
    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    public function getHtmlValidText(string $text): string
    {
        /** @var string $text $text */
        $text = str_replace('<br>', '<br/>', $text);

        // strip all a tags without href
        $pattern = '/<a\s+(?!.*?\bhref\s*=\s*([\'"])\S*\1)(.*?)>(.*?)<\/a>/i';
        $text = preg_replace($pattern, '$3', $text);

        // avoid problems in phpword parser
        return $this->htmlSanitizer->purify($text);
    }
}
