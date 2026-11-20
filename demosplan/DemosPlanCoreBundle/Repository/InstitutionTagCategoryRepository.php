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

use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\QueryBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Exception;

/**
 * @template-extends CoreRepository<InstitutionTagCategory>
 */
class InstitutionTagCategoryRepository extends CoreRepository implements ObjectInterface
{
    /**
     * @return InstitutionTagCategory|null
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param InstitutionTagCategory $institutionTagCategory
     *
     * @return InstitutionTagCategory
     */
    public function addObject($institutionTagCategory)
    {
        $manager = $this->getEntityManager();
        $manager->persist($institutionTagCategory);
        $manager->flush();

        return $institutionTagCategory;
    }

    /**
     * @param InstitutionTagCategory $institutionTagCategory
     *
     * @return InstitutionTagCategory
     */
    public function updateObject($institutionTagCategory)
    {
        $em = $this->getEntityManager();
        $em->persist($institutionTagCategory);
        $em->flush();

        return $institutionTagCategory;
    }

    /**
     * @param string $institutionTagCategoryId
     *
     * @return bool
     *
     * @throws ResourceNotFoundException
     */
    public function delete($institutionTagCategoryId)
    {
        $tag = $this->get($institutionTagCategoryId);
        if (!$tag instanceof InstitutionTagCategory) {
            throw new ResourceNotFoundException("InstitutionTag with ID {$institutionTagCategoryId} was not found.");
        }

        return $this->deleteObject($tag);
    }

    /**
     * @param InstitutionTagCategory $institutionTagCategory
     *
     * @return bool
     */
    public function deleteObject($institutionTagCategory)
    {
        try {
            $this->getEntityManager()->remove($institutionTagCategory);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete statementVote failed: ', [$e]);
        }

        return false;
    }

    /**
     * Builds a QueryBuilder matching the given EDT conditions without executing it, so
     * callers can embed it as a subquery (e.g. `id IN (...)`) instead of running it as
     * its own round-trip.
     *
     * @param list<ClauseFunctionInterface<bool>> $conditions
     */
    public function generateAccessConditionQueryBuilder(array $conditions): QueryBuilder
    {
        return $this->objectProvider->generateQueryBuilder($conditions, []);
    }
}
