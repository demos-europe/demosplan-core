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
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Exception;

use function array_key_exists;

/**
 * @template-extends FluentRepository<Paragraph>
 */
class ParagraphRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get a paragraph.
     *
     * @param string $entityId
     *
     * @return Paragraph|null
     *
     * @throws NonUniqueResultException
     */
    public function get($entityId)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('p')
                ->from(Paragraph::class, 'p')
                ->where('p.id = :ident')
                ->setParameter('ident', $entityId)
                ->setMaxResults(1)
                ->getQuery();

            return $query->getOneOrNullResult();
        } catch (Exception $e) {
            $this->logger->warning('Get Paragraph failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a list of paragraph.
     *
     * @param array $ids
     *
     * @return Paragraph[]
     *
     * @throws Exception
     */
    public function getByIds($ids)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('p')
                ->from(Paragraph::class, 'p')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
                ->orderBy('p.order', 'asc')
                ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get List Paragraph by Ids failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a the maximum order value of all paragraphs of one element.
     *
     * @param string $elementId
     *
     * @return int
     *
     * @throws Exception
     */
    public function getMaxOrderFromElement($elementId)
    {
        try {
            $em = $this->getEntityManager();
            $queryBuilder = $em->createQueryBuilder()
                ->select('p.order')
                ->from(Paragraph::class, 'p')
                ->where('p.element = :elementId')
                ->setParameter('elementId', $elementId)
                ->orderBy('p.order', 'desc')
                ->setMaxResults(1);

            $query = $queryBuilder->getQuery();

            $result = $query->getResult();

            $maxOrder = 0;
            if (is_array($result) && array_key_exists(0, $result)) {
                $maxOrder = $result[0]['order'];
            } else {
                $this->getLogger()->warning('could not get max paragraph order. Result '.DemosPlanTools::varExport($result, true));
            }

            return $maxOrder;
        } catch (Exception $e) {
            $this->logger->warning('getOrderFromElement failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Increment all paragraphs subsequent to given order by offset.
     *
     * @param int    $order
     * @param string $elementId
     * @param int    $offset
     *
     * @return int
     *
     * @throws Exception
     */
    public function incrementSubsequentOrders($order, $elementId, $offset = 1)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->update(Paragraph::class, 'p')
                ->set('p.order', 'p.order + :offset')
                ->where('p.element = :elementId')
                ->setParameter('elementId', $elementId)
                ->setParameter('offset', $offset)
                ->andWhere('p.order > :order')
                ->setParameter('order', $order)
                ->getQuery();

            $versionQuery = $em->createQueryBuilder()
                ->update(ParagraphVersion::class, 'pv')
                ->set('pv.order', 'pv.order + :offset')
                ->where('pv.element = :elementId')
                ->setParameter('elementId', $elementId)
                ->setParameter('offset', $offset)
                ->andWhere('pv.order > :order')
                ->setParameter('order', $order)
                ->getQuery();

            $result = $query->execute();
            $versionQuery->execute();

            return $result;
        } catch (Exception $e) {
            $this->logger->warning('incrementSubsequentOrders failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add a paragraph.
     *
     * @return Paragraph
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $paragraph = $this->generateObjectValues(new Paragraph(), $data);

            $dateTime = new DateTime();
            $paragraph->setCreateDate($dateTime);
            $paragraph->setModifyDate($dateTime);
            $paragraph->setDeleteDate($dateTime);

            $paragraph->setDeleted(false);

            $em->persist($paragraph);
            $em->flush();

            return $paragraph;
        } catch (Exception $e) {
            $this->logger->warning('Create Paragraph failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update a paragraph.
     *
     * @param string $id
     *
     * @return Paragraph|mixed
     */
    public function update($id, array $data)
    {
        try {
            $em = $this->getEntityManager();

            $paragraph = $this->get($id);
            $paragraph = $this->generateObjectValues($paragraph, $data);

            $dateTime = new DateTime();
            $paragraph->setModifyDate($dateTime);

            $em->persist($paragraph);
            $em->flush();

            return $paragraph;
        } catch (Exception $e) {
            $this->logger->warning('Update Paragraph failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a paragraph.
     *
     * @param string $entityId
     *
     * @return Paragraph|mixed
     */
    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();
            $entity = $this->get($entityId);
            // Wenn es Versionen gibt, führe einen Pseudodelete durch, damit die Referenz erhalten bleibt,
            // weil sich Stellungnahmen darauf beziehen.
            // Wenn es keine Versionen gibt, lösche die Entity komplett
            if (0 < count($entity->getVersions())) {
                $entity->setVisible(0);
                $entity->setDeleted(true);
                $entity->setDeleteDate(new DateTime());
            } else {
                $em->remove($entity);
            }
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Paragraph failed ', [$e]);

            return false;
        }
    }

    /**
     * Deletes all Paragraphs of a procedure.
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
                ->delete(Paragraph::class, 'p')
                ->andWhere('p.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Paragraphs of a procedure failed ', [$e]);
            throw $e;
        }
    }

    public function deleteByProcedureIdAndElementId($procedureId, $elementId)
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->delete(Paragraph::class, 'p')
                ->andWhere('p.procedure = :procedureId')
                ->andWhere('p.element = :elementId')
                ->setParameter('procedureId', $procedureId)
                ->setParameter('elementId', $elementId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Paragraphs of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Kopiert alle Paragraph (Begründung und textliche Festsetzung) von einem Verfahren in ein anderes.
     *
     * @param string $srcProcedureId
     * @param string $destProcedureId
     * @param array  $ids
     *
     * @throws Exception
     */
    public function copy($srcProcedureId, $destProcedureId, $ids = [])
    {
        try {
            $em = $this->getEntityManager();

            $src = $this->getParagraphList($srcProcedureId);
            $parentMapping = [];
            foreach ($src as $paragraph) {
                if (isset($ids[$paragraph->getElementId()])) {
                    $dstParagraph = clone $paragraph;
                    // this call does not trigger a db query, but creates an empty proxy with the ID
                    $dstProcedure = $em->getReference(Procedure::class, $destProcedureId);
                    if (!$dstProcedure instanceof Procedure) {
                        throw ProcedureNotFoundException::createFromId($destProcedureId);
                    }
                    $dstParagraph->setProcedure($dstProcedure);
                    $dstElementId = $ids[$paragraph->getElementId()];
                    $dstElement = $em->getReference(Elements::class, $dstElementId);
                    if (!$dstElement instanceof Elements) {
                        throw StatementElementNotFoundException::createFromId($dstElementId);
                    }
                    $dstParagraph->setElement($dstElement);
                    // set copied paragraph as new parent
                    $dstParagraph->setParent($this->getParentParagraph($paragraph, $parentMapping));
                    $em->persist($dstParagraph);

                    $parentMapping[$paragraph->getId()] = $dstParagraph;
                }
            }
            $em->flush();
        } catch (Exception $e) {
            $this->logger->warning('Copy paragraph failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Paragraph[] $parentMapping
     *
     * @return Paragraph|null
     */
    protected function getParentParagraph(Paragraph $paragraph, array $parentMapping)
    {
        $parentParagraph = $paragraph->getParent();
        if (null !== $parentParagraph && isset($parentMapping[$parentParagraph->getId()])) {
            return $parentMapping[$parentParagraph->getId()];
        }

        return null;
    }

    /**
     * Get a list of paragraph.
     *
     * @param string $procedureId
     *
     * @return Paragraph[]
     *
     * @throws Exception
     */
    public function getParagraphList($procedureId)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                    ->select('p')
                    ->from(Paragraph::class, 'p')
                    ->where('p.procedure = :pid')
                    ->setParameter('pid', $procedureId)
                    ->orderBy('p.order', 'asc')
                    ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get List Paragraph failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Paragraph $entity
     *
     * @return Paragraph
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
        if (array_key_exists('pId', $data)) {
            $procedure = $em->getReference(Procedure::class, $data['pId']);
            if (!$procedure instanceof Procedure) {
                throw ProcedureNotFoundException::createFromId($data['pId']);
            }
            $entity->setProcedure($procedure);
        }
        if (array_key_exists('elementId', $data)) {
            $element = $em->getReference(Elements::class, $data['elementId']);
            if (!$element instanceof Elements) {
                throw StatementElementNotFoundException::createFromId($data['elementId']);
            }
            $entity->setElement($element);
        }
        if (array_key_exists('category', $data)) {
            $entity->setCategory($data['category']);
        }
        if (array_key_exists('order', $data)) {
            $entity->setOrder($data['order']);
        }
        if (array_key_exists('lockReason', $data)) {
            $entity->setLockReason($data['lockReason']);
        }
        if (array_key_exists('visible', $data)) {
            $entity->setVisible($data['visible']);
        }
        if (array_key_exists('parentId', $data)) {
            $parent = null;
            if (null !== $data['parentId']) {
                $parent = $em->getReference(Paragraph::class, $data['parentId']);
            }
            $entity->setParent($parent);
        }

        return $entity;
    }

    public function addObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param Paragraph $paragraph
     *
     * @return Paragraph
     *
     * @throws Exception
     */
    public function updateObject($paragraph)
    {
        try {
            $em = $this->getEntityManager();

            $em->persist($paragraph);
            $em->flush();

            return $paragraph;
        } catch (Exception $e) {
            $this->logger->warning('Update Paragraph failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Paragraph $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    public function copyParagraphsOfElement(Elements $elementToCopy, Elements $copiedElement): void
    {
        $paragraphIdMapping = [];
        $paragraphsToCopy = $this->getParagraphOfElement($elementToCopy);
        foreach ($paragraphsToCopy as $paragraphToCopy) {
            $copiedParagraph = new Paragraph();
            $copiedParagraph->setCategory($paragraphToCopy->getCategory());
            $copiedParagraph->setTitle($paragraphToCopy->getTitle());
            $copiedParagraph->setText($paragraphToCopy->getText());
            $copiedParagraph->setOrder($paragraphToCopy->getOrder());
            $copiedParagraph->setDeleted($paragraphToCopy->getDeleted());
            $copiedParagraph->setVisible($paragraphToCopy->getVisible());
            $copiedParagraph->setLockReason($paragraphToCopy->getLockReason());
            $copiedParagraph->setParent($paragraphToCopy->getParent()); // Will be used and fixed later
            $copiedParagraph->setModifyDate(new DateTime());
            $copiedParagraph->setCreateDate(new DateTime());
            $copiedParagraph->setDeleteDate(new DateTime());

            $copiedParagraph->setElement($copiedElement);
            $copiedParagraph->setProcedure($copiedElement->getProcedure());

            $this->getEntityManager()->persist($copiedParagraph);
            $paragraphIdMapping[$paragraphToCopy->getId()] = $copiedParagraph->getId();
        }
        $this->getEntityManager()->persist($copiedElement);
        $this->getEntityManager()->flush();

        $copiedParagraphs = $this->getParagraphOfElement($copiedElement);
        foreach ($copiedParagraphs as $copiedParagraph) {
            if ($copiedParagraph->getParent() instanceof Paragraph) {
                $oldParentId = $copiedParagraph->getParent()->getId();
                $relatedCopiedParagraph = $this->getEntityManager()->getReference(
                    Paragraph::class, $paragraphIdMapping[$oldParentId]
                );
                $copiedParagraph->setParent($relatedCopiedParagraph);
            }
            $this->getEntityManager()->persist($copiedParagraph);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<int, Paragraph>
     */
    public function getParagraphOfElement(Elements $element): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('paragraph')
            ->from(Paragraph::class, 'paragraph')
            ->where('paragraph.element = :elementId')
            ->setParameter('elementId', $element->getId())
            ->getQuery()->getResult();
    }
}
