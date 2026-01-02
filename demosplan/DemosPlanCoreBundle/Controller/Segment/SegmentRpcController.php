<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use demosplan\DemosPlanCoreBundle\StoredQuery\StoredQueryInterface;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SegmentRpcController extends APIController
{
    #[DplanPermissions('area_statement_segmentation')]
    #[Route(path: '/rpc/1.0/statementListQuery/update/{queryHash}', name: 'dplan_rpc_segment_list_query_update', options: ['expose' => true], methods: ['PATCH'])]
    public function updateSegmentListQuery(CurrentProcedureService $currentProcedureService, string $queryHash, DrupalFilterParser $filterParser, HashedQueryService $filterSetService): Response
    {
        $procedureId = $currentProcedureService->getProcedureIdWithCertainty();
        /** @var array $filterArray */
        $filterArray = $this->getRequestJson('filter');
        $searchPhrase = $this->getRequestJson('searchPhrase');
        // Used to validate only, no need for the returned object
        $filterArray = $filterParser->validateFilter($filterArray);
        $filterParser->parseFilter($filterArray);
        $filterSet = $filterSetService->findHashedQueryWithHash($queryHash);
        $segmentListQuery = $filterSet instanceof HashedQuery ? $filterSet->getStoredQuery() : null;
        if (!$segmentListQuery instanceof StoredQueryInterface) {
            throw BadRequestException::unknownQueryHash($queryHash);
        }
        if ($procedureId !== $segmentListQuery->getProcedureId()) {
            throw new BadRequestException('Procedure ID given in HTTP header must match the procedure the query was originally created for');
        }
        /* @var SegmentListQuery $segmentListQuery */
        $segmentListQuery->setFilter($filterArray);
        $segmentListQuery->setSearchPhrase($searchPhrase);
        $filterSetService->findOrCreateFromQuery($segmentListQuery);

        return new Response($segmentListQuery->getHash());
    }
}
