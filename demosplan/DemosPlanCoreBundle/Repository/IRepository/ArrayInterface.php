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
 * This Interface should be used to handle CRUD actions via arrays.
 * When possible use ObjectInterface instead.
 */
interface ArrayInterface
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
     * Add Entity to database.
     *
     * @return CoreEntity
     */
    public function add(array $data);

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     */
    public function update($entityId, array $data);

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId);

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function generateObjectValues($entity, array $data);
}
