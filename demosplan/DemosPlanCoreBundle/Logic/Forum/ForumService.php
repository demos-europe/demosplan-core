<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Forum;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStoryVote;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Repository\DevelopmentReleaseRepository;
use demosplan\DemosPlanCoreBundle\Repository\DevelopmentUserStoryRepository;
use demosplan\DemosPlanCoreBundle\Repository\DevelopmentUserStoryVoteRepository;
use demosplan\DemosPlanCoreBundle\Repository\ForumEntryFileRepository;
use demosplan\DemosPlanCoreBundle\Repository\ForumEntryRepository;
use demosplan\DemosPlanCoreBundle\Repository\ForumThreadRepository;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\SortMethodFactoryInterface;
use Exception;
use ReflectionException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ForumService extends CoreService
{
    /**
     * @var User
     */
    private $currentUser;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    /**
     * @var SortMethodFactoryInterface
     */
    private $sortMethodFactory;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var DateHelper
     */
    private $dateHelper;
    /**
     * @var DevelopmentReleaseRepository
     */
    private $developmentReleaseRepository;
    /**
     * @var DevelopmentUserStoryRepository
     */
    private $developmentUserStoryRepository;
    /**
     * @var DevelopmentUserStoryVoteRepository
     */
    private $developmentUserStoryVoteRepository;
    /**
     * @var ForumEntryFileRepository
     */
    private $forumEntryFileRepository;
    /**
     * @var ForumEntryRepository
     */
    private $forumEntryRepository;
    /**
     * @var ForumThreadRepository
     */
    private $forumThreadRepository;

    /**
     * @throws UserNotFoundException This can be thrown since we really should
     *                               always have at least the AnonymousUser
     */
    public function __construct(
        CurrentUserInterface $currentUser,
        DateHelper $dateHelper,
        DevelopmentReleaseRepository $developmentReleaseRepository,
        DevelopmentUserStoryRepository $developmentUserStoryRepository,
        DevelopmentUserStoryVoteRepository $developmentUserStoryVoteRepository,
        DqlConditionFactory $conditionFactory,
        EntityFetcher $entityFetcher,
        EntityHelper $entityHelper,
        ForumEntryFileRepository $forumEntryFileRepository,
        ForumEntryRepository $forumEntryRepository,
        ForumThreadRepository $forumThreadRepository,
        SortMethodFactory $sortMethodFactory
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->currentUser = $currentUser->getUser();
        $this->dateHelper = $dateHelper;
        $this->entityFetcher = $entityFetcher;
        $this->entityHelper = $entityHelper;
        $this->sortMethodFactory = $sortMethodFactory;
        $this->developmentReleaseRepository = $developmentReleaseRepository;
        $this->developmentUserStoryRepository = $developmentUserStoryRepository;
        $this->developmentUserStoryVoteRepository = $developmentUserStoryVoteRepository;
        $this->forumEntryFileRepository = $forumEntryFileRepository;
        $this->forumEntryRepository = $forumEntryRepository;
        $this->forumThreadRepository = $forumThreadRepository;
    }

    /**
     * Save new ThreadEntry by threadId.
     *
     * @param string $threadId
     * @param array  $data
     *
     * @return bool
     *
     * @throws Exception
     */
    public function addThreadEntry($threadId, $data)
    {
        try {
            $data['threadId'] = $threadId;
            $data['userId'] = $this->currentUser->getId();
            $data['roles'] = $this->currentUser->getRoles();
            if (array_key_exists('files', $data)) {
                $data['request']['files'] = $data['files'];
                unset($data['files']);
            }

            $response = $this->forumEntryRepository->add($data);

            $response = $this->convertToLegacy($response);
            $response['modifiedDate'] = $response['modifyDate'];
            $response['relatedThread'] = $this->convertToLegacy($response['thread']);
            $response['user'] = $this->convertToLegacy($response['user']);
            $response['responseCode'] = 201;
            unset($response['modifyDate']);
            unset($response['forum']);
            unset($response['topic']);
            unset($response['thread']);

            $finalResponse = [];
            $finalResponse['status'] = true;
            $finalResponse['body'] = $response;

            return $finalResponse;
        } catch (Exception $e) {
            $this->logger->error('Add Entry failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Get all threadEnries of a thread by threadId.
     *
     * @param string $threadId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getThreadEntryList($threadId)
    {
        try {
            $result = $this->forumEntryRepository->getForumEntryList($threadId);

            $resultArray = [];
            $resultArray['thread'] = $this->entityHelper->toArray($result['thread']);
            $resultArray['thread'] = $this->dateHelper->convertDatesToLegacy($resultArray['thread']);
            $resultArray['entryList'] = [];

            foreach ($result['entryList'] as $singleEntry) {
                $singleEntryArray = $this->entityHelper->toArray($singleEntry);
                $singleEntryArray['user'] = $this->convertToLegacy($singleEntryArray['user']);
                $singleEntryArray['modifiedDate'] = $singleEntryArray['modifyDate'];
                $resultArray['entryList'][] = $this->dateHelper->convertDatesToLegacy(
                    $singleEntryArray
                );
            }

            return $resultArray;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der Forumseinträge: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all info of a threadEntry by threadEntryId.
     *
     * @param string $threadEntryId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getThreadEntry($threadEntryId)
    {
        try {
            $entry = $this->forumEntryRepository->get($threadEntryId);

            $entry = $this->convertToLegacy($entry);
            $entry['thread'] = $this->convertToLegacy($entry['thread']);
            $entry['user'] = $this->convertToLegacy($entry['user']);
            $entry['modifiedDate'] = $entry['modifyDate'];
            unset($entry['modifyDate']);
            unset($entry['forum']);
            unset($entry['topic']);

            return $entry;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all info of a thread by threadId.
     *
     * @param string $threadId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getThread($threadId)
    {
        try {
            $thread = $this->forumThreadRepository->get($threadId);

            $thread = $this->convertToLegacy($thread);
            $thread['starterEntry'] = $this->convertToLegacy($thread['starterEntry']);
            $thread['starterEntry']['user'] = $this->convertToLegacy($thread['starterEntry']['user']);
            $thread['forum'] = $this->convertToLegacy($thread['forum']);
            $thread['topic'] = $this->convertToLegacy($thread['topic']);

            return $thread;
        } catch (Exception $e) {
            $this->logger->error('Get Thread failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Update the threadEntry by threadEntryId.
     *
     * @param string $threadEntryId
     * @param array  $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateThreadEntry($threadEntryId, $data)
    {
        if (!array_key_exists('anonymise', $data)) {
            $data['anonymise'] = null;
        }

        if (!array_key_exists('initialEntry', $data)) {
            $data['initialEntry'] = null;
        }

        if (!array_key_exists('files', $data)) {
            $data['files'] = null;
        }

        if (!array_key_exists('text', $data)) {
            $data['text'] = null;
        }

        try {
            $updatedEntry = $this->forumEntryRepository->update($threadEntryId, $data);

            $updatedEntry = $this->entityHelper->toArray($updatedEntry);
            $updatedEntry = $this->dateHelper->convertDatesToLegacy($updatedEntry);
            $updatedEntry['modifiedDate'] = $updatedEntry['modifyDate'];

            if (null !== $updatedEntry['user']) {
                $updatedEntry['user'] = $this->entityHelper->toArray($updatedEntry['user']);
                $updatedEntry['user'] = $this->dateHelper->convertDatesToLegacy($updatedEntry['user']);
            }

            unset($updatedEntry['modifyDate']);
            unset($updatedEntry['forum']);
            unset($updatedEntry['topic']);

            return ['status' => true, 'body' => $updatedEntry];
        } catch (Exception $e) {
            $this->logger->error('Update Entry failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Delete file of an entry in the forum by fileId.
     *
     * @param string $fileHash
     *
     * @return bool
     *
     * @throws Exception
     *
     * @internal param string $fileId
     */
    public function deleteForumFile($fileHash)
    {
        try {
            return $this->forumEntryFileRepository->delete($fileHash);
        } catch (Exception $e) {
            $this->logger->warning('Delete ForumEntryFile failed');
            throw $e;
        }
    }

    // ----Weiterentwicklungsbereich----

    /**
     * Save a new release.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function newRelease($data)
    {
        try {
            $addedRelease = $this->developmentReleaseRepository->add($data);

            $result['body'] = $this->convertToLegacy($addedRelease);
            $result['status'] = true;

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Add Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Update a release by releaseId.
     *
     * @param string $releaseId
     * @param array  $data
     *
     * @return bool
     *
     * @throws Exception
     */
    public function updateRelease($releaseId, $data)
    {
        try {
            $this->developmentReleaseRepository->update($releaseId, $data);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Update Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a release by releaseId.
     *
     * @param string $releaseId
     *
     * @throws Exception
     */
    public function deleteRelease($releaseId): void
    {
        try {
            $this->developmentReleaseRepository->delete($releaseId);
        } catch (Exception $e) {
            $this->logger->error('Delete Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Get all info of a release by releaseId.
     *
     * @param string $releaseId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getRelease($releaseId)
    {
        try {
            $entry = $this->developmentReleaseRepository->get($releaseId);

            $entry = $this->convertToLegacy($entry);
            unset($entry['modifiedDate']);
            unset($entry['createDate']);

            return $entry;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all releases.
     *
     * @throws HttpException
     * @throws Exception
     */
    public function getReleases()
    {
        try {
            $result = $this->developmentReleaseRepository->getDevelopmentReleaseList();

            $resultArray = [];
            foreach ($result as $singleEntry) {
                $singleEntryArray = $this->entityHelper->toArray($singleEntry);
                unset($singleEntryArray['modifiedDate']);
                unset($singleEntryArray['createDate']);
                $resultArray[] = $this->dateHelper->convertDatesToLegacy($singleEntryArray);
            }

            return $resultArray;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der Forumseinträge: ', [$e]);
            throw $e;
        }
    }

    /**
     * Save a new user story by releaseId.
     *
     * @param string $releaseId
     * @param array  $data
     *
     * @return mixed
     *
     * @throws HttpException
     * @throws Exception
     */
    public function newUserStory($releaseId, $data)
    {
        try {
            $data['releaseId'] = $releaseId;
            $addedRelease = $this->developmentUserStoryRepository->add($data);

            $result['body'] = $this->convertToLegacy($addedRelease);
            $result['status'] = true;

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Add Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Update an user story by storyId.
     *
     * @param string $storyId
     * @param array  $data
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function updateUserStory($storyId, $data)
    {
        try {
            $this->developmentUserStoryRepository->update($storyId, $data);

            $response['status'] = true;
            $response['body'] = null;
            $response['responseCode'] = 204;

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Update Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Delete an user story by storyId.
     *
     * @param string $storyId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteUserStory($storyId)
    {
        try {
            $this->developmentUserStoryRepository->delete($storyId);
        } catch (Exception $e) {
            $this->logger->error('Delete Release failed.', [$e]);
            throw $e;
        }

        return true;
    }

    /**
     * Get all user stories of a release by releaseId.
     *
     * @param string $releaseId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getUserStories($releaseId)
    {
        try {
            $result = $this->developmentUserStoryRepository
                ->getDevelopmentUserStoryList($releaseId);

            $resultArray = [];
            $resultArray['release'] = $this->convertToLegacy($result['release']);
            $resultArray['userStories'] = [];

            foreach ($result['userStories'] as $singleStory) {
                $singleStoryArray = $this->convertToLegacy($singleStory);
                $resultArray['userStories'][] = $this->dateHelper->convertDatesToLegacy(
                    $singleStoryArray
                );
            }

            return $resultArray;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der Forumseinträge: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all info for an user story by storyId.
     *
     * @param string $storyId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getUserStory($storyId)
    {
        try {
            $entry = $this->developmentUserStoryRepository->get($storyId);

            $entry = $this->convertToLegacy($entry);
            unset($entry['relase']);
            unset($entry['thread']);

            return $entry;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }

    public function recalculateAndUpdateVotes($updateClosedReleases)
    {
        $this->developmentUserStoryRepository
            ->recalculateAndUpdateVotes($updateClosedReleases);
    }

    /**
     * Save the votes of different user stories(votes)of one release by releaseId.
     *
     * @param string $releaseId
     * @param array  $votes
     *
     * @return mixed
     *
     * @throws HttpException
     * @throws Exception
     */
    public function saveVotes($releaseId, $votes)
    {
        try {
            $data['releaseId'] = $releaseId;
            $data['userId'] = $this->currentUser->getId();
            $data['orgaId'] = $this->currentUser->getOrganisationId();
            $data['votes'] = $votes;
            $addedVotes = $this->developmentUserStoryVoteRepository->add($data);

            $result['body'] = $addedVotes;
            $result['status'] = true;

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Add Release failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Get the votes of particular UserStory.
     *
     * @param string $storyId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getVotes($storyId)
    {
        $userStory = $this->getUserStory($storyId);

        $votesObjects = $this->entityFetcher->listEntitiesUnrestricted(
            DevelopmentUserStoryVote::class,
            [$this->conditionFactory->propertyHasValue($storyId, ['userStory'])],
            [$this->sortMethodFactory->propertyDescending(['userStory', 'ident'])]
        );

        $votes = array_map([__CLASS__, 'convertToLegacy'], $votesObjects);

        return [
            'userStory' => $userStory,
            'votes'     => $votes,
        ];
    }

    /**
     *  Convert Doctrine Result into legacyformat as pure array without Classes and right names.
     *
     * @param CoreEntity $object
     *
     * @return array|mixed
     *
     * @throws ReflectionException
     */
    protected function convertToLegacy($object)
    {
        if ($object instanceof User) {
            $user['ident'] = $object->getId();
            $user['utitle'] = $object->getTitle();
            $user['ugwId'] = $object->getGwId();
            $user['ulogin'] = $object->getLogin();
            $user['uemail'] = $object->getEmail();
            $user['ulanguage'] = $object->getLanguage();
            $user['ugender'] = $object->getGender();
            $user['ufirstname'] = $object->getFirstname();
            $user['ulastname'] = $object->getLastname();
            $user['upassword'] = $object->getPassword();
            $user['tUDeleted'] = $object->isDeleted();

            return $user;
        } else {
            $array = $this->entityHelper->toArray($object);
            $array = $this->dateHelper->convertDatesToLegacy($array);
        }
        if ($object instanceof DevelopmentUserStory) {
            $array['numberOfVotes'] = $object->getOfflineVotes() + $object->getOnlineVotes();
        }

        return $array;
    }

    /**
     * @param ForumEntry $entry
     *
     * @return array|ForumEntry|mixed
     *
     * @throws ReflectionException
     */
    protected function convertEntryToLegacy($entry)
    {
        if (!$entry instanceof ForumEntry) {
            return ['responseMessage' => 'Wrong Instance to convert to Legacy'];
        }

        $entry = $this->entityHelper->toArray($entry);

        return $this->dateHelper->convertDatesToLegacy($entry);
    }
}
