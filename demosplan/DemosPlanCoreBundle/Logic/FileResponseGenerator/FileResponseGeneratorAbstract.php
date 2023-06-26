<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator;

use Symfony\Component\HttpFoundation\Response;

/**
 * Interface FileResponseGeneratorAbstract.
 */
abstract class FileResponseGeneratorAbstract
{
    protected $supportedTypes = [];

    /**
     * Given an array implementing a file, generates the Response based on the specific
     * file format.
     */
    abstract public function __invoke(array $file): Response;

    /**
     * Check whether the implementation can generate the Response object for the given format.
     */
    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }

    /**
     * Generiere den Downloadfilename aus dem übergebenen Dateinamen
     * Der IE braucht eine Extrabehandlung.
     *
     * @param string $filename
     *
     * @return string
     */
    protected function generateDownloadFilename($filename): ?string
    {
        // der IE benötigt mal wieder eine Extrabehandlung.
        $filenameURLEncoded = urlencode($filename);
        // Leerzeichen sollen nicht als + dargestellt werden
        $filenameURLEncoded = str_replace('+', '_', $filenameURLEncoded);

        // " müssen maskiert werden, damit sie nicht im Filename unten den String beenden (je nach Browser unterschiedlich
        // interpretiert)
        $filename = str_replace('"', '\"', $filename);

        // filename*=UTF-8'' ist legacy für den IE (http://greenbytes.de/tech/webdav/rfc5987.html)
        // http://blogs.msdn.com/b/ieinternals/archive/2010/06/07/content-disposition-attachment-and-international-unicode-characters.aspx
        if (false !== stripos(getenv('HTTP_USER_AGENT'), 'MSIE')
            || false !== stripos(getenv('HTTP_USER_AGENT'), 'Internet Explorer')) {
            return sprintf('attachment;filename="%s";', $filenameURLEncoded);
        } else {
            return sprintf('attachment;filename="%s"; filename*=UTF-8\'\'%s', $filename, $filenameURLEncoded);
        }
    }
}
