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

use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<MailSend>
 *
 * @property-read End $to
 */
final class EmailResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Email';
    }

    public function getEntityClass(): string
    {
        return MailSend::class;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->false()];
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->to)->readable(true)->sortable()->filterable(),
        ];
    }
}
