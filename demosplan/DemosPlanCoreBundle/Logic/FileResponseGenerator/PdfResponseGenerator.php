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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use Symfony\Component\HttpFoundation\Response;

class PdfResponseGenerator extends FileResponseGeneratorAbstract
{
    public function __construct(array $supportedTypes, NameGenerator $nameGenerator)
    {
        parent::__construct($nameGenerator);
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
        if (!isset($file['content'])) {
            throw new DemosException('error.generic', 'Doc File Response could not be generated because of missing "content" field');
        }
        $response = new Response($file['content'], 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');

            $response->headers->set(
                'Content-Disposition',
                $this->nameGenerator->generateDownloadFilename($file['filename'])
            );


        return $response;
    }
}
