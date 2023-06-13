<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Psr\Container\ContainerInterface;
use Twig\TwigFilter;

/**
 * CSS/Class Prefixer.
 */
class PrefixClassExtension extends ExtensionBase
{
    /** @var string */
    private $prefixClass;

    public function __construct(ContainerInterface $container, string $prefixClass)
    {
        $this->prefixClass = $prefixClass;
        parent::__construct($container);
    }

    /* (non-PHPdoc)
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('prefixClass', [$this, 'prefixClass']),
        ];
    }

    /**
     * Adds a CSS Prefix from parameters yml to css-classes.
     *
     * @param string $classList
     * @param bool   $omitPrefix
     *
     * @return string
     */
    public function prefixClass($classList = '', $omitPrefix = false)
    {
        if (true === $omitPrefix) {
            return $classList;
        }
        $prefix = $this->prefixClass;

        // Assuming that a querySelector is passed when classList contains a dot, only the class selector parts are prefixed.
        // In the unlikely case that classes contain dots as part of their names, they will not be prefixed.
        if (false !== strpos($classList, '.')) {
            return preg_replace_callback(
                '/(\S+)/',
                function ($match) use ($prefix) {
                    // Only prefix matches that start with a dot
                    return 0 === strpos($match[0], '.') ? '.'.$prefix.substr($match[0], 1) : $match[0];
                },
                $classList
            );
        }

        return preg_replace('/(\S+)/', $prefix.'$1', $classList);
    }
}
