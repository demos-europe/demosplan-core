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
 * This Interface should be used to handle create and get via arrays.
 * When possible use ImmutableObjectInterface instead.
 */
interface ImmutableArrayInterface
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
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function generateObjectValues($entity, array $data);
}
