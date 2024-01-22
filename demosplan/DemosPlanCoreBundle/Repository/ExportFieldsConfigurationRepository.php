<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends CoreRepository<ExportFieldsConfiguration>
 */
class ExportFieldsConfigurationRepository extends CoreRepository implements ObjectInterface
{
    /**
     * @param string $exportFieldsConfigurationId
     */
    public function get($exportFieldsConfigurationId): ?ExportFieldsConfiguration
    {
        return $this->find($exportFieldsConfigurationId);
    }

    /**
     * @param ExportFieldsConfiguration $exportFieldsConfiguration
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($exportFieldsConfiguration): ExportFieldsConfiguration
    {
        $em = $this->getEntityManager();
        $em->persist($exportFieldsConfiguration);
        $em->flush();

        return $exportFieldsConfiguration;
    }

    /**
     * @param ExportFieldsConfiguration $exportFieldsConfiguration
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($exportFieldsConfiguration): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($exportFieldsConfiguration);
        $entityManager->flush();
    }

    /**
     * @param string $exportFieldsConfigurationId
     *
     * @return bool|void
     */
    public function delete($exportFieldsConfigurationId)
    {
        return $this->deleteObject($this->get($exportFieldsConfigurationId));
    }

    public function deleteObject($exportFieldsConfiguration)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($exportFieldsConfiguration);
        $entityManager->flush();

        return true;
    }

    public function getEntityByProcedureId(string $procedureId): ExportFieldsConfiguration
    {
        $return = $this->createQueryBuilder('exportConfig')
            ->andWhere('exportConfig.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getResult();

        return $return[0];
    }
}
