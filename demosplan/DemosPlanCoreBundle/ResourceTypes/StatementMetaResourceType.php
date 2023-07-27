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

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<StatementMeta>
 *
 * @property-read End $authorName
 * @property-read End $houseNumber
 * @property-read End $orgaStreet
 * @property-read End $orgaCity
 * @property-read End $orgaDepartmentName
 * @property-read End $orgaName
 * @property-read End $orgaPostalCode
 * @property-read End $submitName
 * @property-read End $authoredDate
 * @property-read End $orgaEmail
 */
final class StatementMetaResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'StatementMeta';
    }

    public function getEntityClass(): string
    {
        return StatementMeta::class;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->false();
    }

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    protected function getProperties(): array
    {
        return [];
    }
}
