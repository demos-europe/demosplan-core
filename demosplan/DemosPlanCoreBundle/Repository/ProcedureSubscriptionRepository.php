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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Exception\DeprecatedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableArrayInterface;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends CoreRepository<ProcedureSubscription>
 */
class ProcedureSubscriptionRepository extends CoreRepository implements ImmutableArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return ProcedureSubscription
     */
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['ident' => $entityId]);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @deprecated use {@link addObject} instead
     *
     * @throws DeprecatedException
     */
    public function add(array $data): never
    {
        throw new DeprecatedException('Use addObject instead.');
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     */
    public function delete($entityId): bool
    {
        try {
            $em = $this->getEntityManager();
            $entity = $this->get($entityId);
            if (null != $entity) {
                $em->remove($entity);
                $em->flush();

                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('Could not delete ProcedureSubscription: ', [$e]);

            return false;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }
}
