<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\News;

use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Twig\Environment;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanDocumentBundle\Tools\ServiceImporter;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;

class ServiceOutput
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var ServiceImporter
     */
    protected $serviceImporter;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var ProcedureNewsService
     */
    private $procedureNewsService;

    /**
     * @var GlobalNewsHandler
     */
    private $globalNewsHandler;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CurrentUserInterface $currentUser,
        Environment $twig,
        FileService $serviceFiles,
        GlobalNewsHandler $globalNewsHandler,
        LoggerInterface $logger,
        ProcedureNewsService $procedureNewsService,
        ServiceImporter $serviceImporter
    ) {
        $this->currentUser = $currentUser;
        $this->fileService = $serviceFiles;
        $this->globalNewsHandler = $globalNewsHandler;
        $this->logger = $logger;
        $this->procedureNewsService = $procedureNewsService;
        $this->serviceImporter = $serviceImporter;
        $this->twig = $twig;
    }

    /**
     * Get procedure news.
     *
     * @param string      $procedure
     * @param string|null $manualSortScope
     * @param int|null    $limit
     * @param array       $roles
     *
     * @throws \ReflectionException
     */
    // @improve T24347
    public function newsListHandler($procedure, $manualSortScope, $limit = null, $roles = []): array
    {
        return $this->procedureNewsService->getNewsList(
            $procedure,
            $this->currentUser->getUser(),
            $manualSortScope,
            $limit,
            $roles
        )['result'];
    }

    /**
     * Get global news.
     *
     * @throws ReflectionException
     * @throws UserNotFoundException
     */
    public function globalNewsListHandler(): array
    {
        return $this->globalNewsHandler->getNewsList($this->currentUser->getUser());
    }

    /**
     * Generate PDF.
     *
     * @param string|null $procedure
     * @param string      $manualSortScope
     * @param string      $title
     *
     * @return string
     *
     * @throws \Exception
     */
    public function generatePdf($procedure, $manualSortScope, $title)
    {
        // Template Variable aus Storage Ergebnis erstellen(Output)
        if (null === $procedure) {
            $outputResult = $this->globalNewsListHandler();
        } else {
            $outputResult = $this->newsListHandler($procedure, $manualSortScope);
        }

        $templateVars = [
            'list'      => ['newslist' => $outputResult],
            'procedure' => $procedure,
        ];

        // Erstelle das tex-Dokument
        $content = $this->twig->render(
            '@DemosPlanCore/DemosPlanNews/newsexport.tex.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => $title,
            ]
        );

        //Generiere das PDF
        // Gibt es in den News Bilder?
        $pictures = [];
        $i = 0;
        foreach ($outputResult as $singleNews) {
            if (0 < strlen($singleNews['picture'])) {
                $fileInfo = $this->fileService->getFileInfoFromFileString($singleNews['picture']);
                if (is_file($fileInfo->getAbsolutePath())) {
                    $fileContent = file_get_contents($fileInfo->getAbsolutePath());
                    $pictures['picture'.$i] = $fileInfo->getHash().'###'.$fileInfo->getFileName().'###'.base64_encode($fileContent);
                    ++$i;
                }
            }
        }
        $this->logger->debug('Send Content to tex2pdf consumer: '.DemosPlanTools::varExport($content, true));

        //Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content), $pictures);
        $pdf = base64_decode($response);

        $this->logger->debug('Got Response: '.DemosPlanTools::varExport($pdf, true));

        return $pdf;
    }
}
