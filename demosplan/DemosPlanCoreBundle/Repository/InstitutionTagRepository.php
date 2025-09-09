<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Exception;

/**
 * @template-extends CoreRepository<InstitutionTag>
 */
class InstitutionTagRepository extends CoreRepository implements ObjectInterface
{
    /**
     * @return InstitutionTag|null
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param InstitutionTag $tag
     *
     * @return InstitutionTag
     */
    public function addObject($tag)
    {
        $manager = $this->getEntityManager();
        $manager->persist($tag);
        $manager->flush();

        return $tag;
    }

    /**
     * @param InstitutionTag $tag
     *
     * @return InstitutionTag
     */
    public function updateObject($tag)
    {
        $em = $this->getEntityManager();
        $em->persist($tag);
        $em->flush();

        return $tag;
    }

    /**
     * @param string $tagId
     *
     * @return bool
     *
     * @throws ResourceNotFoundException
     */
    public function delete($tagId)
    {
        $tag = $this->get($tagId);
        if (!$tag instanceof InstitutionTag) {
            throw new ResourceNotFoundException("InstitutionTag with ID {$tag} was not found.");
        }

        return $this->deleteObject($tag);
    }

    /**
     * @param InstitutionTag $tag
     *
     * @return bool
     */
    public function deleteObject($tag)
    {
        try {
            $this->getEntityManager()->remove($tag);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete statementVote failed: ', [$e]);
        }

        return false;
    }
}
