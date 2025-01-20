<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use Symfony\Component\HttpFoundation\Response;

class CsvHelper
{
    /**
     * Prepares the CSV response with UTF-8 BOM, headers, and charset.
     *
     * @param Response $response The response object
     * @param string $part The part to be added to the filename for the CSV export
     * @param NameGenerator $nameGenerator The name generator for the filename
     *
     * @return Response The prepared response object
     */
    public function prepareCsvResponse(Response $response, string $part, NameGenerator $nameGenerator): Response
    {
        $response->setContent($this->addUtf8Bom($response->getContent()));
        $filename = 'export_'.$part.'_'.date('Y_m_d_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $nameGenerator->generateDownloadFilename($filename));
        $response->setCharset('UTF-8');

        return $response;
    }

    /**
     * Adds UTF-8 BOM for MS-Excel umlauts support.
     *
     * @param string $content The CSV content
     *
     * @return string The CSV content with UTF-8 BOM
     */
    private function addUtf8Bom(string $content): string
    {
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        return $bom . $content;
    }
}
