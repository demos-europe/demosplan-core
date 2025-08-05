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
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldJsonRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\AllAttributesTransformer;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldConfigBuilder;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\ResourceConfigInterface;
use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Querying\Utilities\Reindexer;
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
use Ramsey\Uuid\Uuid;

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
        $configBuilder->name->setReadableByPath(DefaultField::YES)->addPathCreationBehavior()->addPathUpdateBehavior();
        $configBuilder->fieldType->setReadableByPath()->addPathCreationBehavior();
        $configBuilder->options
            ->setReadableByCallable(
                static fn(CustomFieldInterface $customField): array =>
                array_map(static fn($option) => $option->toJson(), $customField->getOptions())
            )
            ->addPathCreationBehavior()
            ->addPathUpdateBehavior();
        $configBuilder->description->setReadableByPath()->addPathCreationBehavior()->addPathUpdateBehavior();
        $configBuilder->targetEntity->addPathCreationBehavior();
        $configBuilder->sourceEntity->addPathCreationBehavior();
        $configBuilder->sourceEntityId->addPathCreationBehavior();

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
        return $this->currentUser->hasPermission('area_admin_custom_fields');
    }

    public function getUpdatability(): ResourceUpdatability
    {
        return $this->getResourceConfig()->getUpdatability();
    }

    protected function getSchemaPathProcessor(): \EDT\Wrapping\Utilities\SchemaPathProcessor
    {
        return $this->getJsonApiResourceTypeService()->getSchemaPathProcessor();
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $attributes = $entityData->getAttributes();
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
        // Get the CustomFieldConfiguration from database
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($entityId);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$entityId}' not found");
        }

        // Get the current CustomField object
        $customField = clone $customFieldConfiguration->getConfiguration();
        $customField->setId($customFieldConfiguration->getId());

        // Update the fields from the request
        $attributes = $entityData->getAttributes();

        if (array_key_exists($this->name->getAsNamesInDotNotation(), $attributes)) {
            $customField->setName($attributes['name']);
        }

        if (array_key_exists($this->description->getAsNamesInDotNotation(), $attributes)) {
            $customField->setDescription($attributes['description']);
        }

        if (array_key_exists($this->options->getAsNamesInDotNotation(), $attributes)) {
            $newOptions = $attributes['options'];
            $this->validateOptionsUpdate($newOptions);

            $currentOptions = $customField->getOptions() ?? [];
            $updatedOptions = $this->processOptionsUpdate($currentOptions, $newOptions);
            $customField->setOptions($updatedOptions);
        }

        // Save back to CustomFieldConfiguration
        $customFieldConfiguration->setConfiguration($customField);
        $this->customFieldConfigurationRepository->updateObject($customFieldConfiguration);

        return new ModifiedEntity($customField, ['name', 'description', 'options']);
    }

    private function processOptionsUpdate(array $currentOptions, array $newOptions): array
    {
        $updatedOptions = [];
        $currentOptionsById = [];

        // Index current options by ID - now working with CustomFieldOption objects
        foreach ($currentOptions as $option) {
            $currentOptionsById[$option->getId()] = $option;
        }

        // Process each new option - incoming data is still arrays from API
        foreach ($newOptions as $newOption) {
            if (isset($newOption['id'])) {
                // Update existing option
                if (isset($currentOptionsById[$newOption['id']])) {
                    $customFieldOption = new CustomFieldOption();
                    $customFieldOption->setId($newOption['id']);
                    $customFieldOption->setLabel(
                        $newOption['label'] ?? $currentOptionsById[$newOption['id']]->getLabel()
                    );
                    $updatedOptions[] = $customFieldOption;
                } else {
                    // ID provided but doesn't exist - treat as new
                    $customFieldOption = new CustomFieldOption();
                    $customFieldOption->setId($newOption['id']);
                    $customFieldOption->setLabel($newOption['label']);
                    $updatedOptions[] = $customFieldOption;
                }
            } else {
                // New option - generate UUID
                $customFieldOption = new CustomFieldOption();
                $customFieldOption->setId(Uuid::uuid4()->toString());
                $customFieldOption->setLabel($newOption['label']);
                $updatedOptions[] = $customFieldOption;
            }
        }

        return $updatedOptions;
    }


    private function validateOptionsUpdate(array $newOptions): void
    {
        foreach ($newOptions as $option) {
            if (!isset($option['label']) || empty(trim($option['label']))) {
                throw new InvalidArgumentException('All options must have a non-empty label');
            }
        }
    }
}
