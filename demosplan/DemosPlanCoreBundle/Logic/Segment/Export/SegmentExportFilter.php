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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Provider as StatementSegmentProvider;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Resource as StatementSegmentResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Finds the segments to export with the same query the segment list uses, so a filter only has to
 * exist once: the filters of `GET /api/3.0/StatementSegment` apply to the export too.
 *
 * The export URL carries the same query parameters as the list request (`tags.id[]`, `customField[..]`,
 * `search[value]`, `order[..]`, ...). They are sent through the API Platform provider chain, just
 * without the HTTP layer around it.
 */
class SegmentExportFilter
{
    private const API_PATH = '/api/3.0/StatementSegment';

    private const PROCEDURE_PARAMETER = 'parentStatementOfSegment.procedure.id';

    /**
     * Parameters of the export URL that must not reach the query: `columns` is only for the
     * exporter, the paging parameters and the procedure are set by the export itself.
     */
    private const IGNORED_PARAMETERS = ['columns', 'pagination', 'page', 'itemsPerPage', self::PROCEDURE_PARAMETER];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'api_platform.state_provider.main')]
        private readonly ProviderInterface $stateProvider,
    ) {
    }

    /**
     * @return list<Segment> all segments of the procedure that match the query of the current request
     */
    public function filter(string $procedureId): array
    {
        $operation = $this->getCollectionOperation();
        $request = Request::create(self::API_PATH.'?'.$this->getQueryString($procedureId), 'GET');
        $request->attributes->set('_format', 'jsonapi');
        $request->attributes->set('_api_resource_class', StatementSegmentResource::class);
        $request->attributes->set('_api_operation_name', $operation->getName());

        $segments = $this->stateProvider->provide($operation, [], [
            'request'                                  => $request,
            'uri_variables'                            => [],
            'resource_class'                           => StatementSegmentResource::class,
            StatementSegmentProvider::RETURN_ENTITIES  => true,
        ]);

        return array_values($segments);
    }

    /**
     * The query of the export URL, restricted to the procedure of the route and without paging. The
     * procedure does not come from the query, so the client cannot export another one.
     *
     * It is the raw query string, not `$request->query`: PHP replaces the dots in the names of
     * parameters like `tags.id` or `parentStatementOfSegment.externId` when it parses a request,
     * which would silently drop these filters. The API provider parses the raw string itself.
     */
    private function getQueryString(string $procedureId): string
    {
        $rawQueryString = (string) $this->requestStack->getCurrentRequest()->server->get('QUERY_STRING', '');
        $fixedParameters = http_build_query([self::PROCEDURE_PARAMETER => $procedureId, 'pagination' => 'false']);

        return implode('&', array_filter([
            $this->removeParameters($rawQueryString, self::IGNORED_PARAMETERS),
            $fixedParameters,
        ]));
    }

    /**
     * @param list<string> $names names without brackets, `order` removes `order[x]=y` too
     */
    private function removeParameters(string $queryString, array $names): string
    {
        $remaining = array_filter(
            explode('&', $queryString),
            static fn (string $parameter): bool => !in_array(
                explode('[', urldecode(strstr($parameter, '=', true) ?: $parameter))[0],
                $names,
                true
            )
        );

        return implode('&', $remaining);
    }

    /**
     * The flags are set like API Platform's main controller does for a GET request, because that
     * controller is not involved here.
     */
    private function getCollectionOperation(): HttpOperation
    {
        foreach ($this->resourceMetadataFactory->create(StatementSegmentResource::class) as $resource) {
            foreach ($resource->getOperations() as $operation) {
                if ($operation instanceof CollectionOperationInterface && $operation instanceof HttpOperation) {
                    return $operation->withRead(true)->withValidate(false)->withDeserialize(false);
                }
            }
        }

        throw new LogicException('The StatementSegment resource has no collection operation.');
    }
}
