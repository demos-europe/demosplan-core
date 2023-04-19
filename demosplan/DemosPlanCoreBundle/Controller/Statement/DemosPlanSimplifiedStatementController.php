<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\CreateSimplifiedStatementEventInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Event\CreateSimplifiedStatementEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\SimplifiedStatement\ManualSimplifiedStatementCreator;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Takes care of actions related to the simplified version of a Statement.
 */
class DemosPlanSimplifiedStatementController extends BaseController
{
    /**
     * Creates a new Statement from the simplified form.
     *
     * @Route(
     *     name="dplan_simplified_new_statement_create",
     *     methods={"POST"},
     *     path="/verfahren/{procedureId}/stellungnahmen/neu",
     *     options={"expose": true}
     * )
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     *
     * @DplanPermissions("feature_simplified_new_statement_create")
     */
    public function createAction(
        TraceableEventDispatcher $eventDispatcher,
        ManualSimplifiedStatementCreator $statementCreator,
        Request $request,
        string $procedureId
    ): Response {
        /** @var CreateSimplifiedStatementEvent $event * */
        $event = $eventDispatcher->dispatch(new CreateSimplifiedStatementEvent($request), CreateSimplifiedStatementEventInterface::class);
        $eventStatementCreator = $event->getStatementFromEmailCreator();
        if (null !== $eventStatementCreator && is_callable($eventStatementCreator)) {
            return $eventStatementCreator($request, $procedureId);
        }

        return $statementCreator($request, $procedureId);
    }
}
