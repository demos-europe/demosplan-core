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

    /**
     * Parameters of the export URL that are not part of the list query.
     */
    private const EXPORT_ONLY_PARAMETERS = ['columns'];

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
        $parameters = array_diff_key(
            $this->requestStack->getCurrentRequest()->query->all(),
            array_flip(self::EXPORT_ONLY_PARAMETERS)
        );
        // The procedure comes from the route, not from the query, so it cannot be changed by the client.
        $parameters['parentStatementOfSegment.procedure.id'] = $procedureId;
        $parameters['pagination'] = 'false';

        $operation = $this->getCollectionOperation();
        $request = Request::create(self::API_PATH, 'GET', $parameters);
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
