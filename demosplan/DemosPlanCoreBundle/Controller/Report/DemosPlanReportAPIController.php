<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Report;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FinalMailReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GeneralReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PublicPhaseReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\RegisterInvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementReportEntryResourceType;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\AccessException;
use Exception;
use League\Fractal\Resource\Collection;
use Symfony\Component\Routing\Annotation\Route;

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
    #[Route(path: '/api/1.0/reports/{procedureId}/{group}', methods: ['GET'], name: 'dplan_api_report_procedure_list', defaults: ['group' => null], options: ['expose' => true])]
    public function listProcedureReportsAction(
        JsonApiPaginationParser $paginationParser,
        EntityFetcher $entityFetcher,
        PaginatorFactory $paginatorFactory,
        $group = null
    ): APIResponse {
        switch ($group) {
            case 'general':
                $resourceTypeName = GeneralReportEntryResourceType::getName();
                break;
            case 'statements':
                $resourceTypeName = StatementReportEntryResourceType::getName();
                break;
            case 'publicPhase':
                $resourceTypeName = PublicPhaseReportEntryResourceType::getName();
                break;
            case 'invitations':
                $resourceTypeName = InvitationReportEntryResourceType::getName();
                break;
            case 'registerInvitations':
                $resourceTypeName = RegisterInvitationReportEntryResourceType::getName();
                break;
            case 'finalMails':
                $resourceTypeName = FinalMailReportEntryResourceType::getName();
                break;
            default:
                $resourceTypeName = ReportEntryResourceType::getName();
                break;
        }

        $resourceType = $this->resourceTypeProvider->requestType($resourceTypeName)
            ->instanceOf(ResourceTypeInterface::class)
            ->getInstanceOrThrow();

        if (!$resourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($resourceType);
        }

        $pagination = $paginationParser->parseApiPaginationProfile(
            $this->request->query->get('page', []),
            $this->request->query->get('sort', '')
        );

        try {
            $paginator = $entityFetcher->getEntityPaginator($resourceType, $pagination, []);
            $transformer = $resourceType->getTransformer();
            $collection = new Collection($paginator, $transformer, ReportEntryResourceType::getName());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator);
            $collection->setPaginator($paginatorAdapter);

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
