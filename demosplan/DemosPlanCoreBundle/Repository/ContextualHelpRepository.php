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
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends FluentRepository<ContextualHelp>
 */
class ContextualHelpRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Fetch all contextualHelp items from DB.
     *
     * @return ContextualHelp[]|null
     */
    public function getAllContextualHelp(): ?array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('contextualHelp')
            ->from(ContextualHelp::class, 'contextualHelp')
            ->orderBy('contextualHelp.key', 'asc')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Fetch all contextualHelp items from DB that are not gisLayer related.
     *
     * @return ContextualHelp[]|null
     */
    public function getNonGisLayerRelatedContextualHelp(): ?array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('contextualHelp')
            ->from(ContextualHelp::class, 'contextualHelp')
            ->orderBy('contextualHelp.key', 'asc')->getQuery();
        $allHelpItems = $query->getResult();

        // exclude gislayer
        return array_filter($allHelpItems, fn ($elem) => !str_contains((string) $elem->getKey(), 'gislayer'));
    }

    /**
     * Get single contextualHelp form DB by id.
     *
     * @param string $id
     *
     * @return ContextualHelp|null
     *
     * @throws Exception
     */
    public function get($id)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('contextualHelp')
            ->from(ContextualHelp::class, 'contextualHelp')
            ->where('contextualHelp.ident = :ident')
            ->setParameter('ident', $id)
            ->setMaxResults(1)
            ->getQuery();
        try {
            $result = $query->getResult();
            if (1 === (is_countable($result) ? count($result) : 0)) {
                return $result[0];
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('GetSingleContextualHelp failed, Id: '.$id.' Message:', [$e]);
            throw $e;
        }
    }

    /**
     * Get one single contextual help item by key from DB.
     *
     * @param string $key
     *
     * @return ContextualHelp|null
     */
    public function getByKey($key)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('contextualHelp')
            ->from(ContextualHelp::class, 'contextualHelp')
            ->where('contextualHelp.key = :key')
            ->setParameter('key', $key)
            ->setMaxResults(1)
            ->getQuery();

        try {
            $result = $query->getResult();
            if (1 === (is_countable($result) ? count($result) : 0)) {
                return $result[0];
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('GetSingleContextualHelp failed, Key: '.$key.' Message:', [$e]);

            return null;
        }
    }

    /**
     * Creates a copy of a ContextualHelp Entity and returns it.
     *
     * @return ContextualHelp|null
     *
     * @throws Exception
     */
    public function copy(GisLayer $sourceGisLayer, GisLayer $newGisLayer)
    {
        try {
            /** @var ContextualHelp|null $contextualHelp */
            $contextualHelp = $this->findOneBy(['ident' => $sourceGisLayer->getContextualHelp()->getId()]);

            $newContextualHelp = clone $contextualHelp;
            $newContextualHelp->setIdent(null);
            $newContextualHelp->setCreateDate(null);
            $newContextualHelp->setModifyDate(null);
            $newContextualHelp->setKey('gislayer.'.$newGisLayer->getId());

            $this->getEntityManager()->persist($newContextualHelp);
            $this->getEntityManager()->flush();

            return $newContextualHelp;
        } catch (Exception $e) {
            $this->logger->warning('Copy ContextualHelp failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update from a specific contextualhelp item.
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function update($id, array $data)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('contextualHelp')
            ->from(ContextualHelp::class, 'contextualHelp')
            ->where('contextualHelp.ident = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery();
        $help = $query->getSingleResult();

        if (isset($data['text']) && !is_null($data['text'])) {
            // is there a difference between old and new entry
            if ($help->getText() != $data['text']) {
                // if yes, update entry
                $help->setText($data['text']);
                $this->getEntityManager()->persist($help);
                $this->getEntityManager()->flush();
            }

            // if no just return
            return true;
        }

        return false;
    }

    public function add(array $data): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Deletes the contextual help with the given id.
     *
     * @param string $id
     *
     * @return bool
     */
    public function deleteById($id)
    {
        $help = $this->get($id);

        return $this->delete($help);
    }

    /**
     * Deletes the given contextual help.
     *
     * @param ContextualHelp $entity
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($entity)
    {
        if (is_null($entity)) {
            $this->logger->warning('Delete ContextualHelp failed: Given ID not found.');
            throw new EntityNotFoundException('Delete ContextualHelp failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($entity);

            // if gisLayer exists with reference, remove reference
            /** @var MapRepository $gisLayerRepo */
            $gisLayerRepo = $this->getEntityManager()->getRepository(GisLayer::class);
            $gisLayer = $gisLayerRepo->findOneBy(['contextualHelp' => $entity->getId()]);
            if (null !== $gisLayer) {
                $gisLayer->setContextualHelp(null);
                $gisLayerRepo->updateObject($gisLayer);
            }

            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete ContextualHelp failed: ', [$e]);
        }

        return false;
    }

    /**
     * @param ContextualHelp $entity
     *
     * @return ContextualHelp
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }

    /**
     * @param ContextualHelp $entity
     *
     * @return ContextualHelp
     *
     * @throws Exception
     */
    public function addObject($entity)
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $entity;
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
