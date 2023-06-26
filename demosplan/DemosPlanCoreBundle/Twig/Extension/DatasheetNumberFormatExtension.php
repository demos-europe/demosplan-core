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
use Twig\Environment;
use Twig\TwigFilter;

class DatasheetNumberFormatExtension extends ExtensionBase
{
    /**
     * @var Environment
     */
    private $twig;

    public function __construct(ContainerInterface $container, Environment $twig)
    {
        parent::__construct($container);
        $this->twig = $twig;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('datasheet_number_format', [&$this, 'numberFormatFilter']),
        ];
    }

    public function numberFormatFilter($number, $decimal)
    {
        if (!is_numeric($number)) {
            return $number;
        } else {
            if (function_exists('twig_number_format_filter')) {
                return twig_number_format_filter($this->twig, $number, $decimal, ',', '.');
            }

            return 0;
        }
    }
}
