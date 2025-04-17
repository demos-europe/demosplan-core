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

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiPaginationInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\DoctrineResourceTypeInjectionTrait;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldJsonRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldConfigBuilder;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\ResourceConfigInterface;
use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Querying\Utilities\Reindexer;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\ResourceBehavior\ResourceInstantiability;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use EDT\Wrapping\ResourceBehavior\ResourceUpdatability;
use Exception;
use IteratorAggregate;
use League\Fractal\TransformerAbstract;
use Pagerfanta\Pagerfanta;

/** LEARNINGS
 * implementing PropertyAutoPathInterface makes it available to attributes from the entity.
 */

/**
 * @template-extends DplanResourceType<CustomField>
 *
 * @property-read End $name
 * @property-read End $description
 * @property-read End $targetEntity
 * @property-read End $sourceEntity
 *
 * @method bool isNullSafe(int $index)
 */
final class CustomFieldResourceType extends AbstractResourceType implements JsonApiResourceTypeInterface, PropertyPathInterface, IteratorAggregate, PropertyAutoPathInterface
{
    use PropertyAutoPathTrait;
    use DoctrineResourceTypeInjectionTrait;

    public function __construct(
        protected readonly DqlConditionFactory $conditionFactory,
        private readonly CustomFieldCreator $customFieldCreator,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly Reindexer $reindexer,
        private readonly CurrentUserInterface $currentUser)
    {
    }

    public function getEntityClass(): string
    {
        return CustomFieldInterface::class;
    }

    public function getTypeName(): string
    {
        return 'CustomField';
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getInstantiability(): ResourceInstantiability
    {
        return $this->getResourceConfig()->getInstantiability();
    }

    protected function getDefaultSortMethods(): array
    {
        return [];
    }

    protected function getResourceConfig(): ResourceConfigInterface
    {
        $configBuilder = new CustomFieldConfigBuilder(
            $this->getEntityClass(),
            $this->propertyBuilderFactory
        );

        $configBuilder->id->setReadableByPath();
        $configBuilder->name->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->fieldType->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->options->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->description->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->targetEntity->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->sourceEntity->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->sourceEntityId->setReadableByPath()->addPathCreationBehavior();

        return $configBuilder->build();
    }

    protected function getIdentifierPropertyPath(): array
    {
        return ['name'];
    }

    public function getTransformer(): TransformerAbstract
    {
        return new DynamicTransformer(
            $this->getTypeName(),
            $this->getEntityClass(),
            $this->getReadability(),
            $this->messageFormatter,
            null
        );
    }

    public function isAvailable(): bool
    {
        return true;
    }

    protected function getRepository(): RepositoryInterface
    {
        return new CustomFieldJsonRepository(
            $this->getEntityManager(),
            $this->conditionFactory,
            $this->reindexer,
            $this->customFieldConfigurationRepository
        );
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_custom_fields');
    }

    public function isDeleteAllowed(): bool
    {
        return false;
    }

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_custom_fields');
    }

    public function addCreationErrorMessage(array $parameters): void
    {
        $this->getJsonApiResourceTypeService()->addCreationErrorMessage($parameters);
    }

    public function getUpdateValidationGroups(): array
    {
        return [ProcedureInterface::VALIDATION_GROUP_DEFAULT];
    }

    public function getCreationValidationGroups(): array
    {
        return [ProcedureInterface::VALIDATION_GROUP_DEFAULT];
    }

    public function getReadability(): ResourceReadability
    {
        return $this->getResourceConfig()->getReadability();
    }

    public function getFilteringProperties(): array
    {
        return $this->getResourceConfig()->getFilteringProperties();
    }

    public function getSortingProperties(): array
    {
        return $this->getResourceConfig()->getSortingProperties();
    }

    public function getEntityPaginator(ApiPaginationInterface $pagination, array $conditions, array $sortMethods = []): Pagerfanta
    {
        throw AccessException::typeNotAvailable($this);
    }

    public function listPrefilteredEntities(array $dataObjects, array $conditions = [], array $sortMethods = []): array
    {
        throw AccessException::typeNotAvailable($this);
    }

    public function getEntityCount(array $conditions): int
    {
        throw AccessException::typeNotAvailable($this);
    }

    public function listEntityIdentifiers(array $conditions, array $sortMethods): array
    {
        throw AccessException::typeNotAvailable($this);
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return false;
    }

    public function getUpdatability(): ResourceUpdatability
    {
        return $this->getResourceConfig()->getUpdatability();
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $attributes = $entityData->getAttributes();
                    $customField = $this->customFieldCreator->createCustomField($attributes);

                    return new ModifiedEntity($customField, []);
                }
            );
        } catch (Exception $exception) {
            $this->addCreationErrorMessage([]);

            throw $exception;
        }
    }
}
