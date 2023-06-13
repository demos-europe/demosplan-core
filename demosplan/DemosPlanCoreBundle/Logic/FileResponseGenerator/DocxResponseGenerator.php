<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocxResponseGenerator extends FileResponseGeneratorAbstract
{
    public function __construct(array $supportedTypes)
    {
        $this->supportedTypes = $supportedTypes;
    }

    /**
     * Given an array implementing a file, generates the Response based on the specific
     * file format.
     *
     * @throws DemosException
     */
    public function __invoke(array $file): Response
    {
        if (!isset($file['writer'])) {
            throw new DemosException('error.generic', 'Doc File Response could not be generated because of missing "writer" field');
        }
        $writer = $file['writer'];
        $response = new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            }
        );

        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set(
            'Content-Disposition',
            $this->generateDownloadFilename($file['filename'])
        );

        return $response;
    }
}
