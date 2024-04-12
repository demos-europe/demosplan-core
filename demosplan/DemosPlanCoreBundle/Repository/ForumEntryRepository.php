<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntryFile;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @template-extends CoreRepository<ForumEntry>
 */
class ForumEntryRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return ForumEntry
     *
     * @throws EntityNotFoundException
     */
    public function get($entityId)
    {
        $entry = $this->find($entityId);
        if (null === $entry) {
            $this->logger->error('Get Entry failed: Entry with ID: '.$entityId.' not found.');
            throw new EntityNotFoundException('Get Entry failed: Entry with ID: '.$entityId.' not found.');
        }
        /** @var ForumEntryFileRepository $repo */
        $repo = $this->getEntityManager()->getRepository(ForumEntryFile::class);
        $files = $repo->getFileResponsesAsString($entityId);

        return $this->convertToEntryResponse($entry, $files);
    }

    /**
     * Get ForumEntry List.
     *
     * @param string $threadId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getForumEntryList($threadId)
    {
        try {
            /** @var ForumThreadRepository $repo */
            $repo = $this->getEntityManager()->getRepository(ForumThread::class);
            $relatedThread = $repo->get($threadId);

            if (null === $relatedThread) {
                $this->logger->error('Get list failed: Thread with ID: '.$threadId.' not found.');
                throw new EntityNotFoundException('Get list failed: Thread with ID: '.$threadId.' not found.');
            }

            $resultArray = [];

            $resultArray['entryList'] = $this->getEntryResponses($threadId);
            $resultArray['thread'] = $relatedThread;

            return $resultArray;
        } catch (Exception $e) {
            $this->logger->error('Get list failed: Thread with ID: '.$threadId.' ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $threadId
     *
     * @return array
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function getEntryResponses($threadId)
    {
        $relatedThread = $this->getEntityManager()->find(ForumThread::class, $threadId);

        if (null === $relatedThread) {
            $this->logger->error('Get list failed: Thread with ID: '.$threadId.' not found.');
            throw new EntityNotFoundException('Get list failed: Thread with ID: '.$threadId.' not found.');
        }

        $entries = $this->findBy(['thread' => $threadId]);
        $resultList = [];
        /** @var ForumEntryFileRepository $forumEntryFileRepos */
        $forumEntryFileRepos = $this->getEntityManager()->getRepository(ForumEntryFile::class);
        foreach ($entries as $entry) {
            try {
                $files = $forumEntryFileRepos->getFileResponsesAsString($entry->getIdent());
            } catch (Exception) {
                $files = [];
            }
            $entryResponse = $this->convertToEntryResponse($entry, $files);
            $resultList[] = $entryResponse;
        }

        return $resultList;
    }

    /**
     * Add Entity to database.
     *
     * @return ForumEntry
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(array $data)
    {
        if (!(array_key_exists('threadId', $data)
            && array_key_exists('userId', $data)
            && array_key_exists('roles', $data)
            && array_key_exists('text', $data))
        ) {
            $this->logger->error(
                'Add Entry failed: Missing one of the following keys in the given data: threadId, userId, roles'
            );
            throw new MissingDataException('Add Entry failed: Missing one of the following keys in the given data: threadId, userId, roles');
        }

        $toAdd = $this->generateObjectValues(new ForumEntry(), $data);

        if (!$this->isThreadClosed($data['threadId'])) {
            $relatedUser = $this->getEntityManager()->getRepository(User::class)->find($data['userId']);
            if (null !== $relatedUser) {
                $toAdd->setUser($relatedUser);
                $toAdd->setUserRoles(implode(',', $data['roles']));

                $relatedThread = $this->getEntityManager()->getRepository(ForumThread::class)->find(
                    $data['threadId']
                );
                $toAdd->setThread($relatedThread);

                $this->getEntityManager()->persist($toAdd);
                $this->getEntityManager()->flush();

                if (isset($data['request']['files'])) {
                    if (null !== $data['request']['files']) {
                        $this->createFileEntries($data['request']['files'], $toAdd);
                    }
                } else {
                    $data['request']['files'] = null;
                }

                return $this->convertToEntryResponse($toAdd, $data['request']['files']);
            } else {
                $this->logger->error('Add Entry failed: User with ID: '.$data['userId'].' not found.');
                throw new EntityNotFoundException('Add Entry failed: User with ID: '.$data['userId'].' not found.');
            }
        } else {
            $this->logger->error('Add Entry failed: User with ID: '.$data['userId'].' not found.');
            throw new InvalidArgumentException('Thread with ID: '.$data['threadId'].' is already closed.');
        }
    }

    private function isThreadClosed($threadId)
    {
        return $this->getEntityManager()->getRepository(ForumThread::class)->find($threadId)->getClosed(
        );
    }

    /**
     * @param ForumEntry $entry
     * @param array      $files
     *
     * @return ForumEntry
     */
    private function convertToEntryResponse($entry, $files)
    {
        $entry->setFiles($files);

        return $entry;
    }

    /**
     * Generiert DB-Einträge aus den gegebenen Parametern.
     *
     * @param array      $files - Liste von Strings, die unter anderem den Hashwert des File-Eintrages beinhalten
     * @param ForumEntry $entry beitrag, zu dem die File-Einträge zugeordnet werden
     *
     * @return ForumEntry Beitrag der erstellt wurde, inkl. der generierten File-Einträge.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createFileEntries($files, $entry)
    {
        $numberOfFiles = 0;

        foreach ($files as $singleFileString) {
            $fileToAdd = new ForumEntryFile();
            $fileToAdd->setString($singleFileString);
            $exploded = explode(':', (string) $singleFileString);
            $fileToAdd->setHash($exploded[1]);
            $fileToAdd->setEntry($entry);

            $fileToAdd->setOrder($numberOfFiles);
            ++$numberOfFiles;
            $this->getEntityManager()->persist($fileToAdd);
        }

        $this->getEntityManager()->flush();

        return $this->convertToEntryResponse($entry, $files);
    }

    /**
     * Ermöglicht das Ändern eines bestimmten, bestehenden Beitrags in der DB.
     * Dabei werden Werte deren Inhalt null ist oder identisch mit dem bereits in der DB bestehenden Werten, nicht verändert.
     *
     * @param string $entityId
     *
     * @return ForumEntry
     *
     * @throws EntityNotFoundException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function update($entityId, array $data)
    {
        $toUpdate = $this->get($entityId);
        if (null === $toUpdate) {
            $this->logger->error('Update Entry failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Update Entry failed: Entry not found.');
        }

        if (null !== $data['anonymise'] && $data['anonymise'] && null !== $toUpdate->getUser(
        )) {
            $this->deleteRelatedFiles($entityId);
            $toUpdate->setUser(null);
        }

        if (null !== $data['initialEntry'] && $data['initialEntry'] != $toUpdate->isInitialEntry()) {
            $toUpdate->setInitialEntry($data['initialEntry']);
        }

        if (null !== $data['text']) {
            $toUpdate->setText($data['text']);
        }

        if (null !== $data['files'] && null !== $toUpdate->getUser()) {
            if (null === $data['anonymise'] || !$data['anonymise']) {
                /** @var ForumEntryFileRepository $repo */
                $repo = $this->getEntityManager()->getRepository(ForumEntryFile::class);
                $repo->add(['entryId' => $entityId, 'files' => $data['files']]);
            }
        }

        $this->getEntityManager()->persist($toUpdate);
        $this->getEntityManager()->flush();

        return $this->convertToEntryResponse($toUpdate, $data['files']);
    }

    private function deleteRelatedFiles($entryId)
    {
        $filesRepos = $this->getEntityManager()->getRepository(ForumEntryFile::class);
        $toDeletingFiles = $filesRepos->findBy(['entryId' => $entryId]);

        foreach ($toDeletingFiles as $toDelete) {
            $this->getEntityManager()->remove($toDelete);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @throws Exception
     */
    public function delete($entityId): never
    {
        throw new Exception('not used any more');
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param ForumEntry $entity
     *
     * @return ForumEntry
     */
    public function generateObjectValues($entity, array $data)
    {
        $commonFields = collect(['text']);
        $this->setEntityFieldsOnFieldCollection($commonFields, $entity, $data);

        $flagFields = collect(['initialEntry']);
        $this->setEntityFlagFieldsOnFlagFieldCollection($flagFields, $entity, $data);

        return $entity;
    }
}
