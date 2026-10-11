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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Which user sees which attribute is declared with serialization groups:
 *
 * - `read` marks attributes open to everyone allowed to use the resource.
 * - A permission name (e.g. `field_statement_memo`) marks attributes that need that permission.
 *   Several permission names mean "any of", `a+b` inside one group means "all of".
 *
 * The permissions mirror what the EDT StatementResourceType required. See
 * {@see \demosplan\DemosPlanCoreBundle\Api\Serializer\FieldPermissionResolver}.
 */
#[ApiResource(
    shortName: 'Statement',
    operations: [new Get(uriTemplate: '/Statement/{id}')],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    normalizationContext: ['groups' => ['read']],
    provider: StatementStateProvider::class,
)]
class StatementResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $externId = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $internId = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'area_admin_statement_list',
        'area_admin_submitters',
        'area_statement_segmentation',
        'feature_segments_of_statement_list',
    ])]
    public bool $isSubmittedByCitizen = false;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'area_admin_assessmenttable',
        'area_admin_consultations',
        'feature_json_api_statement',
    ])]
    public string $authorName = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public string $initialOrganisationName = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $initialOrganisationDepartmentName = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $initialOrganisationStreet = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $initialOrganisationHouseNumber = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $initialOrganisationPostalCode = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $initialOrganisationCity = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $authoredDate = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $submitDate = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'area_admin_assessmenttable',
        'area_admin_consultations',
        'feature_json_api_statement',
    ])]
    public ?string $submitName = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['read'])]
    public ?string $submitType = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['field_statement_memo'])]
    public string $memo = '';

    /**
     * The processing status. Expensive to compute, see StatementStateProvider.
     */
    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['area_statement_segmentation'])]
    public ?string $status = null;

    public static function fromEntity(StatementInterface $statement, ?string $status = null): self
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
