<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Logic\TransformMessageBagService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Custom Eventlistener
 * Class DemosPlanResponseListener.
 */
class DemosPlanResponseListener
{

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /** @var TransformMessageBagService */
    private $transformMessageBagService;

    public function __construct(
        GlobalConfig $globalConfig,
        TransformMessageBagService $transformMessageBagService
    ) {
        $this->globalConfig = $globalConfig;
        $this->transformMessageBagService = $transformMessageBagService;
    }

    /**
     * Perform search task and orga branding.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        // handle Messages on Redirects
        if (Response::HTTP_FOUND === $event->getResponse()->getStatusCode()) {
            $this->transformMessageBagService->transformMessageBagToFlashes();
        }

        // Xhr Responses need to get messages into data
        if ($event->getResponse() instanceof JsonResponse) {
            $responseContent = Json::decodeToArray($event->getResponse()->getContent());
            $messageBagMessages = $this->transformMessageBagService->transformMessageBagToResponseFormat();
            if (!isset($responseContent['meta']['messages'])) {
                $responseContent['meta']['messages'] = [];
            }
            $responseContent['meta']['messages'] = array_merge_recursive(
                $responseContent['meta']['messages'],
                $messageBagMessages
            );
            // update response content
            $event->getResponse()->setContent(Json::encode($responseContent));
            // set Status code from "no content" to "OK", as content was added
            // As of JSON:API 1.1 this is valid for updates and deletions of resources and relationships.
            // However neither the status code 200 nor responses with 'meta' as only content are specified for resource creations.
            // the addition should be proposed in the discussion forum (https://discuss.jsonapi.org/)
            // if it is rejected an JSON:API extension ("profile") should be specified to document the deviating behaivior
            if (Response::HTTP_NO_CONTENT === $event->getResponse()->getStatusCode()) {
                $event->getResponse()->setStatusCode(Response::HTTP_OK);
            }
        }
    }
}
