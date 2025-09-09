<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Twig\TwigFilter;

/**
 * Base64-Encode.
 */
class Base64Extension extends ExtensionBase
{
    /* (non-PHPdoc)
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('base64', $this->base64Filter(...)),
        ];
    }

    /**
     * Base64 Encode.
     *
     * @param string $text
     *
     * @return string
     */
    public function base64Filter($text)
    {
        if (!is_string($text)) {
            return '';
        }

        return base64_encode($text);
    }
}
