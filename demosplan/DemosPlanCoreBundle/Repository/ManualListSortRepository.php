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
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableArrayInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends CoreRepository<ManualListSort>
 */
class ManualListSortRepository extends CoreRepository implements ImmutableArrayInterface
{
    /**
     * Get Entity by Context.
     *
     * @param string $context
     *
     * @throws NonUniqueResultException
     */
    public function get($context): ?ManualListSort
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('manualSort')
            ->from(ManualListSort::class, 'manualSort')
            ->where('manualSort.context = :context')
            ->setParameter('context', $context)
            ->setMaxResults(1)
            ->getQuery();
        try {
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get ManualListSort failed, Id: '.$context, [$e]);

            return null;
        }
    }

    /**
     * @param string $procedure
     * @param string $context
     * @param string $namespace
     *
     * @return ManualListSort|mixed|null
     */
    public function getManualListSort($procedure, $context, $namespace, $customer)
    {
        $result = $this->findBy(['pId' => $procedure, 'context' => $context, 'namespace' => $namespace, 'customer' => $customer]);
        if (0 < sizeof($result)) {
            return $result[0];
        }

        return null;
    }

    /**
     * @param string $context
     *
     * @throws Exception
     */
    public function setManualSort($context, array $data): bool
    {
        $procedure = null;
        $idents = null;
        $namespace = null;

        if (array_key_exists('ident', $data)) {
            $procedure = $data['ident'];
        }

        if (array_key_exists('sortIdent', $data)) {
            $idents = $data['sortIdent'];
        }

        if (array_key_exists('namespace', $data)) {
            $namespace = $data['namespace'];
        }

        if (array_key_exists('customer', $data)) {
            $customer = $data['customer'];
        }

        if (is_null($procedure) || is_null($context) || is_null($idents) || is_null($namespace) || is_null($customer)) {
            return false;
        }

        if ('' === $idents) {
            return $this->deleteManualSort($procedure, $context, $namespace, $customer);
        }

        return $this->addList($procedure, $context, $namespace, $idents, $customer);
    }

    /**
     * Modifies the ident-list of a manualListSort-entry.
     * If the given parameters do not identify a existing list, a new one will be created.
     *
     * @param string $procedureId the assigned procedure of the entry
     * @param string $context     contextinformations of the entry
     * @param string $namespace   namespaceinformations of the entry
     * @param string $idents      identifier of the entries in the right order
     *
     * @return bool false, if the entry was not found, true if the modification was successful
     *
     * @throws Exception
     */
    public function addList($procedureId, $context, $namespace, $idents, $customer): bool
    {
        $sort = new ManualListSort();
        $sort->setPId($procedureId);
        $sort->setContext($context);
        $sort->setNamespace($namespace);
        $sort->setCustomer($customer);

        $manualListSorts = $this->findBy(['pId' => $procedureId, 'context' => $context, 'namespace' => $namespace, 'customer' => $customer]);
        if (0 < sizeof($manualListSorts)) {
            $sort = $manualListSorts[0];
        }

        $sort->setIdents($idents);

        if (is_null($sort->getPId())) {
            return false;
        }

        if (is_null($sort->getContext())) {
            $sort->setContext('');
        }

        if (is_null($sort->getIdents())) {
            $sort->setIdents('');
        }

        if (is_null($sort->getNamespace())) {
            $sort->setNamespace('');
        }

        try {
            $this->getEntityManager()->persist($sort);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Setting/Update ManualListSort failed Reason: ', [$e]);
            throw $e;
        }
    }

    public function add(array $data): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Delete a manual ListSort.
     *
     * @param string $procedure
     * @param string $context
     * @param string $namespace
     */
    public function deleteManualSort($procedure, $context, $namespace): bool
    {
        $em = $this->getEntityManager();
        $mls = $this->getManualListSort($procedure, $context, $namespace);
        if (!is_null($mls)) {
            $em->remove($mls);
            $em->flush();
            $this->logger->info('ManualListSort deleted');

            return true;
        }
        $this->logger->info('ManualListSort not found. Could not delete');

        return false;
    }

    /**
     * Deletes all ManualListSorts of a procedure.
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
                ->delete(ManualListSort::class, 'mls')
                ->andWhere('mls.pId = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete ManualListSorts of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param ManualListSort $manualListSort
     *
     * @return CoreEntity
     */
    public function generateObjectValues($manualListSort, array $data)
    {
        if (array_key_exists('pId', $data)) {
            $manualListSort->setPId($data['pId']);
        }
        if (array_key_exists('namespace', $data)) {
            $manualListSort->setNamespace($data['namespace']);
        }
        if (array_key_exists('context', $data)) {
            $manualListSort->setContext($data['context']);
        }
        if (array_key_exists('idents', $data)) {
            $manualListSort->setIdents($data['idents']);
        }

        return $manualListSort;
    }
}
