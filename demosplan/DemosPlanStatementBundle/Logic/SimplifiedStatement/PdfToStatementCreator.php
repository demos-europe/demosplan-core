<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Takes care of actions related to importing a PDF into a Statement.
 */
class PdfToStatementCreator extends SimplifiedStatementCreator
{
    /** @var FileUploadService */
    private $fileUploadService;

    /** @var FileService */
    private $fileService;

    /** @var AnnotatedStatementPdfHandler */
    private $annotatedStatementPdfHandler;

    public function __construct(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        CurrentUserInterface $currentUser,
        FileService $fileService,
        FileUploadService $fileUploadService,
        MessageBagInterface $messageBag,
        StatementHandler $statementHandler,
        RouterInterface $router
    ) {
        $this->currentUser = $currentUser;
        $this->messageBag = $messageBag;
        $this->statementHandler = $statementHandler;
        $this->router = $router;
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
        $this->fileService = $fileService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Returns the PDF as file to attach to the Statement.
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
        $annotatedStatementPdf = $this
            ->annotatedStatementPdfHandler
            ->findOneById($request->get('r_annotated_statement_pdf_id'));

        return $this->fileService->createFileStringFromFile($annotatedStatementPdf->getFile());
    }

    /**
     * Implements tasks related to the successful import of the PDF into a Statement (changes
     * the Workflow Status of the AnnotatedStatementPDF and sets the Users confirmation
     * message).
     *
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function handleCreatedStatement(Request $request, Statement $statement): void
    {
        if (null !== $statement) {
            $annotatedStatementPdf = $this->annotatedStatementPdfHandler
                ->findOneById($request->get('r_annotated_statement_pdf_id'));
            $annotatedStatementPdf->setStatement($statement);
            $annotatedStatementPdf->setStatus(AnnotatedStatementPdf::CONVERTED);
            $annotatedStatementPdf->setReviewer(null);
            $this->annotatedStatementPdfHandler->updateObjects([$annotatedStatementPdf]);
            $this->messageBag->add(
                'confirm',
                'confirm.pdf.to.statement.imported',
                ['externId' => $statement->getExternId()]
            );
        }
    }

    /**
     * Selects The route to redirect to depending on whether the pdf import into a Statement
     * has been successful or not.
     *
     * @param array<string, mixed> $params
     */
    protected function redirectResponse(Request $request, array $params): RedirectResponse
    {
        $procedureId = $params[self::PARAM_PROCEDURE_ID];
        $statement = $params[self::PARAM_STATEMENT];
        if ($statement instanceof Statement) {
            $route = 'DemosPlan_procedure_dashboard';
            $paramName = 'procedure';
            if ($this->currentUser->hasPermission('area_statement_data_input_orga')) {
                $route = 'DemosPlan_statement_orga_list';
                $paramName = 'procedureId';
            }

            return new RedirectResponse(
                $this->router->generate(
                    $route,
                    [$paramName => $params[self::PARAM_PROCEDURE_ID]]
                )
            );
        }

        return new RedirectResponse(
            $this->router->generate(
                'dplan_convert_annotated_pdf_to_statement',
                [
                    'procedureId' => $procedureId,
                    'documentId'  => $request->get('r_annotated_statement_pdf_id'),
                ]
            )
        );
    }
}
