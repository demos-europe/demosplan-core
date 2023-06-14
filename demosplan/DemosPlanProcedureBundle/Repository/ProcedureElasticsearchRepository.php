<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Repository;

use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryProcedure;
use demosplan\DemosPlanCoreBundle\Traits\DI\ElasticsearchQueryTrait;
use Elastica\Index;
use Elastica\Query\BoolQuery;
use Elastica\Query\Terms;
use Psr\Log\LoggerInterface;

class ProcedureElasticsearchRepository
{
    use ElasticsearchQueryTrait;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var Permissions
     */
    protected $permissions;

    public function __construct(
        Index $procedureSearchType,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        PermissionsInterface $permissions
    ) {
        $this->index = $procedureSearchType;
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->permissions = $permissions;
    }

    /**
     * Search for Procedures.
     *
     * @param QueryProcedure $esQuery
     */
    public function searchProcedures($esQuery): array
    {
        $elasticsearchResult = $this->getElasticsearchResult($esQuery);

        return $this->toLegacyResultES($elasticsearchResult);
    }

    /**
     * Remove hidden phases according to scope from resultset.
     */
    protected function modifyBoolMustNotFilter(BoolQuery $boolQuery, QueryProcedure $esQuery): BoolQuery
    {
        // we need nested BoolQueries with Should here as planners may also be institution users
        // If they are usual institutions they should not see procedures in their hidden states
        // their own procedures should be displayed even in hidden phases
        if ($esQuery->hasScope(QueryProcedure::SCOPE_INTERNAL)) {
            $boolInternalQuery = new BoolQuery();
            $boolInternalMustNotTerms = [''];
            foreach ($this->globalConfig->getInternalPhases('hidden') as $internalHidden) {
                $boolInternalMustNotTerms[] = $internalHidden['key'];
            }
            $boolInternalQuery->addMustNot(new Terms('phase', $boolInternalMustNotTerms));

            if (!$this->permissions->hasPermission('feature_procedure_all_orgas_invited')) {
                $boolInternalQuery->addMust(new Terms('organisationIds', [$esQuery->getOrgaId()]));
            }
            $boolQuery->addShould($boolInternalQuery);
            $boolQuery = $this->setMinimumShouldMatch($boolQuery, 1);
        }

        if ($esQuery->hasScope(QueryProcedure::SCOPE_PLANNER)) {
            $boolPlannerQuery = new BoolQuery();
            $boolPlannerQuery->addMust(new Terms('orgaId', [$esQuery->getOrgaId()]));
            if ($this->globalConfig->hasProcedureUserRestrictedAccess()) {
                $boolPlannerQuery->addMust(new Terms('authorizedUserIds', [$esQuery->getUserId()]));
            }
            $boolQuery->addShould($boolPlannerQuery);
            $boolQuery = $this->setMinimumShouldMatch($boolQuery, 1);
        }

        if ($esQuery->hasScope(QueryProcedure::SCOPE_EXTERNAL)) {
            $boolQuery->addMustNot(new Terms('publicParticipationPhase', ['']));
            foreach ($this->globalConfig->getExternalPhases('hidden') as $externalHidden) {
                $boolQuery->addMustNot(new Terms('publicParticipationPhase', [$externalHidden['key']]));
            }
        }

        return $boolQuery;
    }

    /**
     * @param BoolQuery $boolQuery
     * @param int       $minimumShouldMatch
     */
    protected function setMinimumShouldMatch($boolQuery, $minimumShouldMatch): BoolQuery
    {
        return $boolQuery->setMinimumShouldMatch($minimumShouldMatch);
    }

    public function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }
}
