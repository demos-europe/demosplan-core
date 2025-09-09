<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits;

use BadMethodCallException;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;

trait RepositoryLegacyShizzle
{
    /**
     * ===================================================================================
     * ===================================================================================.
     *
     * LEGACY SHIZZLE START
     *
     * ===================================================================================
     * ===================================================================================
     */

    /**
     * @param string $entityId
     *
     * @throws BadMethodCallException
     */
    public function update($entityId, array $data): never
    {
        throw new BadMethodCallException('Please use Objects and not arrays.');
    }

    /**
     * @throws BadMethodCallException
     */
    public function add(array $data): never
    {
        throw new BadMethodCallException('Please use Objects and not arrays.');
    }

    /**
     * @param string $entityId
     *
     * @throws BadMethodCallException
     */
    public function delete($entityId): never
    {
        // We want to use objects. So don't use or implement this.
        // instead make use of deleteObject (which should also be defined in the interface)
        // we have addObject, updateObject, but no deleteObject ...
        throw new BadMethodCallException('Not implemented → use deleteObject(entity)');
    }

    /**
     * @param CoreEntity $entity
     *
     * @throws BadMethodCallException
     */
    public function generateObjectValues($entity, array $data): never
    {
        // - First we need to refactor the usages of generateObjectValues
        // - Then we can delete the method from the CoreRepository
        // - Then the interface allows us to delete this unused method.
        throw new BadMethodCallException('Not implemented → just use the object with getters/setters...');
    }

    /**
     * @param string $entityId
     *
     * @throws BadMethodCallException
     */
    public function get($entityId): never
    {
        /*
         * instead use ->find(['id' => $entityId]);
         * or even better make use of the magic function __call in EntityRepository
         * u can do ->findById($entityId);
         * for other fields u can use findBySomePropperty($proppertyValue);
         * Why the fuuuu would we write this method...
         * And why the fuuu is this even in our interface...
         * It's pretty easy to use and makes the interface clean again (in the future)
         */
        throw new BadMethodCallException('Not implemented → just use findById()');
    }

    /*
     * ===================================================================================
     * ===================================================================================
     *
     * LEGACY SHIZZLE END
     *
     * ===================================================================================
     * ===================================================================================
     */
}
