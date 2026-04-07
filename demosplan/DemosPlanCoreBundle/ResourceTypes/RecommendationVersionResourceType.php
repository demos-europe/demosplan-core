<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\RecommendationVersionResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * Read-only ResourceType for recommendation version history entries.
 *
 * Each entry stores the OLD recommendation text before a specific update.
 * The current recommendation is always on the Statement/Segment entity itself.
 *
 * @template-extends DplanResourceType<RecommendationVersion>
 */
final class RecommendationVersionResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'RecommendationVersion';
    }

    public function getEntityClass(): string
    {
        return RecommendationVersion::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_enable_recommendation_versions');
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(RecommendationVersionResourceConfigBuilder::class);

        $configBuilder->id->setReadableByPath();
        $configBuilder->versionNumber->setReadableByPath()->setFilterable()->setSortable();
        $configBuilder->recommendationText->setReadableByPath();
        $configBuilder->createdAt->setReadableByPath()->setFilterable()->setSortable();
        $configBuilder->statement
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
            ->setReadableByPath()
            ->setFilterable();

        return $configBuilder;
    }
}
