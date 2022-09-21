<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementImportEmail\StatementImportEmail;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\StatementImportEmailRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementImportEmailResourceType;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class StatementFromEmailCreator extends ManualSimplifiedStatementCreator
{
    private const STATEMENT_IMPORT_EMAIL_ID_URL_PARAM = 'StatementImportEmailId';
    private const STATEMENT_IMPORT_EMAIL_ID_POST = 'r_statement_import_email_id';

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var StatementImportEmailResourceType
     */
    private $statementImportEmailResourceType;

    /**
     * @var StatementImportEmailRepository
     */
    private $emailRepository;

    public function __construct(
        CurrentUserInterface $currentUser,
        EntityFetcher $entityFetcher,
        FileUploadService $fileUploadService,
        MessageBagInterface $messageBag,
        RouterInterface $router,
        StatementHandler $statementHandler,
        StatementImportEmailRepository $emailRepository,
        StatementImportEmailResourceType $statementImportEmailResourceType
    ) {
        parent::__construct(
            $currentUser,
            $fileUploadService,
            $messageBag,
            $statementHandler,
            $router
        );
        $this->entityFetcher = $entityFetcher;
        $this->statementImportEmailResourceType = $statementImportEmailResourceType;
        $this->emailRepository = $emailRepository;
    }

    /**
     * @throws AccessDeniedException if the method would return true but permissions are missing
     */
    public function isImportingStatementViaEmail(Request $request): bool
    {
        if ($request->query->has(self::STATEMENT_IMPORT_EMAIL_ID_URL_PARAM) ||
            $request->request->has(self::STATEMENT_IMPORT_EMAIL_ID_POST)) {
            if (!$this->currentUser->hasAllPermissions(
                'area_admin_import',
                'feature_import_statement_via_email'
            )) {
                throw AccessDeniedException::missingPermissions(
                    null,
                    ['area_admin_import', 'feature_import_statement_via_email']
                );
            }

            return true;
        }

        return false;
    }

    public function getStatementImportEmail(Request $request): StatementImportEmail
    {
        $emailId = $request->query->get(self::STATEMENT_IMPORT_EMAIL_ID_URL_PARAM);
        if (null === $emailId) {
            $emailId = $request->request->get(self::STATEMENT_IMPORT_EMAIL_ID_POST);
        }

        return $this->entityFetcher->getEntityAsReadTarget(
            $this->statementImportEmailResourceType,
            $emailId
        );
    }

    protected function handleCreatedStatement(Request $request, Statement $originalStatement): void
    {
        $email = $this->getStatementImportEmail($request);
        $email->getCreatedStatements()->add($originalStatement);
        $this->emailRepository->flushEverything();
    }

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
