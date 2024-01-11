<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragmentVersion;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends CoreRepository<StatementFragmentVersion>
 */
class StatementFragmentVersionRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return StatementFragmentVersion
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get StatementFragmentVersion failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param StatementFragmentVersion $fragmentVersion
     *
     * @return StatementFragmentVersion
     *
     * @throws Exception
     */
    public function addObject($fragmentVersion)
    {
        try {
            $manager = $this->getEntityManager();
            $manager->persist($fragmentVersion);
            $manager->flush();
        } catch (Exception $e) {
            $this->logger->error('Add StatementFragmentVersion failed: ', [$e]);
            throw new Exception('Could not add StatementFragmentVersion');
        }

        return $fragmentVersion;
    }

    /**
     * Update Entity.
     *
     * @param StatementFragmentVersion $statementFragmentVersion
     *
     * @return StatementFragmentVersion|false
     */
    public function updateObject($statementFragmentVersion)
    {
        try {
            $this->getEntityManager()->persist($statementFragmentVersion);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update StatementFragmentVersion failed: ', [$e]);

            return false;
        }

        return $statementFragmentVersion;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteById($entityId)
    {
        /** @var StatementFragmentVersion|null $toDelete */
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Deletes all StatementFragmentVersions of a procedure.
     *
     * @param string $procedureId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteByProcedureId($procedureId)
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->delete(StatementFragmentVersion::class, 'sfv')
                ->andWhere('sfv.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete StatementFragmentVersions of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param StatementFragmentVersion $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning(
                'Delete StatementFragmentVersion failed: Given ID not found.'
            );
            throw new EntityNotFoundException('Delete StatementFragmentVersion failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete StatementFragmentVersion failed: ', [$e]);
        }

        return false;
    }

    /**
     * @param StatementFragmentVersion $entity
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteObject($entity)
    {
        return $this->delete($entity);
    }

    /**
     * Get all Entities.
     *
     * @return StatementFragmentVersion[]
     */
    public function getAll()
    {
        try {
            return $this->findAll();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get max given number.
     *
     * @param array $data
     *
     * @return int
     */
    protected function getMaxDisplayId($data)
    {
        if (!isset($data['procedureId'])) {
            return 0;
        }
        $val = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('MAX(sf.displayId)')
            ->from(StatementFragmentVersion::class, 'sf')
            ->andWhere('sf.procedure = :procedureId')
            ->setParameter('procedureId', $data['procedureId'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $val;
    }
}
