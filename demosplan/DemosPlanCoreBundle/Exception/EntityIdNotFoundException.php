<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

/**
 * Class EntityIdNotFoundException
 * <p>
 * Use instances of this exception class to indicate that a requested task can not be fulfilled because the entity ID
 * provided was not found in the database. The entity ID not found can be set optionally.
 * <p>
 * To specify the type of the entity use subclasses of this exception.
 */
class EntityIdNotFoundException extends InvalidArgumentException
{
    /**
     * The entity ID not found formatted as string.
     *
     * @var string
     */
    protected $entityId;

    /**
     * @param string $entityId the value to set as entity ID
     *
     * @uses $entityId
     */
    public function setEntityId(string $entityId)
    {
        $this->entityId = $entityId;
    }

    /**
     * @return string|null the value set as entity ID or null if it hasn't been set
     *
     * @uses $entityId
     */
    public function getEntityId()
    {
        return $this->entityId;
    }
}
