<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\RpcController;
use demosplan\DemosPlanCoreBundle\Event\RpcEvent;
use demosplan\DemosPlanCoreBundle\Event\StatementAnonymizeRpcEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementAnonymizeHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Response\EmptyResponse;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatementAnonymizeRpcController extends RpcController
{
    /**
     * @DplanPermissions("area_statement_anonymize")
     *
     * @return RedirectResponse|Response
     */
    #[Route(path: '/rpc/1.0/statement/anonymize', name: 'dplan_rpc_statement_anonymize', options: ['expose' => true])]
    public function statementAnonymizeRpcAction(
        Request $request,
        StatementAnonymizeHandler $statementAnonymizeHandler,
        StatementHandler $statementHandler,
        CurrentUserInterface $currentUser,
        EventDispatcherPostInterface $eventDispatcherPost
    ): Response {
        try {
            $requestData = $this->getIncomingRpcData($request, StatementAnonymizeHandler::FIELDS);

            $actions = $requestData->getActions();
            $statementId = $requestData->getData()[StatementAnonymizeHandler::STATEMENT_ID];
            $statement = $statementHandler->getStatementWithCertainty($statementId);

            $event = new StatementAnonymizeRpcEvent($actions, $statement, $currentUser);
            /** @var RpcEvent $postedEvent */
            $postedEvent = $eventDispatcherPost->post($event);
            if ($postedEvent->hasException()) {
                $this->getLogger()->warning('Could not anonymize statement.', [$postedEvent->getException()]);

                return new Response(null, Response::HTTP_BAD_REQUEST, []);
            }

            $statementAnonymizeHandler->anonymizeStatement($statement, $actions);

            return new EmptyResponse();
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not anonymize statement.', [$e]);

            return new Response(null, Response::HTTP_BAD_REQUEST, []);
        }
    }
}
