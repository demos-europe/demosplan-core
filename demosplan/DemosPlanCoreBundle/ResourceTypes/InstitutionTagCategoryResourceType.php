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

use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\AccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InstitutionTagCategoryResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @property-read CustomerResourceType $customer
 */
class InstitutionTagCategoryResourceType extends DplanResourceType
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly InstitutionTagRepository $institutionTagRepository,
    ) {
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
        $institutionTagCategoryConfig = $this->getConfig(InstitutionTagCategoryResourceConfigBuilder::class);
        $institutionTagCategoryConfig->id
            ->setReadableByPath();
        $institutionTagCategoryConfig->name
            ->setReadableByPath()
            ->addPathCreationBehavior()
            ->addPathUpdateBehavior();

        $institutionTagCategoryConfig->customer
            ->setRelationshipType($this->resourceTypeStore->getCustomerResourceType());

        $institutionTagCategoryConfig->tags
            ->setRelationshipType($this->getTypes()->getInstitutionTagResourceType())
            ->setReadableByPath();

        $institutionTagCategoryConfig->creationDate
            ->setReadableByPath();

        $institutionTagCategoryConfig->addPostConstructorBehavior(
            new FixedSetBehavior(
                function (InstitutionTagCategory $institutionTagCategory): array {
                    $institutionTagCategory->setCustomer($this->currentCustomerService->getCurrentCustomer());
                    $this->institutionTagRepository->persistEntities([$institutionTagCategory]);

                    return [];
                }
            )
        );

        return $institutionTagCategoryConfig;
    }

    public function isAvailable(): bool
    {
        return $this->accessChecker->isAvailable();
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_update');
    }

    protected function getAccessConditions(): array
    {
        return $this->accessChecker->getCategoryAccessConditions();
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_create');
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_delete');
    }

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_read');
    }
}
