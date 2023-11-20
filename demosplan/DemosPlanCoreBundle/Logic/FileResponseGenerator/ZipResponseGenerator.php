<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use ZipStream\Exception\FileNotFoundException;
use ZipStream\Exception\FileNotReadableException;
use ZipStream\ZipStream;

class ZipResponseGenerator extends FileResponseGeneratorAbstract
{
    public function __construct(
        array $supportedTypes,
        NameGenerator $nameGenerator,
        private readonly ZipExportService $zipExportService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($nameGenerator);
        $this->supportedTypes = $supportedTypes;
    }

    public function __invoke(array $file): Response
    {
        return $this->createStreamedResponseForZip($file);
    }

    private function createStreamedResponseForZip(array $file): StreamedResponse
    {
        return $this->zipExportService->buildZipStreamResponse(
            $file['zipFileName'].'.zip',
            fn (ZipStream $zipStream) => $this->fillZipWithData($zipStream, $file)
        );
    }

    private function fillZipWithData(ZipStream $zipStream, array $file): void
    {
        $errorMessages = [];
        $temporaryFullFilePath = DemosPlanPath::getTemporaryPath($file['xlsx']['filename']);
        /** @var IWriter $writer */
        $writer = $file['xlsx']['writer'];
        try {
            $writer->save($temporaryFullFilePath);
            $this->zipExportService->addFileToZipStream(
                $temporaryFullFilePath,
                $file['zipFileName'].'/'.$file['xlsx']['filename'],
                $zipStream
            );
        } catch (FileNotFoundException|FileNotReadableException $e) {
            $this->logger->critical('Unable to load or read file from path.', [$e]);
            $errorMessages[] = $this->translator->trans('error.statements.zip.export.generic.xlsx');
        } catch (WriterException|Exception $e) {
            $this->logger->critical('An error occured during creation of zip file for export.', [$e]);
            $errorMessages[] = $this->translator->trans('error.statements.zip.export.generic.xlsx');
        }

        foreach ($file['attachments'] as $attachmentArray) {
            /** @var File $attachment */
            foreach ($attachmentArray as $attachment) {
                try {
                    $this->zipExportService->addFileToZipStream(
                        $attachment->getFilePathWithHash(),
                        $file['zipFileName'].'/'.$attachment->getHash().'_'.$attachment->getFilename(),
                        $zipStream
                    );
                } catch (FileNotFoundException|FileNotReadableException $e) {
                    $this->logger->critical('Unable to load or read file from path.', [$e]);
                    $errorMessages[] = $this->translator->trans(
                        'error.statments.zip.export.filenotfoundorreadable',
                        ['hash' => $attachment->getHash()]
                    );
                } catch (InvalidDataException $e) {
                    $this->logger->critical(
                        'The hash for a file, which should be added to a zip export is not a string,
            is an empty string, equals \'..\' or contains a slash (\'/\').',
                        [$e]
                    );
                    $errorMessages[] = $this->translator->trans('error.statments.zip.export.hash.invalid');
                } catch (Exception $e) {
                    $this->logger->critical('An error occured during creation of zip file for export.', [$e]);
                    $errorMessages[] = $this->translator->trans('error.statements.zip.export.generic.attachment');
                }
            }
        }

        if (0 < count($errorMessages)) {
            $this->addErrorTextFile($zipStream, $errorMessages);
        }
    }

    private function addErrorTextFile(ZipStream $zipStream, array $errorMessages): void
    {
        $zipStream->addFile(
            'errors.txt',
            implode("\n", $errorMessages)
        );
    }
}
