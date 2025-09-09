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

class IsNotExtension extends ExtensionBase
{
    /**
     * (non-PHPdoc).
     *
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('isNot', $this->isNotDefined(...)),
        ];
    }

    /**
     * Check whether value is not defined, then return given return value.
     *
     * @param string $text
     * @param string $return
     *
     * @return string
     */
    public function isNotDefined($text = null, $return = '')
    {
        if (null === $text || (is_string($text) && '' === $text)) {
            return $return;
        }

        return '';
    }
}
