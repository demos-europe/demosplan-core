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

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InstitutionTagCategoryResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

class InstitutionTagCategoryResourceType extends DplanResourceType
{
    public function __construct(private readonly InstitutionTagRepository $institutionTagRepository)
    {
    }

    public static function getName(): string
    {
        return 'InstitutionTagCategory';
    }

    public function getEntityClass(): string
    {
        return InstitutionTagCategory::class;
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(InstitutionTagCategoryResourceConfigBuilder::class);
        $configBuilder->id
            ->setReadableByPath();
        $configBuilder->name
            ->setReadableByPath()
            ->addPathCreationBehavior();

        $configBuilder->customer
            ->setRelationshipType($this->resourceTypeStore->getCustomerResourceType());

        $configBuilder->tags
            ->setRelationshipType($this->getTypes()->getInstitutionTagResourceType())
            ->setReadableByPath();

        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(function (InstitutionTagCategory $institutionTagCategory, EntityDataInterface $entityData): array {
            $institutionTagCategory->setCustomer($this->currentCustomerService->getCurrentCustomer());
            $this->institutionTagRepository->persistEntities([$institutionTagCategory]);

            return [];
        }));

        return $configBuilder;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isUpdateAllowed(): bool
    {
        return true;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
    }

    public function isCreateAllowed(): bool
    {
        return true;
    }

    public function isDeleteAllowed(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return true;
    }
}
