<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\Services\PdfNameServiceInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;

class PdfNameService extends CoreService implements PdfNameServiceInterface
{
    /**
     * @param string $filename
     *
     * @return string
     */
    public function generateDownloadFilename($filename)
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
