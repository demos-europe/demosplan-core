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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseInstitutionTagResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InstitutionTagCategoryResourceConfigBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\CallbackToOneRelationshipSetBehavior;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
