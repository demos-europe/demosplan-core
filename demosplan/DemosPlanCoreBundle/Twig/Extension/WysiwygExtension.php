<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Psr\Container\ContainerInterface;
use Twig\TwigFilter;

/**
 * Wysiwyg-Editor.
 */
class WysiwygExtension extends ExtensionBase
{
    public function __construct(ContainerInterface $container, private readonly HTMLSanitizer $htmlSanitizer)
    {
        parent::__construct($container);
    }

    /* (non-PHPdoc)
     * @see Twig_Extension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('wysiwyg', $this->wysiwygFilter(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * HTML-Filter fuer Eingaben aus dem WYSIWYG-Editor.
     *
     * @param string $text
     * @param array  $additionalAllowedTags
     * @param bool   $purify
     *
     * @return string
     */
    public function wysiwygFilter($text, $additionalAllowedTags = [], $purify = false)
    {
        return $this->htmlSanitizer->wysiwygFilter($text, $additionalAllowedTags, $purify);
    }
}
