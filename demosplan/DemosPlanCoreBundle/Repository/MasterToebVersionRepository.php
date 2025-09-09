<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToebVersion;

/**
 * @template-extends CoreRepository<MasterToebVersion>
 */
class MasterToebVersionRepository extends CoreRepository
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     */
    public function get($entityId)
    {
    }

    /**
     * Add Entity to database.
     */
    public function add(array $data)
    {
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     */
    public function update($entityId, array $data)
    {
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     */
    public function delete($entityId)
    {
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return void
     */
    public function generateObjectValues($entity, array $data)
    {
    }

    public function addObject($entity)
    {
    }

    public function updateObject($entity)
    {
    }
}
