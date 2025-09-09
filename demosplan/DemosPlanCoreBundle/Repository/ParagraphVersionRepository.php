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
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<ParagraphVersion>
 */
class ParagraphVersionRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get a paragraph.
     *
     * @param string $id
     *
     * @return ParagraphVersion|null
     *
     * @throws Exception
     */
    public function get($id)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('paragraphVersion')
                ->from(ParagraphVersion::class, 'paragraphVersion')
                ->where('paragraphVersion.id = :ident')
                ->setParameter('ident', $id)
                ->setMaxResults(1)
                ->getQuery();

            return $query->getOneOrNullResult();
        } catch (Exception $e) {
            $this->logger->warning('Get paragraphVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add a paragraph.
     *
     * @internal
     *
     * @return void
     */
    public function add(array $data)
    {
        // Version can only be copied
    }

    /**
     * Update a paragraphversion not possible.
     *
     * @param string $id
     *
     * @internal
     *
     * @return void
     */
    public function update($id, array $data)
    {
        // Version could not be updated
    }

    /**
     * Deletes all Versions with a specific paragraph-ID.
     *
     * @param string $paragraphId
     *
     * @return bool true if all found versions was deleted, otherwise false
     */
    public function deleteByParagraphId($paragraphId)
    {
        try {
            $versionsToDelete = $this->findBy(['paragraph' => $paragraphId]);
            foreach ($versionsToDelete as $version) {
                $this->getEntityManager()->remove($version);
            }
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete ParagraphVersion by paragraph-ID failed. Message: ', [$e]);

            return false;
        }
    }

    /**
     * Deletes all ParagraphVersions of a procedure.
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
                ->delete(ParagraphVersion::class, 'pv')
                ->andWhere('pv.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete ParagraphVersions of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all paragraph versions that are derived from the given paragraph.
     *
     * @param Paragraph $paragraph The paragraph that we search for
     *
     * @return array[ParagraphVersion]
     *
     * @throws Exception
     */
    public function getVersionsFromParagraph($paragraph)
    {
        try {
            return $this->findBy([
                'paragraph' => $paragraph,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Getting ParagraphVersions of a paragraph failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a paragraphVersion.
     *
     * @param string $id
     *
     * @return bool
     */
    public function delete($id)
    {
        try {
            $em = $this->getEntityManager();

            $paragraph = $this->get($id);

            $em->remove($paragraph);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete ParagraphVersion failed Message: ', [$e]);

            return false;
        }
    }

    /**
     * Get a list of paragraphVersion.
     *
     * @param array $ids
     *
     * @return ParagraphVersion[]
     *
     * @throws Exception
     */
    public function getVersionByIds($ids)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('pv')
                ->from(ParagraphVersion::class, 'pv')
                ->where('pv.id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
                ->orderBy('pv.order', 'asc')
                ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get List ParagraphVersion by Ids failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Creates and adds a ParagraphVersion from the given Paragraph.
     *
     * @throws Exception
     */
    public function createVersion(Paragraph $paragraph): ParagraphVersion
    {
        try {
            $paragraphVersion = $this->generateObjectValuesFromObject(new ParagraphVersion(), $paragraph);
            $paragraphVersion->setParagraph($paragraph);

            return $paragraphVersion;
        } catch (Exception $e) {
            $this->logger->warning('Create ParagraphVersion failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Copy properties from DraftStatement to DraftStatementVersion.
     *
     * @param ParagraphVersion $copyToEntity
     * @param Paragraph        $copyFromEntity
     * @param array            $excludeProperties
     *
     * @return ParagraphVersion
     */
    protected function generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties = [])
    {
        $excludeProperties = ['id', 'pId', 'dId', 'oId', 'uId', 'paragraphId', 'elementId'];

        return parent::generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties);
    }

    /**
     * @param ParagraphVersion $entity
     *
     * @return ParagraphVersion
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('title', $data)) {
            $entity->setTitle($data['title']);
        }
        if (array_key_exists('text', $data)) {
            $entity->setText($data['text']);
        }
        if (array_key_exists('pId', $data)) {
            $entity->setPId($data['pId']);
        }
        if (array_key_exists('elementId', $data)) {
            $entity->setElementId($data['elementId']);
        }
        if (array_key_exists('category', $data)) {
            $entity->setCategory($data['category']);
        }
        if (array_key_exists('order', $data)) {
            $entity->setOrder($data['order']);
        }
        if (array_key_exists('parentId', $data)) {
            $parent = null;
            if (!is_null($data['parentId'])) {
                $parent = $this->getEntityManager()->getReference(
                    ParagraphVersion::class, $data['parentId']);
            }
            $entity->setParent($parent);
        }

        return $entity;
    }

    /**
     * @param ParagraphVersion $paragraphVersion
     *
     * @return ParagraphVersion
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($paragraphVersion)
    {
        $this->getEntityManager()->persist($paragraphVersion);
        $this->getEntityManager()->flush();

        return $paragraphVersion;
    }

    public function updateObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
