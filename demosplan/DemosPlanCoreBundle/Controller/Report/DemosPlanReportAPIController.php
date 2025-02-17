<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Report;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FinalMailReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GeneralReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PublicPhaseReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\RegisterInvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementReportEntryResourceType;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use Exception;
use League\Fractal\Resource\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Webmozart\Assert\Assert;

class DemosPlanReportAPIController extends APIController
{
    /**
     * Get Report information from Elasticsearch.
     *
     * Optional GET-Parameters:
     *
     * - int limit: Set the number of items per page (default: 10)
     * - int page: Set the requested page (default: 1)
     * - array|string[] category: Set the categories from the requested group (default: [])
     *
     * @DplanPermissions("area_admin_protocol")
     *
     * @param string $group
     */
    #[Route(
        path: '/api/1.0/reports/{procedureId}/{group}',
        methods: ['GET'],
        name: 'dplan_api_report_procedure_list',
        defaults: ['group' => null],
        options: ['expose' => true]
    )]
    public function listProcedureReportsAction(
        JsonApiPaginationParser $paginationParser,
        PaginatorFactory $paginatorFactory,
        Request $request,
        $group = null,
    ): APIResponse {
        $resourceTypeName = match ($group) {
            'general'             => GeneralReportEntryResourceType::getName(),
            'statements'          => StatementReportEntryResourceType::getName(),
            'publicPhase'         => PublicPhaseReportEntryResourceType::getName(),
            'invitations'         => InvitationReportEntryResourceType::getName(),
            'registerInvitations' => RegisterInvitationReportEntryResourceType::getName(),
            'finalMails'          => FinalMailReportEntryResourceType::getName(),
            default               => ReportEntryResourceType::getName(),
        };

        $resourceType = $this->resourceTypeProvider->getTypeByIdentifier($resourceTypeName);
        Assert::isInstanceOf($resourceType, JsonApiResourceTypeInterface::class);

        $pagination = $paginationParser->parseApiPaginationProfile(
            $this->request->query->all('page'),
            $this->request->query->get('sort', '')
        );

        try {
            $paginator = $resourceType->getEntityPaginator($pagination, []);
            $transformer = $resourceType->getTransformer();
            $collection = new Collection($paginator, $transformer, ReportEntryResourceType::getName());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator, $request);
            $collection->setPaginator($paginatorAdapter);

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
