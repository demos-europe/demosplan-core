<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\SimplifiedStatement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Takes care of actions related to creating the simplified version of a Statement.
 * Delegates the details depending on the origin of the info to its specific implementations.
 */
abstract class SimplifiedStatementCreator
{
    // Possible keys for values needed by the implementations of this class
    protected const PARAM_PROCEDURE_ID = 'procedureId';
    protected const PARAM_STATEMENT = 'statement';

    /** @var StatementHandler */
    protected $statementHandler;

    /** @var CurrentUserInterface */
    protected $currentUser;

    /** @var MessageBagInterface */
    protected $messageBag;

    /** @var RouterInterface */
    protected $router;

    /**
     * @throws MessageBagException
     * @throws UserNotFoundException
     */
    public function __invoke(
        Request $request,
        string $procedureId
    ): Response {
        $rParams = $request->request->all();
        $fileUpload = $this->getFileUpload($request);
        if (null !== $fileUpload) {
            $rParams['fileupload'] = $fileUpload;
        }
        $originalFileUpload = $this->getOriginalFileUpload($request);
        if (null !== $originalFileUpload) {
            $rParams['originalAttachments'] = [$originalFileUpload];
        }
        if (array_key_exists('r_tags', $rParams) && is_array($rParams['r_tags'])) {
            $rParams['r_recommendation'] = $this->statementHandler->addBoilerplatesOfTags(
                $rParams['r_tags']
            );
        }
        $statement = $this->statementHandler->newStatement(
            $rParams,
            $this->currentUser->hasPermission('feature_statement_data_input_orga')
        );
        if (false !== $statement && null !== $statement) {
            $this->handleCreatedStatement($request, $statement);
        }

        return $this->redirectResponse(
            $request,
            [
                self::PARAM_PROCEDURE_ID => $procedureId,
                self::PARAM_STATEMENT    => $statement,
            ]
        );
    }

    /**
     * @return mixed
     */
    abstract protected function getFileUpload(Request $request);

    /**
     * @return mixed
     */
    abstract protected function getOriginalFileUpload(Request $request);

    abstract protected function handleCreatedStatement(Request $request, Statement $statement): void;

    /**
     * @param array<string, mixed> $params
     */
    abstract protected function redirectResponse(Request $request, array $params): RedirectResponse;
}
