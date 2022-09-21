<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-extends DplanResourceType<EmailAddress>
 *
 * @property-read End $fullAddress
 */
class EmailAddressResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'EmailAddress';
    }

    public function getEntityClass(): string
    {
        return EmailAddress::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function getAccessCondition(): FunctionInterface
    {
        return $this->conditionFactory->true();
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->fullAddress)->readable(),
        ];
    }
}
