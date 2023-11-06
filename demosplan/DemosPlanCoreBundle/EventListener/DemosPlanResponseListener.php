<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Logic\TransformMessageBagService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\SecurityUserProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Custom Eventlistener
 * Class DemosPlanResponseListener.
 */
class DemosPlanResponseListener
{
    public function __construct(
        private readonly SecurityUserProvider $securityUserProvider,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TransformMessageBagService $transformMessageBagService
    ) {
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $this->handleMessagesOnRedirects($event);
        $this->xhrResponsesNeedToGetMessagesIntoData($event);
        $this->transformTokenUserObjectToSecurityUserObject();
    }

    private function handleMessagesOnRedirects(ResponseEvent $event): void
    {
        if (Response::HTTP_FOUND === $event->getResponse()->getStatusCode()) {
            $this->transformMessageBagService->transformMessageBagToFlashes();
        }
    }

    private function xhrResponsesNeedToGetMessagesIntoData(ResponseEvent $event): void
    {
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

    /**
     * Authentication is done with the special SecurityUser, not with the User
     * entity. One of the reasons is that the objects gets serialized
     * in the session between requests and the doctrine entity with all its dependencies
     * may be huge to serialize. Therefore, we use the special SecurityUser that has only
     * those properties that are needed for authorization. During the request it
     * is swapped by the User entity {@see SecurityUserProvider::refreshUser()}.
     */
    private function transformTokenUserObjectToSecurityUserObject(): void
    {
        // unauthenticated requests do not have a token
        $existingToken = $this->tokenStorage->getToken();
        if (null === $existingToken) {
            return;
        }

        // subrequests may already have SecurityUser as user
        // once be got rid of the subrequests triggered in twig by `render()` calls,
        // this can be removed
        if ($existingToken->getUser() instanceof SecurityUser) {
            return;
        }

        $securityUser = $this->securityUserProvider->getSecurityUser(
            $existingToken->getUser()?->getLogin() ?? ''
        );

        $existingToken->setUser($securityUser);
    }
}
