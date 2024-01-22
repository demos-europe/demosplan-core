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
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends FluentRepository<Faq>
 */
class FaqRepository extends FluentRepository
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
