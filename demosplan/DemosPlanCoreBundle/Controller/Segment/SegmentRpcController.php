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
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Webmozart\Assert\Assert;

class SegmentRpcController extends APIController
{
    private const FILTER = 'filter';
    private const SEARCH_PHRASE = 'searchPhrase';
    private const VIEW_SETTINGS = 'viewSettings';

    /**
     * Every rejection of the incoming request surfaces as one {@see BadRequestException}, logged once
     * before it is rethrown.
     *
     * Catching `InvalidArgumentException` covers both sources of client error in one place: Webmozart
     * assertions, and `DrupalFilterException` from the filter parser, whose `FilterException` base
     * extends `InvalidArgumentException`. Anything else - a failing database write, for example - is
     * deliberately left to propagate, so a server fault is not reported to the client as its own
     * mistake.
     *
     * @throws BadRequestException
     */
    #[DplanPermissions('area_statement_segmentation')]
    #[Route(path: '/rpc/1.0/statementListQuery/update/{queryHash}', name: 'dplan_rpc_segment_list_query_update', options: ['expose' => true], methods: ['PATCH'])]
    public function updateSegmentListQuery(CurrentProcedureService $currentProcedureService, string $queryHash, DrupalFilterParser $filterParser, HashedQueryService $filterSetService): Response
    {
        try {
            return $this->applyQueryUpdate($currentProcedureService, $queryHash, $filterParser, $filterSetService);
        } catch (InvalidArgumentException $e) {
            $this->logger->error(
                'Rejected a segment list query update',
                ['queryHash' => $queryHash, 'reason' => $e->getMessage()]
            );
            // todo changeMe to $this->handleApiError($e) - its an api request and should not return plain text
            throw new BadRequestException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws InvalidArgumentException on any unusable input, translated by the caller
     */
    private function applyQueryUpdate(CurrentProcedureService $currentProcedureService, string $queryHash, DrupalFilterParser $filterParser, HashedQueryService $filterSetService): Response
    {
        $procedureId = $currentProcedureService->getProcedureIdWithCertainty();

        // Null when the body could not be decoded, see APIController::getRequestJson().
        $requestJson = $this->getRequestJson();
        Assert::isArray($requestJson, 'The request body must contain valid JSON.');

        // A list when no filter is active, a map of conditions otherwise, so both forms are accepted.
        $filterArray = $requestJson[self::FILTER] ?? null;
        Assert::isArray($filterArray, 'The "filter" property is required; send an empty array when no filter is active.');

        // Used to validate only, no need for the returned object
        $filterArray = $filterParser->validateFilter($filterArray);
        $filterParser->parseFilter($filterArray);

        $filterSet = $filterSetService->findHashedQueryWithHash($queryHash);
        $segmentListQuery = $filterSet instanceof HashedQuery ? $filterSet->getStoredQuery() : null;

        /*
         * Narrowed to SegmentListQuery rather than to StoredQueryInterface: hashes of other query
         * types live in the same table, and an assessment table query carries a different shape - it
         * has setFilters() instead of setFilter() - so the setters below would fail on it. For this
         * route such a hash is simply not a known segment list hash.
         */
        Assert::isInstanceOf(
            $segmentListQuery,
            SegmentListQuery::class,
            sprintf('No segment list query was found for the given filter hash: %s', $queryHash)
        );

        Assert::same(
            $procedureId,
            $segmentListQuery->getProcedureId(),
            'Procedure ID given in HTTP header must match the procedure the query was originally created for'
        );

        $segmentListQuery->setFilter($filterArray);
        $segmentListQuery->setSearchPhrase($requestJson[self::SEARCH_PHRASE] ?? null);
        $this->updateViewSettings($segmentListQuery, $requestJson);

        $filterSetService->findOrCreateFromQuery($segmentListQuery);

        // todo changeMe to $this->createResponse(['data' => ['queryHash' => $hash]], Response::HTTP_OK) - its an
        //  api request and should not return plain text. Note createResponse() merges its argument into the
        //  envelope rather than nesting it, so passing [$hash] would emit {"0":"..."}. Requires the two frontend
        //  consumers to read data.data.queryHash, see the plan.
        return new Response($segmentListQuery->getHash());
    }

    /**
     * An absent key leaves the stored view settings untouched, because this route also handles plain
     * filter changes and wiping the user's columns on every one of those would be surprising. Sending
     * it empty clears them, which restores the hash the query had before any were set.
     *
     * The contents are not validated here: {@see SegmentListQuery::setViewSettings()} normalizes them,
     * which keeps a single definition of what a valid view setting is.
     */
    private function updateViewSettings(SegmentListQuery $segmentListQuery, array $requestJson): void
    {
        if (!array_key_exists(self::VIEW_SETTINGS, $requestJson)) {
            return;
        }

        $viewSettings = $requestJson[self::VIEW_SETTINGS];
        Assert::isArray($viewSettings, 'The "viewSettings" property must hold view settings; send an empty array to clear them.');

        $segmentListQuery->setViewSettings($viewSettings);
    }
}
