<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository\IRepository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;

/**
 * This Interface should be used to handle get and add actions via objects.
 */
interface ImmutableObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     */
    public function get($entityId);

    /**
     * Add Entityobject to database.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function addObject($entity);
}
