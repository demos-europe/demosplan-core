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
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Extension\AbstractExtension;

abstract class ExtensionBase extends AbstractExtension implements ServiceSubscriberInterface
{
    /**
     * This is not the full fledged symfony container but a mini version
     * https://symfonycasts.com/screencast/symfony-doctrine/service-subscriber.
     *
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getName(): string
    {
        $class = explode(
            '\\',
            static::class
        );

        return lcfirst(
            str_replace(
                'Extension',
                '_extension',
                array_pop(
                    $class
                )
            )
        );
    }

    public static function getSubscribedServices(): array
    {
        return [];
    }
}
