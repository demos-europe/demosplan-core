<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;

class CustomFieldProvider
{
    /** @var array<string, ArrayCollection> */
    private array $cache = [];

    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
    ) {
    }

    public function getCustomFieldsByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity): ArrayCollection
    {
        $cacheKey = $sourceEntity.'|'.$sourceEntityId.'|'.$targetEntity;

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->customFieldConfigurationRepository->getCustomFields($sourceEntity, $sourceEntityId, $targetEntity);
        }

        return $this->cache[$cacheKey];
    }
}
