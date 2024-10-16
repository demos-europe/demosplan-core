<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ServiceImporterInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ServiceImporterException;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;

/**
 * Import von Planunterlagen-Absaetzen.
 */
class ServiceImporter implements ServiceImporterInterface
{
    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var ParagraphService
     */
    protected $paragraphService;

    /**
     * @var RpcClient
     */
    protected $client;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(
        private readonly DocxImporterInterface $docxImporter,
        FileService $fileService,
        GlobalConfigInterface $globalConfig,
        private LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly ParagraphRepository $paragraphRepository,
        ParagraphService $paragraphService,
        private readonly PdfCreatorInterface $pdfCreator,
        private readonly RouterInterface $router,
        RpcClient $client
    ) {
        $this->client = $client;
        $this->fileService = $fileService;
        $this->paragraphService = $paragraphService;
        $this->globalConfig = $globalConfig;
    }

    /**
     * Prüfe, ob die Datei importiert werden kann.
     *
     * @param File  $file
     * @param array $allowedMimetypes
     *
     * @return File|FileException
     */
    public function checkFileIsValidToImport($file, $allowedMimetypes)
    {
        // $file kann eine in Symfony hochgeladene Datei oder eine stdClass aus dem Unittest sein
        if ($file instanceof File) {
            // check for MimeType
            $mimeType = $file->getMimeType();

            // mimetype is allowed
            if (in_array($mimeType, $allowedMimetypes, true)) {
                return $file;
            }

            $this->getLogger()->warning('MimeType is not allowed. Given MimeType: '.$mimeType);
            throw new FileException('MimeType is not allowed. Given MimeType: '.$mimeType, 20);
        }
    }

    /**
     * Erstelle ein PDF aus einer tex-Vorlage.
     *
     * @param string $content  base64 encodierte tex-Datei
     * @param array  $pictures array der Form ['picture0 => base64_encode(''), 'picture1' => ....]
     *
     * @return string
     *
     * @throws Exception
     */
    public function exportPdfWithRabbitMQ($content, $pictures = [])
    {
        return $this->pdfCreator->createPdf($content, $pictures);
    }

    /**
     * Importiere ein docx-Dokument mittels RabbitMQ Instanz.
     *
     * @param string $elementId
     * @param string $procedure
     * @param string $category
     *
     * @return array
     *
     * @throws Exception
     */
    public function importDocxWithRabbitMQ(File $file, $elementId, $procedure, $category)
    {
        return $this->docxImporter->importDocx(
            $file,
            $elementId,
            $procedure,
            $category
        );
    }

    /**
     * @throws Exception
     */
    private function deleteDocxAfterImportWithRabbitMQ(string $fileHash)
    {
        try {
            $this->fileService->deleteFile($fileHash);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not delete uploaded docx file ', [$e]);
        }
    }

    public function createParagraphsFromImportResult(array $importResult, string $procedureId): void
    {
        $order = 0;
        $exception = new ServiceImporterException();

        if (
            !array_key_exists('paragraphs', $importResult) ||
            !array_key_exists('procedure', $importResult) ||
            !array_key_exists('elementId', $importResult) ||
            !array_key_exists('category', $importResult)
        ) {
            $this->getLogger()->warning('Paragraph importresult format invalid. '.DemosPlanTools::varExport($importResult, true));
            throw new InvalidArgumentException('Paragraph importresult format invalid');
        }

        // set initial order depending on current order
        $maxOrder = $this->paragraphService->getMaxOrderFromElement($importResult['elementId']);
        if (0 < $maxOrder) {
            $order = $maxOrder + 1;
        }

        // Variables to keep track of last paragraph in nestingLevel
        $parentParagraphs = [0 => null];

        foreach ($importResult['paragraphs'] as $paragraph) {
            if (
                !array_key_exists('text', $paragraph) ||
                !array_key_exists('title', $paragraph) ||
                !array_key_exists('files', $paragraph) ||
                !array_key_exists('nestingLevel', $paragraph)
            ) {
                $this->getLogger()->warning('Paragraph import format invalid. '.DemosPlanTools::varExport($paragraph, true));
                $exception->addErrorParagraph($paragraph['title'] ?? 'noTitle');
                continue;
            }

            // Prüfe ob eine oder mehrere Dateianhänge vorhanden sind
            if (null != $paragraph['files']) {
                foreach ($paragraph['files'] as $files) {
                    foreach ($files as $f => $c) {
                        /* Teile String in 2 Teile
                         * Teil 1 = Dateiname
                         * Teil 2 = Dateiinhalt base64 encoded
                         */
                        $ca = explode('::', (string) $c);
                        if (2 === count($ca)) {
                            $fs = new Filesystem();
                            // Speichere dekodierte Datei als temporäre Datei
                            $fs->dumpFile(
                                sys_get_temp_dir(
                                ).DIRECTORY_SEPARATOR.$ca[0],
                                base64_decode($ca[1])
                            );
                            $this->getLogger()->debug(
                                "Datei '".sys_get_temp_dir(
                                ).DIRECTORY_SEPARATOR.$ca[0]."' angelegt."
                            );
                            $lf = new UploadedFile(
                                sys_get_temp_dir(
                                ).DIRECTORY_SEPARATOR.$ca[0], $ca[0]
                            );

                            $hash = '';
                            // Übergebe temporäre Datei FileService
                            try {
                                $hash = $this->fileService->saveTemporaryFile(
                                    $lf->getPathname(),
                                    $ca[0],
                                    null,
                                    $procedureId,
                                    FileService::VIRUSCHECK_NONE
                                )->getId();
                            } catch (VirusFoundException $e) {
                                $this->getLogger()->error('Virus found in File ', [$e]);
                            } catch (Exception $e) {
                                $this->getLogger()->error('Error in Fileupload ', [$e]);
                            }

                            $this->getLogger()->debug(
                                "Datei '".sys_get_temp_dir(
                                ).DIRECTORY_SEPARATOR.$ca[0]."' hochgeladen. FileService Hash: ".$hash
                            );
                            // Lösche temporäre Datei
                            $fs->remove(
                                sys_get_temp_dir(
                                ).DIRECTORY_SEPARATOR.$ca[0]
                            );
                            // Ersetze Platzhalter im Text mit FileService Hash
                            $stringToReplace = '/file/'.substr((string) $f, 2);
                            $paragraph['text'] = $this->fixImageSize($paragraph['text'], $stringToReplace, $hash);
                            $paragraph['text'] = str_replace(
                                $stringToReplace,
                                $this->router->generate('core_file_procedure', [
                                    'hash'        => $hash,
                                    'procedureId' => $procedureId,
                                ]),
                                (string) $paragraph['text']
                            );
                        }
                    }
                }
            }

            // caclculate hierarchy, headings may not be sequential
            $parentId = null;
            for ($i = 1; $i <= $paragraph['nestingLevel']; ++$i) {
                if (isset($parentParagraphs[$paragraph['nestingLevel'] - $i])) {
                    $parentId = $parentParagraphs[$paragraph['nestingLevel'] - $i];
                    break;
                }
            }

            // Erzeuge Paragraph Zeile
            $p = [
                'text'      => $paragraph['text'],
                'title'     => $paragraph['title'],
                'pId'       => $importResult['procedure'],
                'elementId' => $importResult['elementId'],
                'category'  => $importResult['category'],
                'parentId'  => $parentId,
                'order'     => $order++,
            ];
            // Persistiere Paragraph Zeile
            try {
                $response = $this->paragraphRepository
                    ->add($p);
                $this->getLogger()->debug('Paragraph:'.serialize($response));

                // save paragraph as current one in nesting level
                $parentParagraphs[$paragraph['nestingLevel']] = $response->getId();
            } catch (Exception $e) {
                $this->getLogger()->warning('could not add paragraph ', [$e]);
            }
        }
        $this->getLogger()->debug('Anzahl Paragraphs: '.$order);
        if (0 < (is_countable($exception->getErrorParagraphs()) ? count($exception->getErrorParagraphs()) : 0)) {
            throw $exception;
        }
    }

