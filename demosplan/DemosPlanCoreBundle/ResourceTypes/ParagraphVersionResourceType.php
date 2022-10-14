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

use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<ParagraphVersion>
 *
 * @property-read End $title
 * @property-read ParagraphResourceType $paragraph
 */
final class ParagraphVersionResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ParagraphVersion';
    }

    public function getEntityClass(): string
    {
        return ParagraphVersion::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
        ];
    }
}
