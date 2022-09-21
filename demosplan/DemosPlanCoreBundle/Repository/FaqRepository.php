<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Faq;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class FaqRepository extends CoreRepository
{
    /**
     * Update or save Faq.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateFaq(Faq $faq): Faq
    {
        $em = $this->getEntityManager();
        $em->persist($faq);
        $em->flush();

        return $faq;
    }

    /**
     * Delete Faq.
     */
    public function deleteFaq(Faq $faq): void
    {
        $em = $this->getEntityManager();
        $em->remove($faq);
        $em->flush();
    }
}
