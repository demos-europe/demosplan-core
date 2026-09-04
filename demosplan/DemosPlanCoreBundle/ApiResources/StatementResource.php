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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement as StatementEntity;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementStateProvider;

#[ApiResource(
    shortName: 'Statement',
    operations: [new Get(uriTemplate: '/Statement/{id}')],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: StatementStateProvider::class,
)]
class StatementResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $externId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $internId = null;

    #[ApiProperty(readable: true, writable: false)]
    public bool $isSubmittedByCitizen = false;

    #[ApiProperty(readable: true, writable: false)]
    public string $authorName = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $initialOrganisationName = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrganisationDepartmentName = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrganisationStreet = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrganisationHouseNumber = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrganisationPostalCode = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $initialOrganisationCity = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $authoredDate = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $submitDate = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $submitName = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $submitType = null;

    #[ApiProperty(readable: true, writable: false)]
    public string $memo = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $status = null;

    public static function fromEntity(StatementEntity $statement, ?string $status = null): self
    {
        $resource = new self();
        $resource->id = $statement->getId();
        $resource->externId = $statement->getExternId();
        $resource->internId = $statement->getInternId();
        $resource->isSubmittedByCitizen = $statement->isSubmittedByCitizen();
        $resource->authorName = $statement->getAuthorName();
        $resource->initialOrganisationName = $statement->getMeta()->getOrgaName();
        $resource->initialOrganisationDepartmentName = $statement->getMeta()->getOrgaDepartmentName();
        $resource->initialOrganisationStreet = $statement->getMeta()->getOrgaStreet();
        $resource->initialOrganisationHouseNumber = $statement->getMeta()->getHouseNumber();
        $resource->initialOrganisationPostalCode = $statement->getMeta()->getOrgaPostalCode();
        $resource->initialOrganisationCity = $statement->getMeta()->getOrgaCity();
        $resource->authoredDate = $statement->getMeta()->getAuthoredDateObject()?->format(DATE_ATOM);
        $resource->submitDate = $statement->getSubmitObject()?->format(DATE_ATOM);
        $resource->submitName = $statement->getMeta()->getSubmitName();
        $resource->submitType = $statement->getSubmitType();
        $resource->memo = $statement->getMemo();
        $resource->status = $status;

        return $resource;
    }
}
