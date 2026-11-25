<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use RuntimeException;

class HTMLSanitizer
{
    private const ELEMENT = '/<([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>/';
    private const ATTRIBUTE = '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=/';

    /**
     * @var string
     */
    private $cacheDirectory;

    public function __construct(private readonly HTMLPurifier $htmlPurifier, string $cacheDirectory)
    {
        // uses local file, no need for flysystem
        // Make sure the cache directory exists, as the purifier won't create it for you
        if (!file_exists($cacheDirectory) && !mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
            throw new RuntimeException(sprintf('HTML purifier directory "%s" can not be created', $cacheDirectory));
        }
        $this->cacheDirectory = $cacheDirectory;
    }

    /**
     * Filter input for wysiwyg output.
     *
     * @param string $text
     * @param array  $additionalAllowedTags
     * @param bool   $purify
     */
    public function wysiwygFilter($text, $additionalAllowedTags = [], $purify = false): string
    {
        $allowedTags = collect(
            [
                'a',
                'abbr',
                'b',
                'br',
                'del',
                'em',
                'i',
                'img',
                'ins',
                'li',
                'mark',
                'ol',
                'p',
                's',
                'span',
                'strike',
                'strong',
                'sup',
                'table',
                'td',
                'th',
                'thead',
                'tr',
                'u',
                'ul',
            ]
        )
            ->merge($additionalAllowedTags)
            ->flatMap(
                // format as tags, as strip_tags() needs tags formatted as "<a>"
                static fn ($tagName) => ["<{$tagName}>"]
            )
            ->implode('');

        $text = strip_tags($text, $allowedTags);
        $text = $this->sanitizeCssForPhpWord($text);

        if ($purify) {
            $text = $this->purify($text);
        }

        return $text;
    }

    /**
     * Remove CSS property values that PHPWord cannot handle.
     * PHPWord tries to multiply line-height values as numbers, but CSS keywords
     * like "inherit", "normal", or "initial" cause a TypeError.
     */
    public function sanitizeCssForPhpWord(string $text): string
    {
        return preg_replace(
            '/line-height:\s*(inherit|normal|initial|unset)\b[^;"\']*/i',
            'line-height: 1',
            $text
        );
    }

    public function purify(string $text): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.DefinitionID', 'dplan purifier');
        $config->set('HTML.DefinitionRev', 1);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['noopener noreferrer nofollow']);
        $config->set('Cache.SerializerPath', $this->cacheDirectory);
        $def = $config->maybeGetRawHTMLDefinition();

        if (null !== $def) {
            $def->addElement(
                'dp-obscure',   // name
                'Inline',  // content set
                'Flow', // allowed children
                'Common', // attribute collection
            );
            $def->addElement(
                'mark',   // name
                'Inline',  // content set
                'Flow', // allowed children
                'Common', // attribute collection
            );
        }

        return $this->htmlPurifier->purify($text, $config);
    }

    /**
     * Whether filtering dropped elements or attributes. The filters also rewrite markup they keep
     * (`<br>` becomes `<br />`, colours are normalised), so only element and attribute names count.
     */
    public function removesMarkup(string $before, string $after): bool
    {
        $kept = $this->countMarkupNames($after);
        foreach ($this->countMarkupNames($before) as $name => $count) {
            if (($kept[$name] ?? 0) < $count) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int> element and attribute names (`element`, `element@attribute`) with their counts
     */
    private function countMarkupNames(string $html): array
    {
        $names = [];
        preg_match_all(self::ELEMENT, $html, $elements, PREG_SET_ORDER);
        foreach ($elements as [, $element, $attributes]) {
            $element = mb_strtolower($element);
            $names[] = $element;
            preg_match_all(self::ATTRIBUTE, $attributes, $matches);
            foreach ($matches[1] as $attribute) {
                $names[] = $element.'@'.mb_strtolower($attribute);
            }
        }

        return array_count_values($names);
    }
}
