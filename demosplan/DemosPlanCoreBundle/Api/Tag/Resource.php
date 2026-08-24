<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Tag;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Api\AssignableUser\AssignableUserResource;
use demosplan\DemosPlanCoreBundle\Api\TagTopic\Resource as TagTopicResource;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag as TagEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

#[ApiResource(
    shortName: 'Tag',
    operations: [
        new GetCollection(uriTemplate: '/Tag', paginationClientItemsPerPage: true),
        new Get(uriTemplate: '/Tag/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: Provider::class,
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(OrderFilter::class, properties: ['sortIndex', 'title'])]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $title = '';

    #[ApiProperty(readable: true, writable: false)]
    public int $sortIndex = 0;

    #[ApiProperty(readable: true, writable: false)]
    public ?TagTopicResource $topic = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $boilerplateId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?AssignableUserResource $defaultAssignee = null;

    public static function fromEntity(TagEntity $tag): self
    {
        $resource = new self();
        $resource->id = $tag->getId();
        $resource->title = $tag->getTitle();
        $resource->sortIndex = $tag->getSortIndex();
        $resource->topic = TagTopicResource::fromEntity($tag->getTopic());
        $resource->boilerplateId = $tag->getBoilerplate()?->getId();
        $defaultAssignee = $tag->getDefaultAssignee();
        $resource->defaultAssignee = $defaultAssignee instanceof User
            ? AssignableUserResource::fromEntity($defaultAssignee)
            : null;

        return $resource;
    }
}
