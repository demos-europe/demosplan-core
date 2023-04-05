<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
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
     * @var ProcedureService
     */
    private $procedureService;

    /**
     * @throws Exception
     */
    public function __construct(
        DocumentHandler $documentHandler,
        Environment $twig,
        FileService $fileService,
        LoggerInterface $logger,
        ParagraphService $paragraphService,
        ProcedureService $procedureService,
        ServiceImporter $serviceImporter
    ) {
        $this->documentHandler = $documentHandler;
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->paragraphService = $paragraphService;
        $this->procedureService = $procedureService;
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
            $this->logger->info('Pdf: Gefundene Bilder: '.count($imagematches[2]));
            foreach ($imagematches[2] as $match) {
                try {
                    $file = $this->fileService->getFileInfo($match);

                    if (is_file($file->getAbsolutePath())) {
                        $this->logger->info('Pdf: Bild auf der Platte gefunden');
                        $fileContent = file_get_contents($file->getAbsolutePath());
                        $pictures['picture'.$i] = $file->getHash().'###'.$file->getFileName().'###'.base64_encode($fileContent);
                        ++$i;
                    }
                } catch (Exception $e) {
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
        $procedure = $this->procedureService->getProcedure($procedureId);
        // Template Variable aus Storage Ergebnis erstellen(Output)
        $documentList = $this->paragraphService->getParaDocumentObjectList($procedureId, $category);
        if (0 === count($documentList)) {
            return null;
        }

        $templateVars['list'] = ['documentlist' => $documentList];
        $templateVars['procedure'] = $procedure;

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
