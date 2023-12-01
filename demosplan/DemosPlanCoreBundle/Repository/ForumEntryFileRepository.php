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
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntryFile;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;

/**
 * @template-extends CoreRepository<ForumEntryFile>
 */
class ForumEntryFileRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Ermittelt einen File-Eintrag anhand des Hash-Wertes und liefert diesen zurück.
     *
     * @param string $hash der zur Identifizierung des File-Eintrages dient
     *
     * @return ForumEntryFile file-Eintrag als FileResponse Objekt
     *
     * @throws NonUniqueResultException
     */
    public function get($hash)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('file')
            ->from(ForumEntryFile::class, 'file')
            ->where('file.hash = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->getQuery();

        try {
            return $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get all files of an specify entry.
     *
     * @param string $entryId - ID to identify of the entry
     *
     * @return array - a list of all files related to the entry
     *
     * @throws NonUniqueResultException
     */
    private function getFileResponses($entryId)
    {
        $this->checkEntry($entryId);

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('file')
            ->from(ForumEntryFile::class, 'file')
            ->where('file.entryId = :entryId')
            ->andWhere('file.deleted = false')
            ->andWhere('file.blocked = false')
            ->setParameter('entryId', $entryId)
            ->orderBy('file.createDate', 'DESC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param string $entryIdent
     *
     * @return array
     */
    public function getFileResponsesAsString($entryIdent)
    {
        try {
            $files = $this->getFileResponses($entryIdent);
        } catch (NonUniqueResultException) {
            $files = [];
        }

        $fileStrings = [];

        foreach ($files as $file) {
            array_push($fileStrings, $file->getString());
        }

        return $fileStrings;
    }

    /**
     * Check an entry-ID on validity and throw an exception if the given ID is not valid.
     *
     * @param string $entryId - ID
     *
     * @throws NonUniqueResultException
     */
    private function checkEntry($entryId)
    {
        if (!$this->isValidEntry($entryId)) {
            $this->logger->error('Add entryFile failed: Given entryId identifies an invalid entry.');
            throw new NonUniqueResultException('Add entryFile failed: Given entryId identifies an invalid entry.');
        }
    }

    /**
     * Add one or multiple ForumEntryFile-Entries to the database.
     * The File-Entry has to be related to a specific ForumEntry.
     *
     * @param array $data contains the Identifier of the ForumEntry and the files to add
     *
     * @return array of the created and added entryfiles
     *
     * @throws MissingDataException
     * @throws NonUniqueResultException
     */
    public function add(array $data)
    {
        if (!array_key_exists('entryId', $data)) {
            $this->logger->error('Add entryFile failed: No entryId in given array');
            throw new MissingDataException('Add entryFile failed: No entryId in given array');
        }

        $this->checkEntry($data['entryId']);

        if (!array_key_exists('files', $data)) {
            $this->logger->error('Add entryFile failed: No files in given array');
            throw new MissingDataException('Add entryFile failed: No files in given array');
        }

        // data['files'] contains an array of strings?!
        $files = $data['files'];
        $addedFiles = [];

        foreach ($files as $singleFileString) {
            $forumEntryFile = new ForumEntryFile();

            $forumEntryFile->setBlocked(false);
            $forumEntryFile->setDeleted(false);
            $forumEntryFile->setString($singleFileString);
            $exploded = explode(':', (string) $singleFileString);
            $forumEntryFile->setHash($exploded[1]);
            $forumEntryFile->setEntry($this->getEntityManager()->getRepository(ForumEntry::class)->find($data['entryId']));
            $forumEntryFile->setOrder($this->calculateOrder());

            $this->getEntityManager()->persist($forumEntryFile);
            array_push($addedFiles, $forumEntryFile);
        }

        $this->getEntityManager()->flush();

        return $addedFiles;
    }

    private function calculateOrder()
    {
        $orderNumber = 0;
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('forumEntryFile.order')
            ->from(ForumEntryFile::class, 'forumEntryFile')
            ->getQuery();
        $entryFileList = $query->getResult();
        if (!is_null($entryFileList) && 0 < sizeof($entryFileList)) {
            if (!is_null($entryFileList[0])) {
                $orderNumber = $entryFileList[sizeof($entryFileList) - 1]['order'];
            }
        }

        return $orderNumber + 1;
    }

    /**
     * Überprüft ob eine Beitrags-ID gültig ist.
     * Als gültig gilt die ID, wenn ein Beitrag zu dieser ID existiert und dieser noch nicht "gelöscht"/"anonymisiert" ist.
     *
     * @param string $entryId ID die überprüft werden soll
     *
     * @return bool true wenn die ID als gültig eingestuft wird, ansonsten false
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function isValidEntry($entryId)
    {
        $forumEntry = $this->getEntityManager()->find(ForumEntry::class, $entryId);

        if (!is_null($forumEntry)) {
            if (!is_null($forumEntry->getUserId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $hash
     *
     * @return ForumEntryFile
     *
     * @throws EntityNotFoundException
     */
    public function update($hash, array $data)
    {
        $toUpdate = $this->get($hash);
        if (is_null($toUpdate)) {
            $this->logger->error(
                'Update entryFile failed: No file found with given hash: ', [$hash]);
            throw new EntityNotFoundException('Update entryFile failed: No file found with given hash');
        }

        if (array_key_exists('blocked', $data) && !is_null($data['blocked'])) {
            $toUpdate->setBlocked($data['blocked']);
        }

        if (array_key_exists('deleted', $data) && !is_null($data['deleted'])) {
            $toUpdate->setDeleted($data['deleted']);
        }

        if (array_key_exists('order', $data)) {
            $toUpdate->setOrder($data['order']);
        }

        if (array_key_exists('order', $data)) {
            $toUpdate->setOrder($data['order']);
        }

        $this->getEntityManager()->persist($toUpdate);
        $this->getEntityManager()->flush();

        return $toUpdate;
    }

    /**
     * Delete a specify entity.
     *
     * @param string $hash - Hash value to identify the entity to delete
     *
     * @return bool - true if the entity was found by the given hash and was delted, otherwise false
     */
    public function delete($hash)
    {
        $toDelete = $this->get($hash);
        if (is_null($toDelete)) {
            $this->logger->error(
                'Delete file failed: No entry found with given hash: ', [$hash]);
            throw new EntityNotFoundException('Delete file failed: No entry found with given');
        }
        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();

        return true;
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
}
