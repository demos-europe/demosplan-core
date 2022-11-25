<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanDocumentBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Document\BthgKompassAnswer;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;

class BthgKompassAnswerRepository extends CoreRepository implements ObjectInterface
{
    public function get($entityId): ?BthgKompassAnswer
    {
        return $this->find($entityId);
    }

    /**
     * @param BthgKompassAnswer $bthgKompassAnswer
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function addObject($bthgKompassAnswer): BthgKompassAnswer
    {
        $em = $this->getEntityManager();
        $em->persist($bthgKompassAnswer);
        $em->flush();

        return $bthgKompassAnswer;
    }

    /**
     * @param BthgKompassAnswer $bthgKompassAnswer
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateObject($bthgKompassAnswer): BthgKompassAnswer
    {
        $em = $this->getEntityManager();
        $em->persist($bthgKompassAnswer);
        $em->flush();

        return $bthgKompassAnswer;
    }

    /**
     * @param string $entityId
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete($entityId): void
    {
        $this->deleteObject($this->get($entityId));
    }

    /**
     * @param BthgKompassAnswer $bthgKompassAnswer
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deleteObject($bthgKompassAnswer): void
    {
        $em = $this->getEntityManager();
        $em->remove($bthgKompassAnswer);
        $em->flush();
    }
}
