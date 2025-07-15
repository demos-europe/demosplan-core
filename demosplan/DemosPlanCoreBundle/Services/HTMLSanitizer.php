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

        if ($purify) {
            $text = $this->purify($text);
        }

        return $text;
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
}
