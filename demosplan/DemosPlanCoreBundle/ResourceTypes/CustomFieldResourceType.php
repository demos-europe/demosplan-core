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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldJsonRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\AllAttributesTransformer;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldConfigBuilder;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldDeleter;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\ResourceConfigInterface;
use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
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
 * @property-read End $sourceEntityId
 * @property-read End $options
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
        private readonly CustomFieldUpdater $customFieldUpdater,
        private readonly CustomFieldDeleter $customFieldDeleter,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $customerService, )
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
        $currentCustomer = $this->customerService->getCurrentCustomer();

        /*
         * Defensive: if no current customer is in scope (which should not happen
         * for routes that pass our permission gates), return an always-false
         * condition rather than leak data.
         */
        if (null === $currentCustomer) {
            return [$this->conditionFactory->false()];
        }

        /*
         * For CUSTOMER source the row must belong to the current customer.
         * Non-CUSTOMER sources (PROCEDURE etc.) keep their existing behaviour
         * — their sourceEntityId is still supplied by the caller as a filter.
         */
        return [
            $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->propertyHasNotValue(
                    CustomFieldSupportedEntity::customer->value,
                    $this->sourceEntity
                ),
                $this->conditionFactory->allConditionsApply(
                    $this->conditionFactory->propertyHasValue(
                        CustomFieldSupportedEntity::customer->value,
                        $this->sourceEntity
                    ),
                    $this->conditionFactory->propertyHasValue(
                        $currentCustomer->getId(),
                        $this->sourceEntityId
                    )
                )
            ),
        ];
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
        $configBuilder->name->setReadableByPath(DefaultField::YES)->addPathCreationBehavior()->addPathUpdateBehavior();
        $configBuilder->isRequired
            ->setReadableByCallable(
                static fn (CustomFieldInterface $customField): bool => $customField->getRequired()
            )
            ->addPathCreationBehavior(OptionalField::YES)
            ->addPathUpdateBehavior();
        $configBuilder->fieldType->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->options
            ->setReadableByCallable(
                static fn (CustomFieldInterface $customField): array => array_map(
                    static fn (CustomFieldOption $option) => $option->toJson(),
                    $customField->getOptions()
                )
            )
            ->addPathCreationBehavior()
            ->addPathUpdateBehavior();
        $configBuilder->description->setReadableByPath()->addPathCreationBehavior()->addPathUpdateBehavior();
        $configBuilder->targetEntity->setAliasedPath(['targetEntityClass'])->addPathCreationBehavior()->setReadableByPath()->setFilterable();
        $configBuilder->sourceEntity->setAliasedPath(['sourceEntityClass'])->addPathCreationBehavior()->setReadableByPath()->setFilterable();
        $configBuilder->sourceEntityId->addPathCreationBehavior()->setFilterable();

        return $configBuilder->build();
    }

    protected function getIdentifierPropertyPath(): array
    {
        return ['name'];
    }

    public function getTransformer(): TransformerAbstract
    {
        // Use our custom transformer that returns all attributes
        // This ensures all fields are included in the response for newly created entities
        return new AllAttributesTransformer(
            $this->getTypeName(),
            $this->getEntityClass(),
            $this->getReadability(),
            $this->messageFormatter,
            $this->logger,
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
            $this->customFieldConfigurationRepository
        );
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_custom_fields');
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_custom_fields');
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
        return $this->currentUser->hasAnyPermissions('area_admin_custom_fields', 'feature_statements_custom_fields');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_custom_fields');
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

                    /*
                      * For CUSTOMER source the sourceEntityId is always the current
                      * customer. Server-side override removes that responsibility from
                      * the frontend and prevents cross-customer creation.
                      */

                    if (CustomFieldSupportedEntity::customer->value === $attributes['sourceEntity']) {
                        $attributes['sourceEntityId'] = $this->customerService->getCurrentCustomer()->getId();
                    }

                    $customField = $this->customFieldCreator->createCustomField($attributes);

                    // Using AllAttributesTransformer which always returns all attributes
                    // No need to list attributes here as our custom transformer handles that
                    return new ModifiedEntity($customField, [ContentField::ID]);
                }
            );
        } catch (Exception $exception) {
            $this->addCreationErrorMessage([]);

            throw $exception;
        }
    }

    public function updateEntity(string $entityId, EntityDataInterface $entityData): ModifiedEntity
    {
        // Update the fields from the request, and deletes non included but previously persisted options and removes their usages from segments
        $attributes = $entityData->getAttributes();
        $customField = $this->customFieldUpdater->updateCustomField($entityId, $attributes);

        return new ModifiedEntity($customField, ['name', 'description', 'options']);
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->customFieldDeleter->deleteCustomField($entityIdentifier);
    }
}
