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

use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<MasterToeb>
 *
 * @property-read End $ident
 */
final class MasterToebResourceType extends DplanResourceType
{
    public function getEntityClass(): string
    {
        return MasterToeb::class;
    }

    public static function getName(): string
    {
        return 'MasterToeb';
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_mastertoeblist',
            // can be included via Orga resources in the user list
            'area_manage_users'
        );
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->filterable()->sortable()->readable()
                ->aliasedPath($this->ident),
        ];
    }
}
