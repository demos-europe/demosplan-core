<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends FluentRepository<ScheduledExport>
 */
class ScheduledExportRepository extends FluentRepository
{
    /**
     * @param string $entityId
     *
     * @return ScheduledExport|null
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @param ScheduledExport $entity
     *
     * @return bool
     */
    public function addObject($entity)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($entity);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not add new scheduled export: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param ScheduledExport $entity
     *
     * @return bool
     */
    public function updateObject($entity)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($entity);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not update scheduled export: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param ScheduledExport $scheduledExport
     *
     * @return bool - true if successfully deleted the given entity, otherwise false
     */
    public function deleteObject($scheduledExport)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->remove($scheduledExport);
            $entityManager->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Could not delete scheduled export', ['id' => $scheduledExport->getId(), 'exception' => $e]);
        }

        return false;
    }
}
