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
 * This Interface should be used to handle CRUD actions via objects.
 */
interface ObjectInterface
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

    /**
     * Update Object.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function updateObject($entity);

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId);

    /**
     * @param CoreEntity $entity
     *
     * @return bool
     */
    public function deleteObject($entity);
}
