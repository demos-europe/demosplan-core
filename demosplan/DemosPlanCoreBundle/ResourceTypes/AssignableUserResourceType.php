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

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<User>
 *
 * @property-read End $firstname
 * @property-read End $lastname
 * @property-read DepartmentResourceType $department
 * @property-read OrgaResourceType $orga
 */
final class AssignableUserResourceType extends DplanResourceType
{
    public function __construct(private readonly ProcedureService $procedureService)
    {
    }

    public static function getName(): string
    {
        return 'AssignableUser';
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_user');
    }

    /**
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            // you need a procedure to know who can be an assignee
            return [$this->conditionFactory->false()];
        }

        $authorizedUsers = $this->procedureService->getAuthorizedUsers($currentProcedure->getId());
        $authorizedUserIds = [];
        /** @var User $user */
        foreach ($authorizedUsers as $user) {
            $authorizedUserIds[] = $user->getId();
        }
        if (0 < count($authorizedUsers)) {
            // only return users that are on the list of authorized users
            return [] === $authorizedUserIds
                ? [$this->conditionFactory->false()]
                : [$this->conditionFactory->propertyHasAnyOfValues($authorizedUserIds, $this->id)];
        }

        return [$this->conditionFactory->false()];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->filterable()->sortable(),
            $this->createAttribute($this->firstname)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->lastname)->readable(true)->filterable()->sortable(),
            $this->createToOneRelationship($this->department)->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->orga)
                ->readable(true, fn (User $user): ?Orga => $user->getOrga(), true)
                ->filterable()
                ->sortable(),
        ];
    }
}
