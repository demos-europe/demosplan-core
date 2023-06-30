<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BrandingProvider
{
    private const PROD_EXPIRATION_DATE = 3600;
    private const DEV_EXPIRATION_DATE = 10;

    public function __construct(private readonly CacheInterface $cache, private readonly GlobalConfigInterface $globalConfig, private readonly LoggerInterface $logger)
    {
    }

    /**
     * generates the full CSS from the saved yml-formatted variables and caches them for further use
     * in a specific cache property.
     *
     * @param Customer|Orga $entity
     */
    public function generateFullCss(CoreEntity $entity): ?string
    {
        // Only Customers and Orgas can have brandings
        if ((!$entity instanceof Customer && !$entity instanceof Orga) || null === $entity->getBranding()) {
            return null;
        }

        $cssVars = $entity->getBranding()->getCssvars();
        $cacheName = sprintf('cssvars_%s', $entity->getId());
        try {
            return $this->cache->get($cacheName, function (ItemInterface $item) use ($cssVars) {
                $this->logger->info('Build css vars from yml formatted string');

                $parsedVars = Yaml::parse($cssVars ?? '') ?? [];
                $fullCss = ":root {\n";
                foreach ($parsedVars as $var => $value) {
                    $fullCss .= '--dp-token-color-brand-'.$var.': '.$value.";\n";
                }
                $fullCss .= '}';

                // set long ttl only in prod mode to improve DX in dev mode when working with css vars
                $ttl = $this->globalConfig->isProdMode() ? self::PROD_EXPIRATION_DATE : self::DEV_EXPIRATION_DATE;

                $this->logger->info('Save built css vars into cache with ttl '.$ttl);
                $item->expiresAfter($ttl);

                return $fullCss;
            });
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
