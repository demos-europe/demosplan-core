<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Exception;

/**
 * @template-extends CoreRepository<TagTopic>
 */
class TagTopicRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return TagTopic
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get TagTopic failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param TagTopic $tagTopic
     *
     * @return TagTopic
     *
     * @throws Exception
     */
    public function addObject($tagTopic)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($tagTopic);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add TagTopic failed: ', [$e]);

            throw $e;
        }

        return $tagTopic;
    }

    /**
     * Update Entity.
     *
     * @param TagTopic $tagTopic
     *
     * @return TagTopic|false
     */
    public function updateObject($tagTopic)
    {
        try {
            $this->getEntityManager()->persist($tagTopic);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update TagTopic failed: ', [$e]);

            return false;
        }

        return $tagTopic;
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
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Delete Entity.
     *
     * @param TagTopic $toDelete
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete): bool
    {
        if (null === $toDelete) {
            $this->logger->warning('Delete TagTopic failed: Given ID not found.');
            throw new EntityNotFoundException('Delete TagTopic failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete TagTopic failed: ', [$e]);
        }

        return false;
    }

    /**
     * @param TagTopic $entity
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
     * Returns all tags of a specific TagTopic.
     *
     * @param string $id identifies the TagTopic
     *
     * @return array of Tags
     */
    public function getTags($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQueryBuilder()
            ->select('tag')
            ->from(Tag::class, 'tag')
            ->where('tag.topic = :id')
            ->setParameter('id', $id)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Copy the non-generated values of all topics and all related tags of a specific procedure.
     * Set the generated values to null, for regeneration.
     *
     * @param string    $sourceProcedureId
     * @param Procedure $newProcedure
     *
     * @throws Exception
     *
     * @internal param $newProcedureId
     */
    public function copy($sourceProcedureId, $newProcedure)
    {
        try {
            $topics = $this->findBy(['procedure' => $sourceProcedureId]);

            if (0 < sizeof($topics)) {
                $newProcedure->detachAllTopics();
                /** @var TagTopic $singletopic */
                foreach ($topics as $singletopic) {
                    $newTopic = new TagTopic($singletopic->getTitle(), $newProcedure);

                    /** @var Tag $existingTag */
                    foreach ($singletopic->getTags() as $existingTag) {
                        $newTag = new Tag($existingTag->getTitle(), $newTopic);
                        $newTag->setBoilerplate($existingTag->getBoilerplate());
                        $this->getEntityManager()->persist($newTag);
                        $newTopic->addTag($newTag);
                    }

                    $this->getEntityManager()->persist($newTopic);
                    $newProcedure->addTagTopic($newTopic);
                }
            }

            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->warning('Copy tags failed. Message: ', [$e]);
            throw $e;
        }
    }

    public function findOneByTitle(string $title, string $procedureId): ?TagTopic
    {
        return $this->findOneBy(['title' => $title, 'procedure' => $procedureId]);
    }
}
