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

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\YmlRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\ScaleDTO;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ScaleDTO>
 *
 * @property-read End $scale
 */
final class ScaleResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Scale';
    }

    public function getEntityClass(): string
    {
        return ScaleDTO::class;
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(
                static fn (ScaleDTO $scale) => $scale->getScale()),
            $this->createAttribute($this->scale)->readable(true),
        ];
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    protected function getRepository(): RepositoryInterface
    {
        return new YmlRepository($this->globalConfig);
    }
}
