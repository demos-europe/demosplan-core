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

    /**
     * Memoizes {@see getHtmlValidText()} results keyed by a hash of the raw input. Sanitizing is
     * deterministic and, during a segment export, the same string (empty recommendations, boilerplate
     * phrases) is passed thousands of times. HTMLPurifier is the dominant export cost, so collapsing
     * duplicate inputs to a single purify call cuts it to one call per distinct string.
     *
     * @var array<string, string>
     */
    private array $validTextCache = [];

    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    public function getHtmlValidText(string $text): string
    {
        $cacheKey = md5($text);
        if (isset($this->validTextCache[$cacheKey])) {
            return $this->validTextCache[$cacheKey];
        }

        /** @var string $validText */
        $validText = str_replace('<br>', '<br/>', $text);

        // strip all a tags without href
        $pattern = '/<a(?![^>]*\bhref=)([^>]*)>(.*?)<\/a>/i';
        $validText = preg_replace($pattern, '$2', $validText);

        // avoid problems in phpword parser
        return $this->validTextCache[$cacheKey] = $this->htmlSanitizer->purify($validText);
    }

    /**
     * Extracts image references from the given HTML text. The $class parameter identifies legacy anchor-form
     * image references `<a class="…" href="…">label</a>` (the form is required to disambiguate from real
     * hyperlinks). All `<img src="…" alt="label">` tags are extracted regardless of class — for an `<img>`
     * there's no ambiguity, every `<img>` in statement text is an image reference.
     *
     * @return array<int, ImageReference> an array containing the extracted ImageReference objects
     */
    public function extractImageDataByClass(string $htmlText, string $class, string $prefix): array
    {
        $imageReferences = [];

        $anchorPattern = '/<a\b(?=[^>]*\bclass="[^"]*\b'.preg_quote($class, '/').'\b[^"]*")(?=[^>]*\bhref="([^"]*)")[^>]*>(.*?)<\/a>/i';
        if (preg_match_all($anchorPattern, $htmlText, $matches)) {
            foreach ($matches[1] as $index => $url) {
                $linkText = $matches[2][$index];
                $srcParts = explode('/', $url);
                $hash = $srcParts[array_key_last($srcParts)];
                $imageReferences[] = new ImageReference($prefix.$linkText, $url, $hash);
            }
        }

        $imgPattern = '/<img\b(?=[^>]*\bsrc="([^"]*)")[^>]*\/?>/i';
        if (preg_match_all($imgPattern, $htmlText, $matches)) {
            foreach ($matches[0] as $index => $fullTag) {
                $src = $matches[1][$index];
                $alt = $this->extractAttribute($fullTag, 'alt');
                $srcParts = explode('/', $src);
                $hash = $srcParts[array_key_last($srcParts)];
                $imageReferences[] = new ImageReference($prefix.$alt, $src, $hash);
            }
        }

        return $imageReferences;
    }

    /**
     * Rewrites image references to styled cross-reference anchors suitable for DOCX export. Matches the
     * legacy anchor form `<a class="…" href="…">label</a>` (identified by $className) and any
     * `<img src="…" alt="label">` tag (matched regardless of class).
     */
    public function updateLinkTextWithClass(string $htmlText, string $className, string $prefix): string
    {
        $anchorPattern = '/<a\b(?=[^>]*\bclass="[^"]*\b'
            .preg_quote($className, '/').'\b[^"]*")(?=[^>]*\bhref="([^"]*)")[^>]*>(.*?)<\/a>/i';
        if (preg_match_all($anchorPattern, $htmlText, $matches)) {
            foreach ($matches[2] as $index => $linkText) {
                $htmlText = str_replace(
                    $matches[0][$index],
                    $this->buildCrossReferenceAnchor($className, $prefix.$linkText),
                    $htmlText
                );
            }
        }

        $imgPattern = '/<img\b[^>]*\/?>/i';
        if (preg_match_all($imgPattern, $htmlText, $matches)) {
            foreach ($matches[0] as $fullTag) {
                $label = $this->extractAttribute($fullTag, 'alt');
                $htmlText = str_replace(
                    $fullTag,
                    $this->buildCrossReferenceAnchor($className, $prefix.$label),
                    $htmlText
                );
            }
        }

        return $htmlText;
    }

    /**
     * Strips anchor-form image references (identified by $className) and all `<img>` tags. The label
     * (anchor inner text or img alt) is kept inline, prefixed with $prefix.
     */
    public function removeLinkTagsByClass(string $htmlText, string $className, string $prefix): string
    {
        $anchorPattern = '/<a\b[^>]*class="[^"]*\b'.preg_quote($className, '/').'\b[^"]*"[^>]*>(.*?)<\/a>/i';
        $htmlText = preg_replace($anchorPattern, $prefix.'$1', $htmlText);

        $imgPattern = '/<img\b[^>]*\/?>/i';
        if (preg_match_all($imgPattern, $htmlText, $matches)) {
            foreach ($matches[0] as $fullTag) {
                $alt = $this->extractAttribute($fullTag, 'alt');
                $htmlText = str_replace($fullTag, $prefix.$alt, $htmlText);
            }
        }

        return $htmlText;
    }

    private function buildCrossReferenceAnchor(string $className, string $label): string
    {
        return '<a class="'.$className.'" href="#'.$label.'" style="color: blue; text-decoration: underline;">'.$label.'</a>';
    }

    private function extractAttribute(string $tagHtml, string $attribute): string
    {
        if (preg_match('/\b'.preg_quote($attribute, '/').'="([^"]*)"/i', $tagHtml, $match)) {
            return $match[1];
        }

        return '';
    }
}
