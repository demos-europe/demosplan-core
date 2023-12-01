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
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends CoreRepository<ForumThread>
 */
class ForumThreadRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id
     * Liefert einen Thread anhand der Thread-ID.
     *
     * @param string $entityId - ID, die einen besitmmten Thread identifizert, welcher zurückgegeben werden soll
     *
     * @return ForumThread
     *
     * @throws NonUniqueResultException
     */
    public function get($entityId)
    {
        if (is_null($entityId) || '' === $entityId) {
            $this->logger->error('Get thread failed: Given ID is invalid.');
            throw new MissingDataException('Get thread failed: Given ID is invalid.');
        }

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('thread')
            ->from(ForumThread::class, 'thread')
            ->where('thread.ident = :ident')
            ->setParameter('ident', $entityId)
            ->setMaxResults(1)
            ->getQuery();

        try {
            $resultThread = $query->getSingleResult();

            $starterEntry = $this->getInitialEntry($resultThread);

            $resultThread->setStarterEntry($starterEntry);

            $numberOfEntries = $this->calculateNumberOfEntries($resultThread);
            $resultThread->setNumberOfEntries($numberOfEntries);

            $recentActivity = $this->calculateRecentActivity($resultThread);
            $resultThread->setRecentActivity($recentActivity);

            return $resultThread;
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Berechnet die letzte Aktivität in einem Thread.
     * Dazu wird der Beitrag des Threads ermittelt, der das jüngste Änderungsdatum hat.
     *
     * @param forumThread $thread , dessen letzte Aktivität ermittelt werden soll
     *
     * @return string|null das jüngste Änderungsdatum des Threads als Unix Timestamp, wenn ein Beitrag zu dem Thread gefunden werden konnte, ansonsten null
     */
    private function calculateRecentActivity($thread)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entry.modifyDate')
            ->from(ForumEntry::class, 'entry')
            ->where('entry.thread = :threadId')
            ->setParameter('threadId', $thread->getIdent())
            ->orderBy('entry.modifyDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        try {
            $result = $query->getResult();
            $recentActivity = null;
            if (1 === (is_countable($result) ? count($result) : 0)) {
                if (array_key_exists('modifyDate', $result[0])) {
                    $recentActivity = $result[0]['modifyDate']->getTimestamp();
                }
            }

            return $recentActivity;
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Berechnet die Anzahl der Beiträge eines bestimmten Threads.
     *
     * @param forumThread $thread , dessen Anzahl der Beiträge ermittelt werden soll
     *
     * @return int anzahl der Beiträge des als Parameter übergebenen Threads
     */
    private function calculateNumberOfEntries($thread)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entry')
            ->from(ForumEntry::class, 'entry')
            ->where('entry.thread = :threadId')
            ->andWhere('entry.user IS NOT NULL')
            ->setParameter('threadId', $thread->getIdent())
            ->getQuery();

        $result = $query->getResult();

        return is_countable($result) ? count($result) : 0;
    }

    /**
     * Ermittelt den Initial-Beitrag zu einem bestimmten Thread.
     *
     * @return ForumEntry|null beitrag, mit dem ein besitmmter Thread eröffnet wurde
     *
     * @throws NonUniqueResultException
     */
    private function getInitialEntry($thread)
    {
        $threadId = $thread->getIdent();
        $initial = 1;

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entry')
            ->from(ForumEntry::class, 'entry')
            ->where('entry.thread = :threadId')
            ->andWhere('entry.initialEntry = :initial')
            ->setParameters(['threadId' => $threadId, 'initial' => $initial])
            ->setMaxResults(2)
            ->getQuery();

        try {
            return $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(array $data): never
    {
        throw new Exception('Not possible any more');
    }

    /**
     * Ermöglicht das Ändern eines bestimmten, bestehenden Threads in der DB.
     * Dabei werden Werte deren Inhalt null ist oder identisch mit dem bereits in der DB bestehenden Werten, nicht verändert.
     *
     * @param string $threadId identifiziert den zu Ändernden Thread
     * @param array  $data     array, welches die Änderungen enthält
     *
     * @throws Exception
     */
    public function update($threadId, array $data): never
    {
        throw new Exception('Not used any more');
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
     * @param ForumThread $entity
     *
     * @return ForumThread
     */
    public function generateObjectValues($entity, array $data)
    {
        $entityFields = collect(['closingReason', 'url']);
        $this->setEntityFieldsOnFieldCollection($entityFields, $entity, $data);

        $flagFields = collect(['closed', 'progression']);
        $this->setEntityFlagFieldsOnFlagFieldCollection($flagFields, $entity, $data);

        return $entity;
    }
}
