<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Faq;

use DemosEurope\DemosplanAddon\Controller\APIController;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;

class FaqApiController extends APIController
{
    /**
     * @Route(path="/api/1.0/faq/{faqId}",
     *        methods={"PATCH"},
     *        name="dp_api_admin_faq_update",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin_faq")
     *
     * @deprecated use `api_resource_update` route instead
     */
    public function updateAction(ApiLogger $apiLogger, string $faqId): Response
    {
        try {
            $apiLogger->warning('Use the generic JSON:API (/api/2.0) instead of /api/1.0 to update Faq resources');

            return $this->redirectToRoute(
                'api_resource_update',
                [
                    'resourceType' => 'Faq',
                    'resourceId'   => $faqId,
                ]
            );
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(path="/api/1.0/faq/{faqId}",
     *        methods={"DELETE"},
     *        name="dp_api_admin_faq_delete",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin_faq")
     */
    public function deleteAction(string $faqId, FaqHandler $faqHandler): APIResponse
    {
        try {
            $faqHandler->deleteFaqById($faqId);

            return $this->renderEmpty(Response::HTTP_NO_CONTENT);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
