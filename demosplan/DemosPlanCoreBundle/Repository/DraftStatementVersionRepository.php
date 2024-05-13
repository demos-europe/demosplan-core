<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends CoreRepository<DraftStatementVersion>
 */
class DraftStatementVersionRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get a specific Entity by Statement-ID.
     *
     * @param string $statementId
     *
     * @return DraftStatementVersion|null
     */
    public function getByStatementId($statementId)
    {
        try {
            return $this->findOneBy(['draftStatement' => $statementId]);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return DraftStatementVersion|null
     */
    public function get($entityId)
    {
        try {
            /** @var DraftStatementVersion $draftStatementVersion */
            $draftStatementVersion = $this->findOneBy(['id' => $entityId]);
            if (!is_null($draftStatementVersion)) {
                // add files to statement Entity
                /** @var FileContainerRepository $fileContainerRepository */
                $fileContainerRepository = $this->getEntityManager()->getRepository(FileContainer::class);
                $draftStatementVersion->setFiles(
                    $fileContainerRepository
                        ->getFileStrings(DraftStatementVersion::class, $entityId, 'file')
                );
            }

            return $draftStatementVersion;
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get all Files of a procedure.
     *
     * @param string $procedureId
     *
     * @return array|null
     */
    public function getFilesByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('dsv.file')
            ->addSelect('dsv.mapFile')
            ->from(DraftStatementVersion::class, 'dsv')
            ->where('dsv.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of DraftStatementVersion failed ', [$e]);

            return null;
        }
    }

    /**
     * Get a list of own released DraftStatements (Not their Versions!).
     *
     * @param string $procedureId
     *
     * @return DraftStatement[]
     *
     * @throws Exception
     */
    public function getOwnReleasedList($procedureId, User $user, $element, $search)
    {
        try {
            $em = $this->getEntityManager();

            // addGroupBy('dsv.createdDate') is necessary to avoid problems
            // with mysql > 5.7.4 with groupBy statement
            // http://rpbouman.blogspot.de/2014/09/mysql-575-group-by-respects-functional.html

            // split into two queries. First get relevant draftStatement IDs then fetch entites from db
            $query = $em->createQueryBuilder()
                ->select('ds.id')
                ->from(DraftStatement::class, 'ds')
                ->join('ds.versions', 'dsv')
                ->andWhere('dsv.procedure = :pid')->setParameter('pid', $procedureId)
                ->andWhere('dsv.deleted = :deleted')->setParameter('deleted', false)
                ->andWhere('dsv.organisation = :organisation')->setParameter('organisation', $user->getOrganisationId())
                ->andWhere('dsv.user = :user')->setParameter('user', $user->getId())
                ->andWhere('dsv.released = :released')->setParameter('released', true)
                ->andWhere('dsv.submitted = :submitted')->setParameter('submitted', false)
                ->andWhere('DATE_DIFF(dsv.versionDate, ds.releasedDate) < 2')
                ->groupBy('dsv.draftStatement')
                ->addGroupBy('dsv.createdDate')
                ->addGroupBy('ds.id');

            if (null !== $element) {
                $query->andWhere('dsv.element = :element');
                $query->setParameter('element', $element);
            }

            if (is_string($search) && 0 < strlen($search)) {
                $query->andWhere('dsv.text LIKE :search');
                $query->setParameter('search', '%'.$search.'%');
            }

            $query = $query->getQuery();
            $result = $query->getResult();

            $queryDS = $em->createQueryBuilder()
                ->select('ds')
                ->from(DraftStatement::class, 'ds')
                ->where('ds.id IN (:ids)')->setParameter('ids', $result)
                ->orderBy('ds.createdDate', 'desc')
                ->getQuery();

            return $queryDS->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get List getOwnReleasedList failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return DraftStatementVersion
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('pId', $data)) {
                throw new InvalidArgumentException('Trying to add a Draft statement without ProcedureKey pId');
            }

            $draftStatementVersion = $this->generateObjectValues(new DraftStatementVersion(), $data);

            $em->persist($draftStatementVersion);
            $em->flush();

            return $draftStatementVersion;
        } catch (Exception $e) {
            $this->logger->warning('Create DraftStatementVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Copy DraftStatementVersion from DraftStatement.
     *
     * @throws Exception
     */
    public function createVersion(DraftStatement $draftStatement): DraftStatementVersion
    {
        try {
            if (!$draftStatement instanceof DraftStatement) {
                throw new Exception('DraftStatement to copyfrom has to be of Type DraftStatement');
            }
            $em = $this->getEntityManager();

            $draftStatementVersion = $this->generateObjectValuesFromObject(new DraftStatementVersion(), $draftStatement);
            $draftStatementVersion->setDraftStatement($draftStatement);
            $em->persist($draftStatementVersion);
            $em->flush();

            return $draftStatementVersion;
        } catch (Exception $e) {
            $this->logger->warning('Create DraftStatementVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return DraftStatementVersion
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $draftStatementVersion = $this->get($entityId);
            $draftStatementVersion = $this->generateObjectValues($draftStatementVersion, $data);

            $em->persist($draftStatementVersion);

            $em->flush();

            return $draftStatementVersion;
        } catch (Exception $e) {
            $this->logger->warning('Update DraftStatementsVersion failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all DraftStatementVersions of a procedure.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteByProcedureId(string $procedureId): int
    {
        $deletedEntities = 0;
        $draftStatementVersionsToDelete = $this->findBy(['procedure' => $procedureId]);
        /** @var DraftStatementVersion $draftStatementVersion */
        foreach ($draftStatementVersionsToDelete as $draftStatementVersion) {
            $this->getEntityManager()->remove($draftStatementVersion);
            ++$deletedEntities;
        }
        $this->getEntityManager()->flush();

        return $deletedEntities;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     */
    public function delete($entityId): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param DraftStatementVersion $entity
     *
     * @return DraftStatementVersion
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }

    /**
     * Copy properties from DraftStatement to DraftStatementVersion.
     *
     * @param DraftStatementVersion $copyToEntity
     * @param DraftStatement        $copyFromEntity
     * @param array                 $excludeProperties
     *
     * @return DraftStatementVersion
     */
    protected function generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties = [])
    {
        $excludeProperties = ['id', 'pId', 'dId', 'oId', 'uId', 'paragraphId', 'elementId'];

        return parent::generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties);
    }
}
