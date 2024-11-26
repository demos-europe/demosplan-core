<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template-extends CoreRepository<Tag>
 */
class TagRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Tag|null
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get tag failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param Tag $tag
     *
     * @return Tag
     *
     * @throws Exception
     */
    public function addObject($tag)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($tag);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add tag failed: ', [$e]);

            throw $e;
        }

        return $tag;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function deleteById($entityId)
    {
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Delete Entity.
     *
     * @param Tag $toDelete
     *
     * @throws InvalidArgumentException
     */
    public function delete($toDelete): bool
    {
        if (null === $toDelete) {
            $this->logger->warning('Delete Tag failed: Given Object not found.');
            throw new InvalidArgumentException('Delete Tag failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete Tag failed: ', [$e]);
        }

        return false;
    }

    /**
     * @param Tag $entity
     *
     * @return bool
     */
    public function deleteObject($entity)
    {
        return $this->delete($entity);
    }

    /**
     * Update Entity.
     *
     * @param Tag $tag
     *
     * @return Tag|false
     */
    public function updateObject($tag)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($tag);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Update tag failed: ', [$e]);

            return false;
        }

        return $tag;
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, Tag>
     */
    public function findByIds(array $ids): array
    {
        return $this->findBy(['id' => $ids]);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getByTitle(string $title, string $procedureId): ?Tag
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('tag')
            ->from(Tag::class, 'tag')
            ->leftJoin(
                TagTopic::class,
                'topic',
                Join::WITH,
                'tag.topic = topic.id'
            )
            ->where('topic.procedure = :procedure')
            ->andWhere('tag.title = :title')
            ->setParameter('procedure', $procedureId)
            ->setParameter('title', $title)
            ->setMaxResults(1)
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    public function isTagTitleFree(string $procedureId, string $title): bool
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('count(tag.id)')
            ->from(Tag::class, 'tag')
            ->leftJoin(
                TagTopic::class,
                'topic',
                Join::WITH,
                'tag.topic = topic.id')
            ->where('topic.procedure = :procedure')
            ->andWhere('tag.title = :title')
            ->setParameter('procedure', $procedureId)
            ->setParameter('title', $title)
            ->setMaxResults(1)
            ->getQuery();

        $singleScalarResult = $query->getSingleScalarResult();
        Assert::integer($singleScalarResult);

        return 0 === $singleScalarResult;
    }
}
