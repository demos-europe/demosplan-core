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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<NotificationReceiver>
 */
class NotificationReceiverRepository extends CoreRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Fetch all info about certain Procedure.
     *
     * @param string $procedureId
     *
     * @return NotificationReceiver|null
     */
    public function getNotificationReceiversByProcedure($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('receiver')
            ->from(NotificationReceiver::class, 'receiver')
            ->where('receiver.procedure = :ident')
            ->setParameter('ident', $procedureId)
            ->getQuery();

        return $query->execute();
    }

    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return NotificationReceiver
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get county failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add NotificationReceiver to database.
     *
     * @return NotificationReceiver
     *
     * @throws Exception
     */
    public function add(array $receiverArray)
    {
        try {
            $em = $this->getEntityManager();
            $receiver = $this->generateObjectValues(new NotificationReceiver(), $receiverArray);
            $em->persist($receiver);
            $em->flush();

            return $receiver;
        } catch (Exception $e) {
            $this->logger->warning('Create NotificationReceiver failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add values from array to new Object.
     *
     * @param NotificationReceiver $entity
     *
     * @return NotificationReceiver
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('label', $data)) {
            $entity->setLabel($data['label']);
        }
        if (array_key_exists('procedureId', $data)) {
            $entity->setProcedureId($data['procedureId']);
        }
        if (array_key_exists('email', $data)) {
            $entity->setEmail($data['email']);
        }

        return $entity;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return NotificationReceiver
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function update($entityId, array $data)
    {
        $receiver = $this->get($entityId);
        $receiver = $this->generateObjectValues($receiver, $data);
        $em = $this->getEntityManager();
        $em->persist($receiver);
        $em->flush();

        return $receiver;
    }

    /**
     * Delete Entity.
     *
     * @param NotificationReceiver $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning('Delete notificationReceiver failed: Got null instead of entity.');
            throw new EntityNotFoundException();
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete notificationReceiver failed: ', [$e]);
        }

        return false;
    }

    /**
     * Add Entity to database.
     *
     * @param NotificationReceiver $receiver
     *
     * @return NotificationReceiver
     *
     * @throws Exception
     */
    public function addObject($receiver)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($receiver);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add notificationReceiver failed: ', [$e]);
        }

        return $receiver;
    }

    /**
     * Update Entity.
     *
     * @param NotificationReceiver $receiver
     *
     * @return NotificationReceiver|false
     */
    public function updateObject($receiver)
    {
        try {
            $this->getEntityManager()->persist($receiver);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update notificationReceiver failed: ', [$e]);

            return false;
        }

        return $receiver;
    }

    /**
     * @param string $blueprintId
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function copy($blueprintId, $procedureId)
    {
        $receivers = $this->getNotificationReceiversByProcedure($blueprintId);
        $em = $this->getEntityManager();
        $procedureRepository = $em->getRepository(Procedure::class);
        $procedure = $procedureRepository->find($procedureId);
        /** @var NotificationReceiver $receiver */
        foreach ($receivers as $receiver) {
            $newReceiver = new NotificationReceiver();
            $newReceiver->setProcedure($procedure);
            $newReceiver->setLabel($receiver->getLabel());
            $newReceiver->setEmail($receiver->getEmail());
            $em->persist($newReceiver);
        }
        $em->flush();
    }

    /**
     * @param string $procedureId
     *
     * @throws EntityNotFoundException
     */
    public function deleteReceiversForProcedure($procedureId)
    {
        $receivers = $this->getNotificationReceiversByProcedure($procedureId);
        foreach ($receivers as $receiver) {
            $this->delete($receiver);
        }
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
