<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ElasticsearchStatementCustomFieldLabelListener implements EventSubscriberInterface
{
    /**
     * In-memory cache to avoid repeated DB lookups during bulk indexing runs.
     * Keyed by CustomFieldConfiguration UUID.
     *
     * @var array<string, CustomFieldConfiguration|null>
     */
    private array $configCache = [];

    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
    ) {
    }

    public function enrichDocument(PostTransformEvent $event): void
    {
        $statement = $event->getObject();

        if (!$statement instanceof Statement) {
            return;
        }

        $customFields = $statement->getCustomFields();
        if (null === $customFields || $customFields->isEmpty()) {
            return;
        }

        $labelMap = [];

        foreach ($customFields->getCustomFieldsValues() as $customFieldValue) {
            $fieldConfigId = $customFieldValue->getId();
            $selectedValue = $customFieldValue->getValue();

            if (null === $selectedValue) {
                continue;
            }

            $config = $this->loadConfig($fieldConfigId);
            if (null === $config) {
                continue;
            }

            $label = $this->resolveLabel($config, $selectedValue);
            if ('' !== $label) {
                $labelMap[$fieldConfigId] = $label;
            }
        }

        if ([] !== $labelMap) {
            $event->getDocument()->set('customFieldsLabel', $labelMap);
        }
    }

    private function loadConfig(string $fieldConfigId): ?CustomFieldConfiguration
    {
        if (!array_key_exists($fieldConfigId, $this->configCache)) {
            $this->configCache[$fieldConfigId] = $this->customFieldConfigurationRepository->find($fieldConfigId);
        }

        return $this->configCache[$fieldConfigId];
    }

    /**
     * Resolves the label of the selected option for sort purposes.
     * For multi-select fields the label of the first selected option is used.
     */
    private function resolveLabel(CustomFieldConfiguration $config, mixed $selectedValue): string
    {
        $options = $config->getConfiguration()->getOptions();

        $optionId = is_array($selectedValue) ? ($selectedValue[0] ?? null) : $selectedValue;
        if (null === $optionId) {
            return '';
        }

        foreach ($options as $option) {
            if ($option->getId() === $optionId) {
                return $option->getLabel();
            }
        }

        return '';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'enrichDocument',
        ];
    }
}
