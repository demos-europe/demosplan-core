<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ParagraphExporter
{
    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var ServiceImporter
     */
    protected $serviceImporter;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var DocumentHandler
     */
    protected $documentHandler;

    /**
     * @var ParagraphService
     */
    protected $paragraphService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @throws Exception
     */
    public function __construct(
        DocumentHandler $documentHandler,
        Environment $twig,
        FileService $fileService,
        LoggerInterface $logger,
        ParagraphService $paragraphService,
        private readonly ProcedureService $procedureService,
        ServiceImporter $serviceImporter
    ) {
        $this->documentHandler = $documentHandler;
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->paragraphService = $paragraphService;
        $this->serviceImporter = $serviceImporter;
        $this->twig = $twig;
    }

    /**
     * Extract Pictures from rendered TexDocument.
     * Looking for the indocator "includegraphics".
     *
     * @param string $content - TexDoxument
     *
     * @return array - array of specific formatted picturestrings
     *
     * @throws Exception
     */
    private function getPicturesFromText(string $content)
    {
        $pictures = [];
        $imagematches = [];
        $i = 0;

        preg_match_all('/includegraphics(\[.*\])?\{(.*)\}/', $content, $imagematches);
        if (isset($imagematches[2])) {
            $this->logger->info('Pdf: Gefundene Bilder: '.(is_countable($imagematches[2]) ? count($imagematches[2]) : 0));
            foreach ($imagematches[2] as $match) {
                try {
                    $file = $this->fileService->getFileInfo($match);

                    if (is_file($file->getAbsolutePath())) {
                        $this->logger->info('Pdf: Bild auf der Platte gefunden');
                        $fileContent = file_get_contents($file->getAbsolutePath());
                        $pictures['picture'.$i] = $match.'###'.$file->getFileName().'###'.base64_encode($fileContent);
                        ++$i;
                    }
                } catch (Exception) {
                    $this->logger->warning('Could not find Picture referenced in conten', ['hash' => $match, 'content' => $content]);
                }
            }
        }

        return $pictures;
    }

    /**
     * @param string $procedureId
     * @param string $title
     * @param string $category
     *
     * @return bool|string
     *
     * @throws ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePdf($procedureId, $title, $category)
    {
        $templateVars = [];
        $procedure = $this->procedureService->getProcedure($procedureId);
        // Template Variable aus Storage Ergebnis erstellen(Output)
        $documentList = $this->paragraphService->getParaDocumentObjectList($procedureId, $category);
        if (0 === count($documentList)) {
            return null;
        }

        $templateVars['list'] = ['documentlist' => $documentList];
        $templateVars['procedure'] = $procedure;
        // the line width of lists inside the generated pdf differs and that depends on which circumstances. In this case
        // the pdf vertical format view will not be split and the width has to be adjusted to 17 instead the default 7cm.
        $templateVars['listwidth'] = 17;

        $content = $this->twig->render(
            '@DemosPlanCore/DemosPlanDocument/paragraph_list_export.tex.twig',
            [
                'procedure'    => $procedureId,
                'templateVars' => $templateVars,
                'title'        => $title,
                'category'     => $category,
            ]
        );

        $pictures = $this->getPicturesFromText($content);

        // Schicke das Tex-Dokument zum PDF-Consumer und bekomme das pdf
        $response = $this->serviceImporter->exportPdfWithRabbitMQ(base64_encode($content), $pictures);
        $pdf = base64_decode($response);
        $this->logger->debug('Got Response: '.DemosPlanTools::varExport($pdf, true));

        return $pdf;
    }
}
