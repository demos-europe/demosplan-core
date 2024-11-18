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

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\PlaningDocumentCategoryResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * This ResourceType can be used to retrieve information from all Elements,
 * including hidden elements.
 *
 * Note: If you need access to only visible elements, you can use the
 * {@link PlanningDocumentCategoryResourceType}.
 */
class PlanningDocumentCategoryDetailsResourceType extends DplanResourceType
{
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $planningDocumentCategoryTitleConfig = $this->getConfig(PlaningDocumentCategoryResourceConfigBuilder::class);
        $planningDocumentCategoryTitleConfig->id->setReadableByPath();
        $planningDocumentCategoryTitleConfig->title->setReadableByPath();
        $planningDocumentCategoryTitleConfig->procedure->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())->setFilterable();
        $planningDocumentCategoryTitleConfig->paragraphs->setRelationshipType($this->resourceTypeStore->getParagraphResourceType())
            ->setReadableByPath();
        $planningDocumentCategoryTitleConfig->documents->setRelationshipType($this->resourceTypeStore->getSingleDocumentResourceType())
            ->setReadableByPath();

        return $planningDocumentCategoryTitleConfig;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public static function getName(): string
    {
        return 'ElementsDetails';
    }

    public function getEntityClass(): string
    {
        return Elements::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
