<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\CustomField;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\CustomField\AbstractCustomField;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use Webmozart\Assert\Assert;

/**
 * A custom field definition attached to a source entity (e.g. a Procedure or the Customer) and applied
 * to a target entity kind (e.g. Statement, Segment). The definition itself (name, description, field
 * type, options, whether it is required) is stored as a serialized value object on the wrapping
 * {@see CustomFieldConfiguration} entity, so `fromEntity()` reads from both.
 *
 * Read-only for now: create/update/delete stay on the legacy
 * {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType} JSON:API v2 endpoint. Only
 * the read side - which that legacy type never implemented for listing - is migrated here.
 */
#[ApiResource(
    shortName: 'CustomField',
    operations: [
        new GetCollection(
            uriTemplate: '/CustomField',
            paginationEnabled: false,
            parameters: [
                'sourceEntity'   => new QueryParameter(filter: new ExactFilter(), property: 'sourceEntityClass', required: true),
                'sourceEntityId' => new QueryParameter(filter: new ExactFilter(), property: 'sourceEntityId', required: true),
                'targetEntity'   => new QueryParameter(filter: new ExactFilter(), property: 'targetEntityClass', required: true),
            ]),
        new Get(uriTemplate: self::ITEM_URI_TEMPLATE),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: CustomFieldProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class CustomFieldResource
{
    private const ITEM_URI_TEMPLATE = '/CustomField/{id}';

    /**
     * {@see CustomFieldConfiguration} UUID.
     */
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $name = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $description = '';

    #[ApiProperty(readable: true, writable: false)]
    public bool $isRequired = false;

    /**
     * One of {@see \demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface::TYPE_CLASSES}.
     */
    #[ApiProperty(readable: true, writable: false)]
    public string $fieldType = '';

    /**
     * @var list<array{id: string, label: string}>
     */
    #[ApiProperty(readable: true, writable: false)]
    public array $options = [];

    /**
     * One of {@see \demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity}.
     */
    #[ApiProperty(readable: true, writable: false)]
    public string $targetEntity = '';

    /**
     * One of {@see \demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity}.
     */
    #[ApiProperty(readable: true, writable: false)]
    public string $sourceEntity = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $sourceEntityId = '';

    public static function fromEntity(CustomFieldConfiguration $entity): self
    {
        $configuration = $entity->getConfiguration();
        // getDescription() is declared on AbstractCustomField, not on CustomFieldInterface itself;
        // every concrete field type (TextField, RadioButtonField, MultiSelectField) extends it.
        Assert::isInstanceOf($configuration, AbstractCustomField::class);

        $resource = new self();
        $resource->id = $entity->getId();
        $resource->sourceEntity = $entity->getSourceEntityClass();
        $resource->sourceEntityId = $entity->getSourceEntityId();
        $resource->targetEntity = $entity->getTargetEntityClass();
        $resource->name = $configuration->getName();
        $resource->description = $configuration->getDescription();
        $resource->fieldType = $configuration->getFieldType();
        $resource->isRequired = $configuration->getRequired();
        $resource->options = array_map(
            static fn (CustomFieldOption $option): array => $option->toJson(),
            $configuration->getOptions()
        );

        return $resource;
    }
}
