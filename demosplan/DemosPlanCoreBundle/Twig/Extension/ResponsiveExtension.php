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
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\TwigFilter;

/**
 * Outputescaping mit Ausnahme von für resonsive benötigten Elementen.
 */
class ResponsiveExtension extends ExtensionBase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        parent::__construct($container);
        $this->logger = $logger;
    }

    /* (non-PHPdoc)
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'responsive', [$this, 'responsiveFilter'],
                ['needs_environment' => true, 'is_safe' => ['html']]
            ),
        ];
    }

    /**
     * Outputescaping mit Ausnahme von für resonsive benötigten Elementen.
     *
     * @param string $string
     *
     * @return string
     */
    public function responsiveFilter(Environment $env, $string)
    {
        $charset = $env->getCharset();

        // Escape den String je nach Charset
        $htmlspecialcharsCharsets = ['utf-8' => true, 'UTF-8' => true];
        if (isset($htmlspecialcharsCharsets[$charset])) {
            $string = htmlspecialchars(
                $string,
                ENT_QUOTES | ENT_SUBSTITUTE,
                $charset
            );
        } else {
            $this->logger->warning('Unkown Charset: "'.$charset.'"');
            $string = htmlspecialchars(
                $string,
                ENT_QUOTES | ENT_SUBSTITUTE
            );
        }

        // Erlaube definierte Zeichen

        // &shy;
        $string = preg_replace('/&amp;shy;/', '&shy;', $string);

        return $string;
    }
}
