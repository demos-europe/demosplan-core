<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Exception;
use ReflectionException;

/**
 * @template-extends CoreRepository<SingleDocumentVersion>
 */
class SingleDocumentVersionRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Add single document entry.
     *
     * @return SingleDocumentVersion
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $singleDocumentVersion = $this->generateObjectValues(new SingleDocumentVersion(), $data);

            $singleDocumentVersion->setDeleted(false);
            $singleDocumentVersion->setVisible(true);

            $em->persist($singleDocumentVersion);
            $em->flush();

            return $singleDocumentVersion;
        } catch (Exception $e) {
            $this->logger->warning('Create SingleDocumentVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a single document entry.
     *
     * @param string $entityId
     *
     * @return SingleDocumentVersion|null
     *
     * @throws Exception
     */
    public function get($entityId)
    {
        try {
            $query = $this->getEntityManager()
                    ->createQueryBuilder()
                    ->select('sdv')
                    ->from(SingleDocumentVersion::class, 'sdv')
                    ->where('sdv.id = :ident')
                    ->setParameter('ident', $entityId)
                    ->setMaxResults(1)
                    ->getQuery();

            return $query->getOneOrNullResult();
        } catch (Exception $e) {
            $this->logger->warning('Get SingleDocumentVersion failed. Message: ', [$e]);
            throw $e;
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
            ->select('sdv.document')
            ->from(SingleDocumentVersion::class, 'sdv')
            ->where('sdv.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of SingleDocumentVersion failed ', [$e]);

            return null;
        }
    }

    /**
     * @param string $entityId
     *
     * @return SingleDocumentVersion|null
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $singleDocumentVersion = $this->get($entityId);
            $singleDocumentVersion = $this->generateObjectValues($singleDocumentVersion, $data);

            $dateTime = new DateTime();
            $singleDocumentVersion->setModifyDate($dateTime);

            $em->persist($singleDocumentVersion);
            $em->flush();

            return $singleDocumentVersion;
        } catch (Exception $e) {
            $this->logger->warning('Update SingleDocumentVersion failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all Versions with a specific document-ID.
     *
     * @param string $documentId
     *
     * @return bool true if all found versions was deleted, otherwise false
     */
    public function deleteByDocumentId($documentId)
    {
        try {
            $versionsToDelete = $this->findBy(['singleDocument' => $documentId]);
            foreach ($versionsToDelete as $version) {
                $this->getEntityManager()->remove($version);
            }
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete SingleDocumentVersion by documentID failed. Message: ', [$e]);

            return false;
        }
    }

    /**
     * Deletes all SingleDocumentVersions of a procedure.
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
                ->delete(SingleDocumentVersion::class, 'sdv')
                ->andWhere('sdv.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete SingleDocumentVersions of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $entityId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();

            $singleDocumentVersion = $this->get($entityId);

            $em->remove($singleDocumentVersion);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete SingleDocumentVersion failed. Message: ', [$e]);

            return false;
        }
    }

    /**
     * Copy SingleDocumentVersion from SingleDocument.
     *
     * @return SingleDocumentVersion
     *
     * @throws Exception
     */
    public function createVersion(SingleDocument $singleDocument)
    {
        try {
            if (!$singleDocument instanceof SingleDocument) {
                throw new Exception('DraftStatement to copyfrom has to be of Type DraftStatement');
            }
            $em = $this->getEntityManager();

            $singleDocumentVersion = $this->generateObjectValuesFromObject(new SingleDocumentVersion(), $singleDocument);
            $singleDocumentVersion->setSingleDocument($singleDocument);
            $em->persist($singleDocumentVersion);
            $em->flush();

            return $singleDocumentVersion;
        } catch (Exception $e) {
            $this->logger->warning('Create SingleDocumentVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Convert a array values.
     *
     * @param SingleDocumentVersion $entity
     *
     * @return SingleDocumentVersion
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }

    /**
     * Copy properties from DraftStatement to DraftStatementVersion.
     *
     * @param SingleDocumentVersion $copyToEntity
     * @param SingleDocument        $copyFromEntity
     * @param array                 $excludeProperties
     *
     * @return SingleDocumentVersion
     *
     * @throws ReflectionException
     */
    protected function generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties = [])
    {
        $excludeProperties = ['id', 'pId', 'dId', 'elementId'];

        return parent::generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties);
    }
}
