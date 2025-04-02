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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceCreateFlushEvent;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\DoctrineResourceTypeInjectionTrait;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldService;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use demosplan\DemosPlanCoreBundle\Doctrine\Type\CustomFieldType;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomField;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceTypeTrait;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldJsonRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldConfigBuilder;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilder;
use EDT\JsonApi\PropertyConfig\Builder\IdentifierConfigBuilder;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\ResourceConfigInterface;
use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PropertyPathInterface;
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
 *
 * @method bool isNullSafe(int $index)
 */
final class CustomFieldResourceType extends AbstractResourceType implements JsonApiResourceTypeInterface, PropertyPathInterface, IteratorAggregate, PropertyAutoPathInterface
{
    use PropertyAutoPathTrait;
    use DoctrineResourceTypeInjectionTrait;


    public function __construct(private readonly CustomFieldService          $customFieldService,
                                protected readonly ConditionFactoryInterface $conditionFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository, private readonly ResourceTypeService $resourceTypeService)
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
        // return [$this->conditionFactory->true()];
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

        $configBuilder->id->readable();
        $configBuilder->name->readable()->initializable();
        $configBuilder->fieldType->readable()->initializable();
        $configBuilder->description->readable()->initializable();
        $configBuilder->targetEntity->readable()->initializable();
        $configBuilder->sourceEntity->readable()->initializable();
        $configBuilder->sourceEntityId->readable()->initializable();

        // $configBuilder->type->readable();

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

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(
                ''),
            $this->createAttribute($this->name)->readable(true),
            $this->createAttribute($this->description)->readable(true),
        ];
    }

    /**
     * @return IdentifierConfigBuilder<TEntity>
     */
    protected function createIdentifier(): IdentifierConfigBuilder
    {
        return $this->getPropertyBuilderFactory()->createIdentifier($this->getEntityClass());
    }

    /**
     * @return AttributeConfigBuilder<ClauseFunctionInterface<bool>, TEntity>
     */
    protected function createAttribute(PropertyPathInterface $path): AttributeConfigBuilder
    {
        return $this->getPropertyBuilderFactory()->createAttribute(
            $this->getEntityClass(),
            $path
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
            $this->customFieldService
        );
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
        return true;
    }

    public function isUpdateAllowed(): bool
    {
        return true;
    }

    public function getUpdatability(): ResourceUpdatability
    {
        return $this->getResourceConfig()->getUpdatability();
    }

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
    }

    public function getLength()
    {
        // TODO: Implement getLength() method.
    }

    public function getParent()
    {
        // TODO: Implement getParent() method.
    }

    public function getElements()
    {
        // TODO: Implement getElements() method.
    }

    public function getElement(int $index)
    {
        // TODO: Implement getElement() method.
    }

    public function isProperty(int $index)
    {
        // TODO: Implement isProperty() method.
    }

    public function isIndex(int $index)
    {
        return 0;
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {

                    $attributes = $entityData->getAttributes();


                    /** @var CustomFieldConfiguration $customFieldConfiguration */
                    $customFieldConfiguration = $this->customFieldConfigurationRepository->getCustomFieldConfigurationByProcedureId($attributes['sourceEntity'], $attributes['sourceEntityId'], $attributes['targetEntity']);
                    //If exists, then merge this customField
                    if($customFieldConfiguration) {

                        /** @var CustomFieldList $configuration */
                        $configuration = $customFieldConfiguration->getConfiguration();

                        $customFieldsList = $configuration->getCustomFieldsList();
                        $radioButton = new RadioButtonField();
                        $radioButton->setType('radio_button');
                        $radioButton->setName($attributes['name']);
                        $radioButton->setDescription($attributes['description']);

                        $customFieldsList[] = $radioButton;
                        $configuration->setCustomFields($customFieldsList);

                        $jsonConfig = $configuration->toJson();

                        $customFieldConfiguration->setConfiguration($jsonConfig);

                        $this->customFieldConfigurationRepository->updateObject($customFieldConfiguration);



                        //$this->eventDispatcher->dispatch(new BeforeResourceCreateFlushEvent($this, $radioButton));

                        return new ModifiedEntity($radioButton, []);

                    }

                    //if it does not exist, create new entry

                   // $toOneRelationships = $entityData->getToOneRelationships();

                    return new ModifiedEntity(null, []);

                    /*$currentCustomer = $this->customFieldConfigurationRepository->getCustomFieldConfigurationByProcedureId();
                    $attributes = $entityData->getAttributes();

                    // create support contact
                    $contact = new SupportContact(
                        SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT,
                        $attributes[$this->title->getAsNamesInDotNotation()],
                        $attributes[$this->phoneNumber->getAsNamesInDotNotation()],
                        $attributes[$this->eMailAddress->getAsNamesInDotNotation()],
                        $attributes[$this->text->getAsNamesInDotNotation()],
                        $currentCustomer,
                        $attributes[$this->visible->getAsNamesInDotNotation()],
                    );

                    // update customer
                    $currentCustomer->getContacts()->add($contact);

                    // validate entities
                    $this->resourceTypeService->validateObject($contact);
                    $this->resourceTypeService->validateObject($currentCustomer);

                    // persist created entities
                    $this->supportContactRepository->persistEntities([$contact]);

                    $this->eventDispatcher->dispatch(new BeforeResourceCreateFlushEvent($this, $contact));

                    return new ModifiedEntity($contact, []);*/
                }
            );
        } catch (Exception $exception) {
            $this->addCreationErrorMessage([]);

            throw $exception;
        }
    }
}
