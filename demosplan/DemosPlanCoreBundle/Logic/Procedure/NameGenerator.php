<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

class NameGenerator
{
    /**
     * The maximum length of the procedure name part of an export file or folder name.
     *
     * Procedure names are effectively unbounded in length. Embedded unshortened into
     * export entry names they produce paths Windows Explorer refuses to extract
     * (MAX_PATH ~260, shared with the destination folder path).
     */
    public const MAX_PROCEDURE_NAME_LENGTH_IN_EXPORTS = 30;

    /**
     * Shortens a procedure name to the part usable within an export file or folder name.
     *
     * Trailing whitespace and dots are stripped so the shortened name does not collide
     * with the separator or extension that is appended to it.
     */
    public function shortenProcedureNameForExport(string $procedureName): string
    {
        return rtrim(mb_substr($procedureName, 0, self::MAX_PROCEDURE_NAME_LENGTH_IN_EXPORTS), " \t\n\r\0\x0B.");
    }

    /**
     * Generiere den Downloadfilename aus dem übergebenen Dateinamen
     * Der IE braucht eine Extrabehandlung.
     *
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
        }

        return sprintf('attachment;filename="%s"; filename*=UTF-8\'\'%s', $filename, $filenameURLEncoded);
    }
}
