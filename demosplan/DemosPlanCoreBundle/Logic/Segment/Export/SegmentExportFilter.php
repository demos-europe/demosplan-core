<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldFilter;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RequestStack;

class SegmentExportFilter
{
    public function __construct(
        protected readonly DqlConditionFactory $conditionFactory,
        protected readonly SegmentCustomFieldFilter $customFieldFilter,
        protected readonly JsonApiActionService $jsonApiActionService,
        protected readonly RequestStack $requestStack,
        protected readonly StatementExportTagFilter $statementExportTagFilter,
        protected readonly StatementSegmentResourceType $statementSegmentResourceType,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     */
    public function filter(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // The segment resource type does not know the custom field conditions, so they are
        // translated into the ids of the matching segments.
        [$filter, $customFieldSelections] = $this->customFieldFilter->extractFromDrupalFilter(
            $request->query->all(UrlParameter::FILTER)
        );
        $queryParameters = $request->query->all();
        if ([] === $filter) {
            unset($queryParameters[UrlParameter::FILTER]);
        } else {
            $queryParameters[UrlParameter::FILTER] = $filter;
        }
        $query = new InputBag($queryParameters);

        return array_values(
            $this->jsonApiActionService->getObjectsByQueryParams(
                $query,
                $this->statementSegmentResourceType,
                $this->getCustomFieldConditions((string) $request->attributes->get('procedureId'), $customFieldSelections),
            )->getList()
        );
    }

    /**
     * @param array<string, list<string>> $customFieldSelections
     */
    private function getCustomFieldConditions(string $procedureId, array $customFieldSelections): array
    {
        if ([] === $customFieldSelections) {
            return [];
        }

        $matchingSegmentIds = $this->customFieldFilter->findMatchingSegmentIds($procedureId, $customFieldSelections);

        return [[] === $matchingSegmentIds
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($matchingSegmentIds, $this->statementSegmentResourceType->id),
        ];
    }
}
