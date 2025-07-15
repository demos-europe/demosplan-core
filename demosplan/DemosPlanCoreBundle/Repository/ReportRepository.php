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
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\DeprecatedException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use InvalidArgumentException;

/**
 * @template-extends CoreRepository<ReportEntry>
 */
class ReportRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Gets an ReportEntry.
     *
     * @param string $identifier
     *
     * @return reportEntry|null - The report entry with the given identifier
     */
    public function get($identifier)
    {
        /** @var ReportEntry $reportEntry */
        $reportEntry = $this->find($identifier);

        return $reportEntry;
    }

    /**
     * Check if the given array has all necessary keys for a ReportEntry.
     *
     * @param array $array array, which is to check
     *
     * @return bool true, if the given array has all necessary keys, otherwise false
     */
    private function hasNecessaryKeys(array $array): bool
    {
        return
            array_key_exists('category', $array)
            && array_key_exists('group', $array)
            && array_key_exists('user', $array)
            && array_key_exists('identifierType', $array)
            && array_key_exists('ident', $array)
            && array_key_exists('message', $array)
            && array_key_exists('customer', $array)
        ;
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
     * @param ReportEntry $reportEntry
     *
     * @return ReportEntry
     */
    public function generateObjectValues($reportEntry, array $data)
    {
        if (!$this->hasNecessaryKeys($data)) {
            throw new InvalidArgumentException('Not all necessary data in the given array.');
        }

        $reportEntry->setIdentifierType($data['identifierType'] ?? '');
        $reportEntry->setIdentifier($data['ident'] ?? '');
        $reportEntry->setMessage($data['message'] ?? '');
        $reportEntry->setCategory($data['category'] ?? '');
        $reportEntry->setGroup($data['group'] ?? '');
        $reportEntry->setIncoming($data['incoming'] ?? '');
        $reportEntry->setUser($data['user'] ?? '');
        $reportEntry->setCustomer($data['customer'] ?? '');
        $reportEntry->setCreateDate($data['createDate'] ?? null);

        return $reportEntry;
    }

    /**
     * @return void
     */
    public function update($entityId, array $data)
    {
        // there is no need to update report entries
    }

    /**
     * @param string $procedureId
     *
     * @return bool true if the entry was found and deleted, otherwise false
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($procedureId)
    {
        $em = $this->getEntityManager();
        $reports = $this->findBy(['identifier' => $procedureId]);

        foreach ($reports as $report) {
            $em->remove($report);
        }
        $em->flush();

        return true;
    }

    public function addObject($entity)
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $entity;
    }

    public function updateObject($entity)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        return $entity;
    }

    /**
     * Returns all ReportEntries, which can be related to a specific statement.
     *
     * @param Statement $statement statement, whose ReportEntry will be searched for
     *
     * @return ReportEntry[] array of found Report entries
     */
    public function getReportsOfStatement(Statement $statement): array
    {
        // improve: T12914
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('report')
            ->from(ReportEntry::class, 'report')
            ->where('report.message LIKE :statementId')
            ->setParameter('statementId', '%'.$statement->getId().'%')
            ->andWhere('report.group = :group')
            ->setParameter('group', 'statement')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Deletes all ReportEntries of a specific procedure.
     *
     * @param string $procedureId identifies the Procedure
     *
     * @return int number of deleted Reports
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteByProcedure(string $procedureId): int
    {
        $deletedReports = 0;
        $em = $this->getEntityManager();
        /** @var ReportEntry[] $reportsToDelete */
        $reportsToDelete = $this->findBy(['identifier' => $procedureId]);
        foreach ($reportsToDelete as $report) {
            $em->remove($report);
            ++$deletedReports;
        }
        $em->flush();

        return $deletedReports;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Returns an array with the reports belonging to the procedure, filtered by groups and categories.
     *
     * @return ReportEntry[]
     */
    public function getProcedureReportEntries(string $procedureId, array $groups, array $categories): array
    {
        /** @var Query $query */
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('r')
            ->from(ReportEntry::class, 'r')
            ->where('r.identifier = :procedureId')
            ->andWhere('r.group IN (:groups)')
            ->andWhere('r.category IN (:categories)')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('groups', $groups)
            ->setParameter('categories', $categories)
            ->addOrderBy('r.createDate', 'DESC')
            ->getQuery();

        return $query->getResult();
    }
}
