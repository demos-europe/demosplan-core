<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ApiResources;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\StateProcessor\AdminProcedureStateProcesor;
use demosplan\DemosPlanCoreBundle\StateProvider\AdminProcedureStateProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'AdminProcedure',
    operations: [
        new Get(uriTemplate: '/AdminProcedure/{id}'),
        new GetCollection(uriTemplate: '/AdminProcedure'),
        new Patch(),
    ],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: AdminProcedureStateProvider::class,
    processor: AdminProcedureStateProcesor::class,
)]
#[ApiFilter(PropertyFilter::class)]
class AdminProcedureResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id;

    public string $name;

    public string $externalName;

    #[SerializedName('creationDate')]
    public \DateTime $createdDate;
    public \DateTime $internalStartDate;
    public \DateTime $internalEndDate;

    public int $originalStatementsCount;

    public int $statementsCounts;

    public string $internalPhaseIdentifier;

    public string $internalPhaseTranslationKey;

    public \DateTime $externalEndDate;

    public string $externalPhaseIdentifier;

    public string $externalPhaseTranslationKey;

    public \DateTime $externalStartDate;





}
