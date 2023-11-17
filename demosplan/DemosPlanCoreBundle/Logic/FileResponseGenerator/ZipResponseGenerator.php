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
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class ZipResponseGenerator extends FileResponseGeneratorAbstract
{
    public function __construct(
        array $supportedTypes,
        NameGenerator $nameGenerator,
        private readonly ZipExportService $zipExportService
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
        // create zip archive
        return $this->zipExportService->buildZipStreamResponse(
            $file['zipFileName'].'.zip',
            fn (ZipStream $zipStream) => $this->fillZipWithData($zipStream, $file)
        );
    }

    /**
     * @throws InvalidDataException
     * @throws Exception
     */
    private function fillZipWithData(ZipStream $zipStream, array $file): void
    {
        $temporaryFullFilePath = DemosPlanPath::getTemporaryPath($file['xlsx']['filename']);
        /** @var IWriter $writer */
        $writer = $file['xlsx']['writer'];
        $writer->save($temporaryFullFilePath);
        $this->zipExportService->addFileToZipStream(
            $temporaryFullFilePath,
            $file['zipFileName'].'/'.$file['xlsx']['filename'],
            $zipStream
        );

        foreach ($file['attachments'] as $attachmentArray) {
            /** @var File $attachment */
            foreach ($attachmentArray as $attachment) {
                $this->zipExportService->addFileToZipStream(
                    $attachment->getFilePathWithHash(),
                    $file['zipFileName'].'/'.$attachment->getHash().'_'.$attachment->getFilename(),
                    $zipStream
                );
            }
        }
    }
}
