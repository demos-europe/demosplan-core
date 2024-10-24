<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\News;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Twig\Environment;

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

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        Environment $twig,
        private readonly FilesystemOperator $defaultStorage,
        FileService $serviceFiles,
        private readonly GlobalNewsHandler $globalNewsHandler,
        private readonly LoggerInterface $logger,
        private readonly ProcedureNewsService $procedureNewsService,
        ServiceImporter $serviceImporter,
    ) {
        $this->fileService = $serviceFiles;
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
     * @throws ReflectionException
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
     * @throws Exception
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

        // Generiere das PDF
        // Gibt es in den News Bilder?
        $pictures = [];
        $i = 0;
        foreach ($outputResult as $singleNews) {
            if (0 < strlen((string) $singleNews['picture'])) {
                $fileInfo = $this->fileService->getFileInfoFromFileString($singleNews['picture']);
                if ($this->defaultStorage->fileExists($fileInfo->getAbsolutePath())) {
                    $fileContent = $this->defaultStorage->read($fileInfo->getAbsolutePath());
                    $pictures['picture'.$i] = $fileInfo->getHash().'###'.$fileInfo->getFileName().'###'.base64_encode($fileContent);
                    ++$i;
                }
            }
        }
        $this->logger->debug('Send Content to tex2pdf consumer: '.DemosPlanTools::varExport($content, true));

        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content), $pictures);
        $pdf = base64_decode($response);

        $this->logger->debug('Got Response: '.DemosPlanTools::varExport($pdf, true));

        return $pdf;
    }
}
