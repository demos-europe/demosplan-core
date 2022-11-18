<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AdditionalDataEvent extends DPlanEvent
{
    private CoreEntity $entity;

    private string $addon;

    /**
     * The array containing all the data to update a given statement.
     * Subscribers need to check for the existence of relevant keys.
     *
     * @var array<string, mixed>
     */
    private array $data;

    public function __construct(CoreEntity $entity, string $addon)
    {
        $this->entity = $entity;
        $this->addon = $addon;
    }

    public function getEntity(): CoreEntity
    {
        return $this->entity;
    }

    public function setEntity(CoreEntity $entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getAddon(): string
    {
        return $this->addon;
    }
}
