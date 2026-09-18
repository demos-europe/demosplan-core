<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;


use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Doctrine\ORM\Query\QueryException;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Symfony\Component\HttpFoundation\RequestStack;

class StatementExportFilter
{
    private const TAG_FILTER_PARAM = 'tagsFilter';
    public function __construct(
        protected readonly JsonApiActionService $jsonApiActionService,
        protected readonly RequestStack $requestStack,
        protected readonly StatementExportTagFilter $statementExportTagFilter,
        protected readonly StatementResourceType $statementResourceType,
    )
    {
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function filter(string $procedureId): array
    {
        // Push the tag filter into the query so only statements carrying a matching tag are
        // loaded, instead of loading every statement of the procedure and discarding the rest
        // in PHP.
        $tagsFilter = $this->requestStack->getCurrentRequest()->query->all(self::TAG_FILTER_PARAM);
        $tagConditions = $this->statementExportTagFilter->buildStatementTagConditions(
            $tagsFilter,
            $this->statementResourceType,
            $procedureId
        );

        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $this->jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $this->statementResourceType,
                $tagConditions
            )->getList()
        );

        // Trim each loaded statement to only its matching segments. Runs on the already-narrowed set.
        return $this->statementExportTagFilter->filterStatementsByTags($statementEntities, $tagsFilter);
    }

    public function isFiltered(): bool
    {
        $tagsFilter = $this->requestStack->getCurrentRequest()->query->all(self::TAG_FILTER_PARAM);
        $otherFilters = $this->requestStack->getCurrentRequest()->query->all(UrlParameter::FILTER);

        return 0 < count($tagsFilter) || 0 < count($otherFilters);
    }
}
