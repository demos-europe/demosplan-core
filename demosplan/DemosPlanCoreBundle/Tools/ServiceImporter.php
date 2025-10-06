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
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Event\CreateReportEntryEvent;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ServiceImporterException;
use demosplan\DemosPlanCoreBundle\Exception\TimeoutException;
use demosplan\DemosPlanCoreBundle\Exception\VirusFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use League\Flysystem\FilesystemOperator;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use ZipArchive;

/**
 * Import von Planunterlagen-Absaetzen.
 */
class ServiceImporter implements ServiceImporterInterface
{
    private const ODT_EXTENSION = '.odt';
    private const ODT_MIME_TYPE = 'application/vnd.oasis.opendocument.text';

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
        private readonly OdtImporter $odtImporter,
        FileService $fileService,
        private readonly FilesystemOperator $defaultStorage,
        GlobalConfigInterface $globalConfig,
        private LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly ParagraphRepository $paragraphRepository,
        ParagraphService $paragraphService,
        private readonly PdfCreatorInterface $pdfCreator,
        private readonly RouterInterface $router,
        RpcClient $client,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->client = $client;
        $this->fileService = $fileService;
        $this->paragraphService = $paragraphService;
        $this->globalConfig = $globalConfig;
    }

    public function checkFileIsValidToImport(FileInfo $fileInfo): void
    {
        // This should probably be in a configuration section
        $allowedMimetypes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
            'application/vnd.oasis.opendocument.text', // ODT
            'application/zip', // Can be ODT or DOCX
            'application/msword',
            'application/octet-stream',
        ];

        // mimetype is allowed
        $contentType = $fileInfo->getContentType();
        if (!in_array($contentType, $allowedMimetypes, true)) {
            $this->getLogger()->warning('MimeType is not allowed. Given MimeType: '.$contentType);
            throw new FileException('MimeType is not allowed. Given MimeType: '.$contentType, 20);
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
            !array_key_exists('paragraphs', $importResult)
            || !array_key_exists('procedure', $importResult)
            || !array_key_exists('elementId', $importResult)
            || !array_key_exists('category', $importResult)
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
                !array_key_exists('text', $paragraph)
                || !array_key_exists('title', $paragraph)
                || !array_key_exists('files', $paragraph)
                || !array_key_exists('nestingLevel', $paragraph)
            ) {
                $this->getLogger()->warning('Paragraph import format invalid. '.DemosPlanTools::varExport($paragraph, true));
                $exception->addErrorParagraph($paragraph['title'] ?? 'noTitle');
                continue;
            }

            // Prüfe ob eine oder mehrere Dateianhänge vorhanden sind
            if (null != $paragraph['files']) {
                foreach ($paragraph['files'] as $files) {
                    foreach ($files as $file => $content) {
                        /* Teile String in 2 Teile
                         * Teil 1 = Dateiname
                         * Teil 2 = Dateiinhalt base64 encoded
                         */
                        $contentParts = explode('::', (string) $content);
                        if (2 === count($contentParts)) {
                            // local file only, no need for flysystem
                            $fs = new Filesystem();
                            // Save decoded file as a temporary file
                            $tmpFilename = $contentParts[0];
                            $tmpFilePath = DemosPlanPath::getTemporaryPath($tmpFilename);
                            $tmpFileContent = $contentParts[1];
                            $fs->dumpFile($tmpFilePath, base64_decode($tmpFileContent));
                            $this->getLogger()->debug('file created', [$tmpFilePath]);

                            $hash = '';
                            // Übergebe temporäre Datei FileService
                            try {
                                $hash = $this->fileService->saveTemporaryLocalFile(
                                    $tmpFilePath,
                                    $tmpFilename,
                                    null,
                                    $procedureId,
                                    FileService::VIRUSCHECK_NONE
                                )->getId();
                            } catch (VirusFoundException $e) {
                                $this->getLogger()->error('Virus found in File ', [$e]);
                            } catch (Exception $e) {
                                $this->getLogger()->error('Error in Fileupload ', [$e]);
                            }

                            // Ersetze Platzhalter im Text mit FileService Hash
                            $stringToReplace = '/file/'.substr((string) $file, 2);
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
                $response = $this->paragraphRepository->add($p);

                $reportEntryEvent = new CreateReportEntryEvent($response, ReportEntry::CATEGORY_ADD);
                $this->eventDispatcher->dispatch($reportEntryEvent);

                // save paragraph as current one in nesting level
                $parentParagraphs[$paragraph['nestingLevel']] = $response->getId();
            } catch (Exception $e) {
                $this->getLogger()->warning('could not add paragraph ', [$e]);
            }
        }
        $this->getLogger()->debug('Anzahl Paragraphs: '.$order);
        if (0 < count($exception->getErrorParagraphs())) {
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
            $fileInfo = $this->fileService->getFileInfoFromFileString($uploadedFile);
            // File needs to be saved as a local file, as any DocxImporterInterface need a local file path
            // no need for flysystem
            $fs = new Filesystem();
            $temporaryPath = DemosPlanPath::getTemporaryPath($fileInfo->getHash());
            $fs->dumpFile($temporaryPath, $this->defaultStorage->read($fileInfo->getAbsolutePath()));
            $file = new File($temporaryPath);
            $this->checkFileIsValidToImport($fileInfo);

            // Detect file type and use appropriate importer
            if ($this->isOdtFile($fileInfo, $file)) {
                $importResult = $this->importOdtFile(
                    $file,
                    $elementId,
                    $procedureId,
                    'paragraph'
                );
            } else {
                $importResult = $this->importDocxWithRabbitMQ(
                    $file,
                    $elementId,
                    $procedureId,
                    'paragraph'
                );
            }
            // cleanup temporary file
            $fs->remove($temporaryPath);
            $this->createParagraphsFromImportResult($importResult, $procedureId);
            // delete uploaded docx
            $this->deleteDocxAfterImportWithRabbitMQ($fileInfo->getHash());

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
     * Detect if file is ODT based on file extension and content.
     */
    private function isOdtFile(FileInfo $fileInfo, File $file): bool
    {
        $contentType = $fileInfo->getContentType();
        $fileName = $fileInfo->getFileName();

        // Check if file has ODT extension or correct MIME type
        $hasOdtExtension = str_ends_with(strtolower($fileName), self::ODT_EXTENSION);
        $hasOdtMimeType = self::ODT_MIME_TYPE === $contentType;

        if (!$hasOdtExtension && !$hasOdtMimeType) {
            return false;
        }

        // Content-based validation: check if file is a ZIP archive and contains correct mimetype
        $filePath = $file->getRealPath();
        if ($filePath && file_exists($filePath)) {
            return $this->validateOdtStructure($filePath);
        }

        return false;
    }

    /**
     * Validate ODT file structure by checking if it's a valid ZIP with correct mimetype.
     */
    private function validateOdtStructure(string $filePath): bool
    {
        // Check if file can be opened as ZIP
        $zip = new ZipArchive();
        if (true !== $zip->open($filePath, ZipArchive::RDONLY)) {
            return false;
        }

        // Check if mimetype file exists and has correct content
        $mimetypeContent = $zip->getFromName('mimetype');
        $zip->close();

        if (false === $mimetypeContent) {
            return false;
        }

        // Verify mimetype content matches ODT specification
        return self::ODT_MIME_TYPE === trim($mimetypeContent);
    }

    /**
     * Import ODT file and convert to paragraph structure.
     */
    public function importOdtFile(File $file, string $elementId, string $procedure, string $category): array
    {
        return $this->odtImporter->importOdt($file, $elementId, $procedure, $category);
    }

    /**
     * @return LoggerInterface
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
        if (str_contains($text, "width='0'")) {
            preg_match_all(sprintf("|<img src='%s' width='0' height='0'>|", $stringToReplace), $text, $matches);
            foreach (array_keys($matches[0]) as $key) {
                try {
                    $fileInfo = $this->fileService->getFileInfo($hash);
                    if ($this->defaultStorage->fileExists($fileInfo->getAbsolutePath())) {
                        $sizeArray = getimagesizefromstring($this->defaultStorage->read($fileInfo->getAbsolutePath()));
                        $text = str_replace(["width='0'", "height='0'"],
                            [
                                sprintf("width='%s'", $sizeArray[0]),
                                sprintf("height='%s'", $sizeArray[1]),
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
