<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use Exception;

/**
 * @template-extends CoreRepository<SurveyVote>
 */
class SurveyVoteRepository extends CoreRepository
{
    /**
     * Get all votes of a specific procedure.
     *
     * @throws Exception
     */
    public function findByProcedure(Procedure $procedure)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('LENGTH(sv.textReview) length, sv')
                ->from(SurveyVote::class, 'sv')
                ->leftJoin('sv.survey', 's')
                ->where('s.procedure = :procedure')->setParameter('procedure', $procedure->getId())
                ->orderBy('length', 'ASC')
                ->addOrderBy('sv.textReview', 'ASC')
                ->getQuery();

            $arrayOfArrays = $query->getResult();

            // not a nice workaround to sort by status in a sensible way, but it get the job done
            // after the sorting, the result needs to be converted into an array of objects again
            $arrayOfObjects = [];
            foreach ($arrayOfArrays as $array) {
                $arrayOfObjects[] = $array[0];
            }

            return $arrayOfObjects;
        } catch (Exception $e) {
            $this->logger->warning(sprintf(
                'Get list of SurveyVotes for procedure "%s" failed: ',
                $procedure->getId()
            ), [$e]);
            throw $e;
        }
    }
}
