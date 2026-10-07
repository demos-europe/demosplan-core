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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
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

    public function findCustomFieldByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity, string $customFieldId): ?CustomFieldInterface
    {
        foreach ($this->getCustomFieldsByCriteria($sourceEntity, $sourceEntityId, $targetEntity) as $customField) {
            if ($customField->getId() === $customFieldId) {
                return $customField;
            }
        }

        return null;
    }

    /**
     * @param string[] $customFieldIds
     *
     * @return array<string, string> Display label keyed by custom field id
     */
    public function getCustomFieldLabelsByIds(array $customFieldIds): array
    {
        $labels = [];
        foreach ($this->getCustomFieldConfigurationsByIds($customFieldIds) as $customFieldConfiguration) {
            $labels[$customFieldConfiguration->getId()] = $customFieldConfiguration->getConfiguration()->getName();
        }

        return $labels;
    }

    /**
     * @param string[] $customFieldIds
     *
     * @return CustomFieldConfiguration[]
     */
    private function getCustomFieldConfigurationsByIds(array $customFieldIds): array
    {
        return $this->customFieldConfigurationRepository->findBy(['id' => $customFieldIds]);
    }
}
