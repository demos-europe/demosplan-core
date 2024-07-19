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
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStoryVote;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;

/**
 * @template-extends FluentRepository<DevelopmentUserStoryVote>
 */
class DevelopmentUserStoryVoteRepository extends FluentRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     *
     * @throws EntityNotFoundException
     */
    public function get($entityId)
    {
        $entry = $this->find($entityId);
        if (is_null($entry)) {
            $this->logger->error('Get UserStoryVote failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Get Entry UserStoryVote: Entry not found.');
        }

        return $entry;
    }

    /**
     *  nur die stories die übergeben werden, werden geupdatet.
     * !kommt es zu einer exceoptin, so wird der vorgang unterbochen, worduch auch das update nicht durchgeführt wird.
     * die daten aus den beiden betroffenen tabellen werden somit asynchron. Abhilfe schafft das aufrufen der funktion, die alle votes updated.
     *
     * Add Entity to database
     *
     * @return CoreEntity
     *
     * @throws EntityNotFoundException
     */
    public function add(array $data)
    {
        $responseList = ['votes' => []];
        $responseVoteList = [];

        $release = $this->getEntityManager()->getRepository(DevelopmentRelease::class)
            ->find($data['releaseId']);

        if (is_null($release)) {
            $this->logger->error('Add Release failed:  Entry not found.', ['id' => $data['releaseId']]);
            throw new EntityNotFoundException('Update Release failed: Entry not found.');
        }

        $releasePhase = $release->getPhase();

        if (0 !== strcmp($releasePhase, 'closed')) {
            $this->deleteVotes($data['releaseId'], $data['userId']);

            foreach ($data['votes'] as $vote) {
                if ($this->isStoryOfRelease($data['releaseId'], $vote['userStoryId'])) {
                    if (!is_null($vote['userStoryId']) || 0 <= $vote['numberOfVotes']) {
                        $votes = $this->findVotesByUserIdAndStoryId($data['userId'], $vote['userStoryId']);
                        if (1 >= sizeof($votes)) {
                            if (empty($votes)) {
                                $this->addVote($data['userId'], $data['orgaId'], $vote);
                                array_push($responseVoteList, $vote);
                            } else {
                                // sollte nur auftreten, wenn in einer anfrageliste mehrmals eine bestimmte userstory vorkommt
                                array_push($responseVoteList, $this->addNumberOfVotes($vote, $votes[0]));
                            }
                            $this->recalculateAndUpdateVotesOfStory($vote['userStoryId']);
                        }
                    }
                }
            }
            $this->recalculateAndUpdateVotesOfRelease($data['releaseId']);
        }
        $responseList['votes'] = $responseVoteList;

        return $responseList;
    }

    /**
     * @param string $userId
     * @param string $orgaId
     * @param array  $data
     */
    private function addVote($userId, $orgaId, $data)
    {
        $em = $this->getEntityManager();
        $userStory = $em->getReference(DevelopmentUserStory::class, $data['userStoryId']);
        $user = $em->getReference(User::class, $userId);
        $orga = $em->getReference(Orga::class, $orgaId);

        $vote = new DevelopmentUserStoryVote();
        $vote->setUserStory($userStory);
        $vote->setNumberOfVotes($data['numberOfVotes']);
        $vote->setOrga($orga);
        $vote->setUser($user);

        $em->persist($vote);
        $em->flush();
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     */
    public function update($entityId, array $data): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
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
            $this->logger->error(
                'Delete UserStoryVote failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Delete UserStoryVote failed: Entry not found.');
        }

        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * alle Votes aller Releases neu berechnen:.
     *
     * @param bool $updateClosedReleases
     */
    public function recalculateAndUpdateAllVotes($updateClosedReleases)
    {
        $releases = $this->getEntityManager()->getRepository(DevelopmentRelease::class)
            ->findAll();

        if ($updateClosedReleases) {
            foreach ($releases as $release) {
                $this->recalculateAndUpdateVotesOfRelease($release->getIdent());
            }
        } else {
            foreach ($releases as $release) {
                if (0 !== strcmp($release->getPhase(), 'closed')) {
                    $this->recalculateAndUpdateVotesOfRelease($release->getIdent());
                }
            }
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return void
     */
    public function generateObjectValues($entity, array $data)
    {
    }

    /**
     * Votes ALLER stories EINES Releases werden neu berechnet.
     *
     * @param string $releaseId
     */
    private function recalculateAndUpdateVotesOfRelease($releaseId)
    {
        $stories = $this->getEntityManager()->getRepository(DevelopmentUserStory::class)
            ->findBy(['release' => $releaseId]);

        foreach ($stories as $story) {
            $this->recalculateAndUpdateVotesOfStory($story->getIdent());
        }
    }

    /**
     * Votes EINER story werden neubrerechnet.
     *
     * @param string $userStoryId
     *
     * @return int
     */
    private function recalculateAndUpdateVotesOfStory($userStoryId)
    {
        $sum = -1;
        $story = $this->getEntityManager()->getRepository(DevelopmentUserStory::class)
            ->find($userStoryId);

        if (!is_null($story)) {
            $sum = 0;
            $votes = $this->findBy(['userStory' => $userStoryId]);

            /** @var DevelopmentUserStoryVote $vote */
            foreach ($votes as $vote) {
                $sum = $sum + $vote->getNumberOfVotes();
            }

            $story->setOnlineVotes($sum);
            $this->getEntityManager()->persist($story);
            $this->getEntityManager()->flush();
        }

        return $sum;
    }

    private function deleteVotes($releaseId, $userId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->distinct(true)
            ->select('vote')
            ->from(DevelopmentUserStoryVote::class, 'vote')
            ->join('vote.userStory', 'userStory')
            ->where('userStory.release = :releaseId')
            ->setParameter('releaseId', $releaseId)
            ->andWhere('vote.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery();

        $resultList = $query->getResult();

        foreach ($resultList as $result) {
            $this->getEntityManager()->remove($result);
        }
        $this->getEntityManager()->flush();
    }

    private function isStoryOfRelease($releaseId, $userStoryId)
    {
        $result = true;
        $story = $this->getEntityManager()->getRepository(DevelopmentUserStory::class)
            ->find($userStoryId);

        if (!is_null($story)) {
            if (0 !== strcmp($story->getReleaseId(), (string) $releaseId)) {
                $result = false;
            }
        } else {
            $result = true;
        }

        return $result;
    }

    /**
     * Addiert die Anzahl der Votes zweier DevelopmentUserStoryVotes.
     *
     * @param DevelopmentUserStoryVote $request
     * @param DevelopmentUserStoryVote $vote
     *
     * @return DevelopmentUserStoryVote
     */
    private function addNumberOfVotes($request, $vote)
    {
        $vote->setNumberOfVotes($request->getNumberOfVotes() + $vote->getNumberOfVotes());
        $this->getEntityManager()->persist($vote);
        $this->getEntityManager()->flush();

        $result = new DevelopmentUserStoryVote();
        $result->setNumberOfVotes($vote->getNumberOfVotes());
        $result->setUserStory($vote->getUserStoryId());

        return $result;
    }

    /**
     * @param string $userId
     * @param string $userStoryId
     *
     * @return array
     */
    private function findVotesByUserIdAndStoryId($userId, $userStoryId)
    {
        return $this->findBy(['userStory' => $userStoryId, 'user' => $userId]);
    }
}
