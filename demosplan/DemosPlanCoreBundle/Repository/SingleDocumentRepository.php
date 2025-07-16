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
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<SingleDocument>
 */
class SingleDocumentRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Add single document entry.
     *
     * @throws Exception
     */
    public function add(array $data): SingleDocument
    {
        try {
            $em = $this->getEntityManager();
            $singleDocument = $this->generateObjectValues(new SingleDocument(), $data);

            $singleDocument->setDeleted(false);
            $singleDocument->setVisible(true);

            $em->persist($singleDocument);
            $em->flush();

            return $singleDocument;
        } catch (Exception $e) {
            $this->logger->warning('Create SingleDocument failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a single document entry.
     *
     * @param string $entityId
     *
     * @return SingleDocument|null
     *
     * @throws Exception
     */
    public function get($entityId)
    {
        try {
            $query = $this->getEntityManager()
                    ->createQueryBuilder()
                    ->select('sd')
                    ->from(SingleDocument::class, 'sd')
                    ->where('sd.id = :ident')
                    ->setParameter('ident', $entityId)
                    ->setMaxResults(1)
                    ->getQuery();

            return $query->getOneOrNullResult();
        } catch (Exception $e) {
            $this->logger->warning('Get SingleDocument failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Kopiert alle SingleDocument (Planunterlagen) von einem Verfahren in ein anderes.
     *
     * @throws Exception
     */
    public function copy(string $srcProcedureId, string $destProcedureId, array $elementIdMapping = []): void
    {
        $entityManager = $this->getEntityManager();
        try {
            $documentsToCopy = $this->getSingleDocumentList($srcProcedureId);
            $destProcedure = $entityManager->getReference(Procedure::class, $destProcedureId);
            if (!$destProcedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($destProcedureId);
            }

            foreach ($documentsToCopy as $singleDocument) {
                // there are no singleDocuments without elements:
                if (isset($elementIdMapping[$singleDocument->getElementId()])) {
                    $dstSingleDocument = clone $singleDocument;
                    $dstSingleDocument->setProcedure($destProcedure);

                    $relatedDstElementId = $elementIdMapping[$singleDocument->getElementId()];
                    $relatedDstElement = $entityManager->getReference(Elements::class, $relatedDstElementId);
                    if (!$relatedDstElement instanceof Elements) {
                        throw StatementElementNotFoundException::createFromId($relatedDstElement);
                    }
                    $dstSingleDocument->setElement($relatedDstElement);
                    $relatedDstElement->getDocuments()->add($dstSingleDocument);

                    $entityManager->persist($dstSingleDocument);
                }
            }
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->warning(
                'Copy SingleDocument failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a list of SingleDocument.
     *
     * @param string $procedureId
     *
     * @return SingleDocument[]
     *
     * @throws Exception
     */
    public function getSingleDocumentList($procedureId)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('sd')
                ->from(SingleDocument::class, 'sd')
                ->where('sd.procedure = :pid')
                ->setParameter('pid', $procedureId)
                ->orderBy('sd.order', 'asc')
                ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get a list of SingleDocument failed. Message: ', [$e]);
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
            ->select('sd.document')
            ->from(SingleDocument::class, 'sd')
            ->where('sd.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of SingleDocument failed ', [$e]);

            return [];
        }
    }

    /**
     * @param string $entityId
     *
     * @return SingleDocument|null
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $singleDocument = $this->get($entityId);
            $singleDocument = $this->generateObjectValues($singleDocument, $data);

            $dateTime = new DateTime();
            $singleDocument->setModifyDate($dateTime);

            $em->persist($singleDocument);
            $em->flush();

            return $singleDocument;
        } catch (Exception $e) {
            $this->logger->warning('Update SingleDocument failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $entityId
     *
     * @return SingleDocument|null
     *
     * @throws Exception
     */
    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();

            $singleDocument = $this->get($entityId);
            // Wenn es Versionen gibt, führe einen Pseudodelete durch, damit die Referenz erhalten bleibt,
            // weil sich Stellungnahmen darauf beziehen.
            // Wenn es keine Versionen gibt, lösche die Entity komplett
            if (0 < count($singleDocument->getVersions())) {
                $singleDocument->setVisible(false);
                $singleDocument->setDeleted(true);
                $singleDocument->setDeleteDate(new DateTime());
            } else {
                $em->remove($singleDocument);
            }

            $em->flush();

            return $singleDocument;
        } catch (Exception $e) {
            $this->logger->warning('Delete SingleDocument failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all SingleDocuments of a procedure.
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
                ->delete(SingleDocument::class, 'sd')
                ->andWhere('sd.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete SingleDocuments of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Convert a array values.
     *
     * @param SingleDocument $entity
     *
     * @return SingleDocument
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        $em = $this->getEntityManager();

        if (array_key_exists('title', $data)) {
            $entity->setTitle($data['title']);
        }
        if (array_key_exists('text', $data)) {
            $entity->setText($data['text']);
        }
        if (array_key_exists('pId', $data) && 0 < strlen((string) $data['pId'])) {
            $procedure = $em->getReference(Procedure::class, $data['pId']);
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($data['pId']);
            }
            $entity->setProcedure($procedure);
        }
        if (array_key_exists('visible', $data)) {
            $entity->setVisible($data['visible']);
        }
        if (array_key_exists('statement_enabled', $data)) {
            $entity->setStatementEnabled($data['statement_enabled']);
        }
        if (array_key_exists('symbol', $data)) {
            $entity->setSymbol($data['symbol']);
        }
        if (array_key_exists('elementId', $data) && 0 < strlen((string) $data['elementId'])) {
            $element = $em->getReference(Elements::class, $data['elementId']);
            if (!$element instanceof Elements) {
                throw StatementElementNotFoundException::createFromId($data['elementId']);
            }
            $entity->setElement($element);
        }
        if (array_key_exists('document', $data)) {
            $entity->setDocument($data['document']);
        }
        if (array_key_exists('category', $data)) {
            $entity->setCategory($data['category']);
        }
        if (array_key_exists('order', $data) && null !== $entity) {
            $entity->setOrder($data['order']);
        }

        return $entity;
    }

    /**
     * Get all SingleDocuments of a procedure.
     *
     * @param string $procedureId
     *
     * @return array|null
     */
    public function getSingleDocumentsByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('sd')
            ->from(SingleDocument::class, 'sd')
            ->where('sd.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of SingleDocument failed ', [$e]);

            return [];
        }
    }

    /**
     * Given a $procedureId returns all SingleDocuments belonging to it with the given visibility status.
     */
    public function getProcedureDocumentsByVisibleStatus(string $procedureId, bool $visible): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('sd')
            ->from(SingleDocument::class, 'sd')
            ->andWhere('sd.procedure = :procedureId')->setParameter('procedureId', $procedureId)
            ->andWhere('sd.visible= :enabled')->setParameter('enabled', $visible)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Files of SingleDocument failed ', [$e]);

            return [];
        }
    }

    public function copyDocumentOfElement(SingleDocument $documentToCopy, Elements $relatedElementCopy): SingleDocument
    {
        $copiedDocument = new SingleDocument();
        $copiedDocument->setCategory($documentToCopy->getCategory());
        $copiedDocument->setProcedure($relatedElementCopy->getProcedure());
        $copiedDocument->setDocument($documentToCopy->getDocument()); // this will be used and reset later in ProcedureService::copyDocumentRelatedFiles
        $copiedDocument->setVisible($documentToCopy->getVisible());
        $copiedDocument->setTitle($documentToCopy->getTitle());
        $copiedDocument->setText($documentToCopy->getText());
        $copiedDocument->setSymbol($documentToCopy->getSymbol());
        $copiedDocument->setStatementEnabled($documentToCopy->isStatementEnabled());
        $copiedDocument->setOrder($documentToCopy->getOrder());
        $copiedDocument->setDeleted($documentToCopy->getDeleted());
        $copiedDocument->setModifyDate(new DateTime());
        $copiedDocument->setDeleteDate(new DateTime());
        $copiedDocument->setCreateDate(new DateTime());

        $copiedDocument->setElement($relatedElementCopy);
        $relatedElementCopy->getDocuments()->add($copiedDocument);

        $this->getEntityManager()->persist($copiedDocument);
        $this->getEntityManager()->persist($relatedElementCopy);

        return $copiedDocument;
    }
}
