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

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryOrga;
use demosplan\DemosPlanCoreBundle\StoredQuery\QuerySegment;
use Doctrine\Common\Collections\ArrayCollection;
use EDT\PathBuilding\End;
use Elastica\Index;

/**
 * @template-extends DplanResourceType<Orga>
 *
 * @property-read End                              $name
 * @property-read End                              $createdDate
 * @property-read InstitutionTagResourceType       $assignedTags
 * @property-read End                              $deleted
 * @property-read UserResourceType                 $users
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 */
final class InvitableInstitutionResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    /**
     * @var Index
     */
    private $esType;

    public function __construct(
        private readonly QueryOrga $esQuery,
        JsonApiEsService $jsonApiEsService,
    ) {
        $this->esType = $jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public static function getName(): string
    {
        return 'InvitableInstitution';
    }

    public function getEntityClass(): string
    {
        return Orga::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read');
    }

    public function isListAllowed(): bool
    {
        return true;
        return $this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign');
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomer::STATUS_ACCEPTED,
                $this->statusInCustomers->status
            ),
            $this->conditionFactory->propertyHasValue(
                Role::GPSORG,
                $this->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaType::PUBLIC_AGENCY,
                $this->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                $this->statusInCustomers->customer->id
            ),
        ];
    }

    protected function getProperties(): array
    {
        $assignedTags = $this->createToManyRelationship($this->assignedTags);
        $allowedProperties = [$assignedTags];
        $allowedProperties[] = $this->createIdentifier()->readable();

        if ($this->currentUser->hasPermission('feature_institution_tag_update')) {
            $assignedTags->updatable([], [], function (Orga $institution, array $newAssignedTags): array {
                $newAssignedTags = new ArrayCollection($newAssignedTags);
                $currentlyAssignedTags = $institution->getAssignedTags();

                // removed tags
                $removedTags = $currentlyAssignedTags->filter(
                    static fn (InstitutionTag $currentTag): bool => !$newAssignedTags->contains($currentTag)
                );

                // new tags
                $newTags = $newAssignedTags->filter(
                    static fn (InstitutionTag $newTag): bool => !$currentlyAssignedTags->contains($newTag)
                );

                foreach ($removedTags as $removedTag) {
                    $institution->removeAssignedTag($removedTag);
                    $this->resourceTypeService->validateObject($removedTag);
                }

                foreach ($newTags as $newTag) {
                    $institution->addAssignedTag($newTag);
                    $this->resourceTypeService->validateObject($newTag);
                }

                $this->resourceTypeService->validateObject($institution);

                return [];
            });
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read')
        ) {
            $allowedProperties[] = $this->createAttribute($this->name)->readable(true);
            $allowedProperties[] = $this->createAttribute($this->createdDate)->readable(true)->sortable();
            $assignedTags->readable(true)->filterable();
        }

        return $allowedProperties;
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return [AbstractQuery::SCOPE_ALL];
    }

    public function getSearchType(): Index
    {
        return $this->esType;
    }

    public function getFacetDefinitions(): array
    {
        return [];
    }
}
