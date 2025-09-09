<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Document;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class DocumentDashboardTransformer extends BaseTransformer
{
    protected $type = 'DocumentDashboard';

    protected array $availableIncludes = ['mapOptions', 'procedureMapInfo', 'documents'];

    public function transform(array $dashboardData): array
    {
        $data = [
            'id'           => $dashboardData['id'],
            'planText'     => $dashboardData['planText'],
            'hasGisLayers' => $dashboardData['hasGisLayers'],
        ];

        if ($this->permissions->hasPermission('feature_procedure_planning_area_match')) {
            $data = ['planningArea'           => $dashboardData['planningArea'], 'availablePlanningAreas' => $dashboardData['availablePlanningAreas'], ...$data];
        }

        return $data;
    }

    public function includeMapOptions(array $data): Item
    {
        return $this->resourceService->makeItem(
            $data['mapOptions'],
            'dp.transformers.map.options'
        );
    }

    public function includeDocuments(array $data): Collection
    {
        return $this->resourceService->makeCollectionOfResources(
            $data['documents'],
            PlanningDocumentCategoryResourceType::getName()
        );
    }

    public function includeProcedureMapInfo(array $data): Item
    {
        return $this->resourceService->makeItemOfResource(
            $data['map'],
            // TODO: doesn't seem right
            PlanningDocumentCategoryResourceType::getName()
        );
    }
}
