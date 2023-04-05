<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ServiceImporterInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
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
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;
use function demosplan\DemosPlanDocumentBundle\Tools\count;

/**
 * Import von Planunterlagen-Absaetzen.
 */
class ServiceImporter implements ServiceImporterInterface
{
    /**
     * @var Logger
     */
    private $logger;

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
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var ParagraphRepository
     */
    private $paragraphRepository;

    public function __construct(
        FileService $fileService,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        MessageBagInterface $messageBag,
        ParagraphRepository $paragraphRepository,
        ParagraphService $paragraphService,
        RouterInterface $router
    ) {
        $this->router = $router;
        $this->messageBag = $messageBag;
        $this->fileService = $fileService;
        $this->paragraphService = $paragraphService;
        $this->logger = $logger;
        $this->paragraphRepository = $paragraphRepository;
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
        $payload = [
            'file' => $content,
        ];
        $payload = array_merge($payload, $pictures);
        $msg = Json::encode($payload);

        $this->getLogger()->debug(
            'Export pdf with RabbitMQ, with routingKey: '.$this->globalConfig->getProjectPrefix());
        $this->getLogger()->debug(
            'Content to send to RabbitMQ: '.DemosPlanTools::varExport(base64_decode($content), true));
        $this->getLogger()->debug(
            'Number of pictures send to RabbitMQ: '.count($pictures));

        try {
            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }
            $this->client->addRequest($msg, 'pdfDemosPlan', 'exportPDF', $routingKey, 600);
            $replies = $this->client->getReplies();
            $this->getLogger()->debug('Got replies ', [DemosPlanTools::varExport($replies, true)]);

            $exportResult = Json::decodeToArray($replies['exportPDF']);
            if (null === $exportResult) {
                $this->getLogger()->error('Reply from RabbitMQ: ', [DemosPlanTools::varExport($replies, true)]);
                throw new Exception('Could not decode export result');
            } elseif (!isset($exportResult['file'])) {
                $this->getLogger()->error('AMPQResult has wrong format ', [DemosPlanTools::varExport($exportResult, true)]);
                throw new Exception('AMPQResult has wrong format');
            }

            return $exportResult['file'];
        } catch (AMQPTimeoutException $e) {
            $this->getLogger()->error('Fehler in ImportConsumer:', [$e]);
            throw new TimeoutException('Timeout ');
        } catch (Exception $e) {
            $this->getLogger()->error('Could not create PDF ', [$e]);
            throw new Exception('Could not create PDF ');
        }
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
        try {
            // Generiere Message
            $msg = Json::encode([
                'procedure' => $procedure,
                'category'  => $category,
                'elementId' => $elementId,
                'path'      => $file->getRealPath(),
             ]);

            $routingKey = $this->globalConfig->getProjectPrefix();
            if ($this->globalConfig->isMessageQueueRoutingDisabled()) {
                $routingKey = '';
            }

            // Füge Message zum Request hinzu
            $this->getLogger()->debug(
                'Import docx with RabbitMQ, with routingKey: '.$routingKey);
            $this->client->addRequest($msg, 'importDemosPlan', 'import', $routingKey, 300);
            // Anfrage absenden
            $replies = $this->client->getReplies();

            if ('' != $replies['import']) {
                $this->getLogger()->info(
                    'Incoming message size:'.strlen($replies['import']));
            }

            return Json::decodeToArray($replies['import']);
        } catch (AMQPTimeoutException $e) {
            $this->getLogger()->error('Fehler in ImportConsumer:', [$e]);
            throw new TimeoutException($e->getMessage());
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler in ImportConsumer:', [$e]);
            throw $e;
        }
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
                        $ca = explode('::', $c);
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
                            $stringToReplace = '/file/'.substr($f, 2);
                            $paragraph['text'] = str_replace(
                                $stringToReplace,
                                $this->router->generate('core_file', [
                                    'hash' => $hash,
                                ]),
                                $paragraph['text']
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
}
