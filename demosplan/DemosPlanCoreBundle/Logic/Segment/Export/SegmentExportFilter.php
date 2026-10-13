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
 *
 * This class only bridges the export routes, which are no API Platform routes, to that provider chain:
 * the request it builds, the raw query string handling and the operation lookup exist for no other
 * reason. If the export becomes an operation of the StatementSegment resource, API Platform does all
 * of this itself and the class is deleted together with the `Provider::RETURN_ENTITIES` flag.
 */
class SegmentExportFilter
{
    private const LIST_PATH = '/api/3.0/StatementSegment';

    private const PROCEDURE_PARAMETER = 'parentStatementOfSegment.procedure.id';

    /**
     * Parameters of the export URL that the list query must not get: `columns` is only for the
     * exporter, the paging parameters and the procedure are set by the export itself.
     */
    private const EXPORT_ONLY_PARAMETERS = ['columns', 'pagination', 'page', 'itemsPerPage', self::PROCEDURE_PARAMETER];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'api_platform.state_provider.main')]
        private readonly ProviderInterface $stateProvider,
    ) {
    }

    // --- The export: which query does it ask the list for? ---

    /**
     * @return list<Segment> all segments of the procedure that match the filters of the export URL
     */
    public function filter(string $procedureId): array
    {
        return array_values($this->callListProvider($this->getListQueryString($procedureId)));
    }

    /**
     * The query of the export URL, restricted to the procedure of the route and without paging. The
     * procedure does not come from the query, so the client cannot export another one.
     *
     * It is the raw query string, not `$request->query`: PHP replaces the dots in the names of
     * parameters like `tags.id` or `parentStatementOfSegment.externId` when it parses a request,
     * which would silently drop these filters. The API provider parses the raw string itself.
     */
    private function getListQueryString(string $procedureId): string
    {
        $exportQuery = (string) $this->requestStack->getCurrentRequest()->server->get('QUERY_STRING', '');
        $fixedParameters = http_build_query([self::PROCEDURE_PARAMETER => $procedureId, 'pagination' => 'false']);

        return implode('&', array_filter([
            $this->withoutParameters($exportQuery, self::EXPORT_ONLY_PARAMETERS),
            $fixedParameters,
        ]));
    }

    /**
     * @param list<string> $names names without brackets, `order` removes `order[x]=y` too
     */
    private function withoutParameters(string $queryString, array $names): string
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

    // --- The recipe: how to call an API Platform provider from your own code ---

    /**
     * 1. Take the operation of the resource, here its list operation.
     * 2. Build a request for the API URL. The filters are read from its raw QUERY_STRING.
     * 3. Name the resource and the operation in the request attributes.
     * 4. Call the main state provider: parameter validation, filters, access conditions and paging run.
     *
     * @return list<Segment>
     */
    private function callListProvider(string $queryString): array
    {
        $operation = $this->getListOperation();                                    // 1
        $request = Request::create(self::LIST_PATH.'?'.$queryString);               // 2
        $request->attributes->add([                                                // 3
            '_format'             => 'jsonapi',
            '_api_resource_class' => StatementSegmentResource::class,
            '_api_operation_name' => $operation->getName(),
        ]);

        return $this->stateProvider->provide($operation, [], [                     // 4
            'request'                                 => $request,
            'uri_variables'                           => [],
            'resource_class'                          => StatementSegmentResource::class,
            StatementSegmentProvider::RETURN_ENTITIES => true,
        ]);
    }

    /**
     * The flags are set like API Platform's main controller does for a GET request, because that
     * controller is not involved here.
     */
    private function getListOperation(): HttpOperation
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
