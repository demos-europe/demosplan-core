<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

interface EntityInterface
{
    /**
     * The unique identifier of this entity.
     *
     * Regarding potential `null` returns: Between initialization and flushing an entities ID will
     * be `null` in most cases (exceptions where it is set via PHP may exist).
     *
     * @return mixed|null
     */
    public function getId();
}
