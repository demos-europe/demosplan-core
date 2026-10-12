<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\TagTopic;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;

#[ApiResource(
    shortName: 'TagTopic',
    operations: [
        new Get(uriTemplate: '/TagTopic/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: Provider::class,
)]
class Resource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $title = '';

    public static function fromEntity(TagTopicInterface $topic): self
    {
        $resource = new self();
        $resource->id = $topic->getId();
        $resource->title = $topic->getTitle();

        return $resource;
    }
}
