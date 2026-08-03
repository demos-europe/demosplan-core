<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Email;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\ApiResources\ApiPlatformConstants;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;

#[ApiResource(
    shortName: 'Email',
    operations: [
        new Get(uriTemplate: '/Email/{id}'),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: EmailProvider::class,
)]

class EmailResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public int $id;

    #[ApiProperty(readable: true, writable: false)]
    public string $to = '';

    public static function fromEntity( MailSend $mailSent): self
    {
        $resource = new self();
        $resource->id = $mailSent->getId();
        $resource->to = $mailSent->getTo();

        return $resource;
    }
}
