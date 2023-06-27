<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\SimplifiedStatement;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Creates a statement from the info coming from a simplified new Statement form.
 */
class ManualSimplifiedStatementCreator extends SimplifiedStatementCreator
{
    public function __construct(
        CurrentUserInterface $currentUser,
        private readonly FileUploadService $fileUploadService,
        MessageBagInterface $messageBag,
        StatementHandler $statementHandler,
        RouterInterface $router
    ) {
        $this->currentUser = $currentUser;
        $this->messageBag = $messageBag;
        $this->statementHandler = $statementHandler;
        $this->router = $router;
    }

    /**
     * Checks whether there is an uploaded file to attach to the Statement.
     * If there is returns it, otherwise returns null.
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function getFileUpload(Request $request)
    {
        $fParams = $this->fileUploadService->prepareFilesUpload($request, 'r_upload');
        if (null !== $fParams && '' !== $fParams) {
            return $fParams;
        }

        return null;
    }

    /**
     * @return mixed|null
     *
     * @throws Exception
     */
    protected function getOriginalFileUpload(Request $request)
    {
        $fParams = $this
            ->fileUploadService
            ->prepareFilesUpload($request, 'r_attachment_original');
        if (null !== $fParams && '' !== $fParams) {
            return $fParams;
        }

        return null;
    }

    /**
     * Implements tasks related to the successful creation of the Statement.
     *
     * @throws MessageBagException
     */
    protected function handleCreatedStatement(Request $request, Statement $statement): void
    {
        // Not needed in this context, at least not for now.
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function redirectResponse(Request $request, array $params): RedirectResponse
    {
        return new RedirectResponse(
            $this->router->generate(
                'DemosPlan_procedure_import',
                ['procedureId' => $params[self::PARAM_PROCEDURE_ID]]
            )
        );
    }
}
