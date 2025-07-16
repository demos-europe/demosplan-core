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
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStoryVote;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;
use Exception;

/**
 * @template-extends CoreRepository<DevelopmentUserStory>
 */
class DevelopmentUserStoryRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     */
    public function get($entityId)
    {
        $entry = $this->find($entityId);
        if (is_null($entry)) {
            $this->logger->error('Get UserStory failed: Entry with ID: '.$entityId.' not found.');
            throw new EntityNotFoundException('Get Entry UserStory: Entry with ID: '.$entityId.' not found.');
        }

        return $entry;
    }

    /**
     * @param string $releaseId
     *
     * @return array
     *
     * @throws EntityNotFoundException
     */
    public function getDevelopmentUserStoryList($releaseId)
    {
        try {
            $relatedRelease = $this->getEntityManager()->getRepository(DevelopmentRelease::class)->find($releaseId);
            if (is_null($relatedRelease)) {
                $this->logger->error('Get Userstories failed: Given releaseId: '.$releaseId.' not found.');
                throw new EntityNotFoundException("Get Userstories failed: Given releaseId: .$releaseId.not found.");
            }

            // select Order:
            if (0 === strcmp($relatedRelease->getPhase(), 'voting_online')) {
                $list = $this->findBy(['release' => $releaseId]);
                shuffle($list);
            } else {
                if (0 === strcmp($relatedRelease->getPhase(), 'configuration')) {
                    $list = $this->findBy(['release' => $releaseId], ['createDate' => 'ASC']);
                } else {
                    $list = $this->getListOrderByTotalVotes($releaseId);
                }
            }

            return ['release' => $relatedRelease, 'userStories' => $list];
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Abruf der Userstories zu Release : '.$releaseId.' ', [$e]);

            return ['release' => [], 'userStories' => []];
        }
    }

    /**
     * Get List of votes ordered by total Votes.
     *
     * @param string $releaseId
     * @param string $sortDir
     *
     * @return array DevelopmentUserStory[]
     *
     * @throws Exception
     */
    public function getListOrderByTotalVotes($releaseId, $sortDir = 'DESC')
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('us')
                ->from(DevelopmentUserStory::class, 'us')
                ->addSelect('(us.onlineVotes + us.offlineVotes) AS HIDDEN total_votes')
                ->where('us.release = :releaseId')
                ->setParameter('releaseId', $releaseId)
                ->orderBy('total_votes', $sortDir)
                ->getQuery();

            return $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Get getListOrderByTotalVotes failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return CoreEntity
     */
    public function add(array $data)
    {
        if (!array_key_exists('releaseId', $data)) {
            $this->logger->error('Add UserStory failed: No releaseId in given array');
            throw new MissingDataException('Add UserStory failed: No releaseId in given array');
        }

        if (!array_key_exists('title', $data)) {
            $this->logger->error('Add UserStory failed: No title in given array');
            throw new MissingDataException('Add UserStory failed: No title in given array');
        }

        $relatedRelease = $this->getEntityManager()->getRepository(DevelopmentRelease::class)
            ->find($data['releaseId']);

        $toAdd = $this->generateObjectValues(new DevelopmentUserStory(), $data);
        $toAdd->setRelease($relatedRelease);

        $thread = new ForumThread();
        $thread->setProgression(true);

        $toAdd->setThread($thread);

        $this->getEntityManager()->persist($toAdd);
        $this->getEntityManager()->flush();

        return $toAdd;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     */
    public function update($entityId, array $data)
    {
        $toUpdate = $this->find($entityId);

        if (is_null($toUpdate)) {
            $this->logger->error('Update UserStory failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Update UserSotry failed: UserStory not found.');
        }

        if (array_key_exists('title', $data) && !is_null($data['title'])) {
            $toUpdate->setTitle($data['title']);
        }

        if (array_key_exists('offlineVotes', $data) && !is_null($data['offlineVotes'])) {
            $toUpdate->setOfflineVotes($data['offlineVotes']);
        }

        if (array_key_exists('description', $data) && !is_null($data['description'])) {
            $toUpdate->setDescription($data['description']);
        }

        if (array_key_exists('threadId', $data) && !is_null($data['threadId'])) {
            $toUpdate->setThread($this->getEntityManager()->getRepository(ForumThread::class)->find($data['threadId']));
        }

        if (array_key_exists('releaseId', $data) && !is_null($data['releaseId'])) {
            $toUpdate->setRelease($this->getEntityManager()->getRepository(DevelopmentRelease::class)->find($data['releaseId']));
        }

        $this->getEntityManager()->persist($toUpdate);
        $this->getEntityManager()->flush();

        return $toUpdate;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId)
    {
        $toDelete = $this->find($entityId);
        if (is_null($toDelete)) {
            $this->logger->error('Delete UserStory failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Delete UserStory failed: Entry not found.');
        }

        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param DevelopmentUserStory $entity
     *
     * @return DevelopmentUserStory
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('offlineVotes', $data)) {
            $entity->setOfflineVotes($data['offlineVotes']);
        }

        // online votes werden ausschliesslich automatisch gesetzt durch die zusammenzählung aus der tabelle votes

        if (array_key_exists('description', $data)) {
            $entity->setDescription($data['description']);
        }
        if (array_key_exists('title', $data)) {
            $entity->setTitle($data['title']);
        }
        if (array_key_exists('threadId', $data)) {
            $entity->setThread($data['threadId']);
        }

        return $entity;
    }

    /**
     * @param bool $updateClosedReleases
     */
    public function recalculateAndUpdateVotes($updateClosedReleases)
    {
        /** @var DevelopmentUserStoryVoteRepository $developmentUserStoryVoteRepository */
        $developmentUserStoryVoteRepository = $this->getEntityManager()->getRepository(DevelopmentUserStoryVote::class);
        $developmentUserStoryVoteRepository->recalculateAndUpdateAllVotes($updateClosedReleases);
    }
}
