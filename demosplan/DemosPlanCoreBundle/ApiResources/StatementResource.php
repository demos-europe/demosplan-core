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
 * - `statement:read` marks attributes open to everyone allowed to use the resource.
 * - `perm:read:<permission>` marks attributes that need that permission (several labels mean
 *   "any of", `a+b` inside one label means "all of").
 *
 * The permissions mirror what the EDT StatementResourceType required. See
 * {@see \demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionGroupResolver}.
 */
#[ApiResource(
    shortName: 'Statement',
    operations: [new Get(uriTemplate: '/Statement/{id}')],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: StatementStateProvider::class,
    // base group: an empty group list would mean "no filtering" to the serializer
    normalizationContext: ['groups' => ['statement:read']],
)]
class StatementResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $externId = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $internId = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'perm:read:area_admin_statement_list',
        'perm:read:area_admin_submitters',
        'perm:read:area_statement_segmentation',
        'perm:read:feature_segments_of_statement_list',
    ])]
    public bool $isSubmittedByCitizen = false;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'perm:read:area_admin_assessmenttable',
        'perm:read:area_admin_consultations',
        'perm:read:feature_json_api_statement',
    ])]
    public string $authorName = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public string $initialOrganisationName = '';

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $initialOrganisationDepartmentName = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $initialOrganisationStreet = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $initialOrganisationHouseNumber = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $initialOrganisationPostalCode = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $initialOrganisationCity = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $authoredDate = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $submitDate = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'perm:read:area_admin_assessmenttable',
        'perm:read:area_admin_consultations',
        'perm:read:feature_json_api_statement',
    ])]
    public ?string $submitName = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['statement:read'])]
    public ?string $submitType = null;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['perm:read:field_statement_memo'])]
    public string $memo = '';

    /**
     * The processing status. Expensive to compute, see StatementStateProvider.
     */
    #[ApiProperty(readable: true, writable: false)]
    #[Groups(['perm:read:area_statement_segmentation'])]
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