    /**
     * Uploads a single File.
     *
     * @param string $elementId
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function uploadImportFile($elementId, $procedureId, $uploadedFile): void
    {
        if ('' === $uploadedFile) {
            $this->messageBag->add('warning', 'warning.import.selected');

            return;
        }

        // Transform document and save to database
        try {
            // @improve T14122

            $fileInfo = $this->fileService->getFileInfoFromFileString($uploadedFile);
            $file = new File($fileInfo->getAbsolutePath());

            // This should probably be in a configuration section
            $documentImportMimeTypes = [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/msword',
                'application/octet-stream',
            ];

            $file = $this->checkFileIsValidToImport(
                $file,
                $documentImportMimeTypes
            );
            $mimeType = $file->getMimeType();

            if (in_array($mimeType, $documentImportMimeTypes, true)) {
                $importResult = $this->importDocxWithRabbitMQ(
                    $file,
                    $elementId,
                    $procedureId,
                    'paragraph'
                );
                $this->createParagraphsFromImportResult($importResult, $procedureId);
                // delete uploaded docx
                $this->deleteDocxAfterImportWithRabbitMQ($fileInfo->getHash());
            }

            $this->messageBag->add('confirm', 'confirm.import');
        } catch (FileException $e) {
            $message = 'warning.import.uploaderror';

            // `20` suggests a disallowed MIME type
            if (20 === $e->getCode()) {
                $message = 'warning.filetype';
            }

            $this->messageBag->add('warning', $message);
            throw $e;
        } catch (ServiceImporterException $e) {
            $this->messageBag->add(
                'warning',
                'warning.import.importerror',
                ['errorParagraphs' => implode(', ', $e->getErrorParagraphs())]
            );
            throw $e;
        } catch (TimeoutException $e) {
            $this->messageBag->add('error', 'error.timeout');
            throw $e;
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.import.general');
            throw $e;
        }
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return RpcClient
     */
    protected function getClient()
    {
        return $this->client;
    }

    /**
     * @param RpcClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * DocxImporter may not be able to calculate the correct image size. In this case the importer will set the width and height to 0.
     * This method will fix the image size by reading the image file and setting the correct width and height.
     */
    private function fixImageSize(string $text, string $stringToReplace, string $hash): string
    {
        // as the tag is generated by the importer we can rely on a specific format
        if(str_contains($text, "width='0'")) {
            preg_match_all(sprintf("|<img src='%s' width='0' height='0'>|", $stringToReplace), $text, $matches);
            foreach ($matches[0] as $key => $match) {
                try {
                    $fileInfo = $this->fileService->getFileInfo($hash);
                    if (is_file($fileInfo->getAbsolutePath())) {
                        $sizeArray = getimagesize($fileInfo->getAbsolutePath());
                        $text = str_replace(["width='0'", "height='0'"],
                            [
                                sprintf("width='%s'", $sizeArray[0]),
                                sprintf("height='%s'", $sizeArray[1])
                            ],
                            $text);
                    }
                } catch (Exception) {
                    $this->logger->info('Could not fix image size for file', [$hash]);
                }
            }
        }

        return $text;
    }
}
