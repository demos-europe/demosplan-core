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
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends CoreRepository<GdprConsent>
 */
class GdprConsentRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $gdprConsentId
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function get($gdprConsentId): GdprConsent
    {
        if (!is_string($gdprConsentId)) {
            throw new InvalidArgumentException('given GdprConsent ID must be of type string');
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('gdprConsent')
            ->from(GdprConsent::class, 'GdprConsent')
            ->where('gdprConsent.id = :gdprConsentId')
            ->setParameter('gdprConsentId', $gdprConsentId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Add Entityobject to database.
     *
     * @param GdprConsent $gdprConsent
     *
     * @throws ORMException
     * @throws InvalidArgumentException
     */
    public function addObject($gdprConsent): GdprConsent
    {
        if (!$gdprConsent instanceof GdprConsent) {
            throw new InvalidArgumentException('Parameter must be of type GdprConsent');
        }
        $entityManager = $this->getEntityManager();
        $entityManager->persist($gdprConsent);
        $entityManager->flush();

        return $gdprConsent;
    }

    /**
     * Update Object.
     *
     * @param GdprConsent $gdprConsent
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($gdprConsent): GdprConsent
    {
        if (!$gdprConsent instanceof GdprConsent) {
            throw new InvalidArgumentException('parameter must be of type GdprConsent');
        }

        $entityManager = $this->getEntityManager();
        $entityManager->persist($gdprConsent);
        $entityManager->flush();

        return $gdprConsent;
    }

    /**
     * Delete Entity.
     *
     * @param string $gdprConsentId
     */
    public function delete($gdprConsentId): never
    {
        // implement if needed but check for correct 'onDelete' settings regarding user and statetment
        throw new NotYetImplementedException('not needed till now');
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
