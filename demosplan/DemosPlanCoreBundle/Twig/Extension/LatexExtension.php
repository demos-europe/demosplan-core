<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Twig\TwigFilter;

use function preg_quote;
use function preg_replace;

/**
 * Wysiwyg-Editor.
 */
class LatexExtension extends ExtensionBase
{
    /**
     * @var FileService
     */
    protected $fileService;

    final public const HTML_TO_LATEX = [
        '\\'                                   => '\textbackslash~',
        '{'                                    => '\{',
        '}'                                    => '\}',
        '['                                    => '\lbrack~',
        ']'                                    => '\rbrack~',
        '<u>'                                  => '\uline{',
        '</u>'                                 => '}',
        '<del>'                                => '\sout{',
        '</del>'                               => '}',
        '<s>'                                  => '\sout{',
        '</s>'                                 => '}',
        '<mark title="markierter Text">'       => '\colorbox{yellow}{',
        '</mark>'                              => '}',
        '<i>'                                  => '{\itshape ',
        '</i>'                                 => '}',
        '<em>'                                 => '{\itshape ',
        '</em>'                                => '}',
        '<strong>'                             => '{\bfseries ',
        '</strong>'                            => '}',
        '<b>'                                  => '{\bfseries ',
        '</b>'                                 => '}',
        '<ins>'                                => '',
        '</ul>'                                => '\end{itemize}',
        '</ol>'                                => '\end{enumerate}',
        '<li>'                                 => '\item ',
        '</li>'                                => '',
        '<dp-obscure>'                         => '\censor{',
        '</dp-obscure>'                        => '}',
        '´'                                    => '\textquoteright ',
        '`'                                    => '\textquoteleft ',
        '&'                                    => '\&',
        '$'                                    => '\$',
        '<span>'                               => '',
        '</span>'                              => '',
        "' "                                   => '\textquoteright~',
        "'"                                    => '\textquoteright ',
        '&#039; '                              => '\textquoteright~',
        '&#039;'                               => '\textquoteright ',
        '§'                                    => '\S~',
        '" '                                   => '\dq~',
        '"'                                    => '\dq ',
        '#'                                    => '\#',
        '_'                                    => '\_',
        '€'                                    => '\texteuro~',
        '%'                                    => '\%',
        '^'                                    => '\textasciicircum~',
        '█'                                    => '\ding{122}',
        '­'                                    => '\-',
        '</ins>'                               => '',
    ];

    public function __construct(ContainerInterface $container, FileService $serviceFile, private readonly LoggerInterface $logger)
    {
        parent::__construct($container);
        $this->fileService = $serviceFile;
    }

    /**
     * Get Twig Filters.
     *
     * @see AbstractExtension::getFilters()
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'latex', function (
                    $string,
                    $listwidth = 7
                ) {
                    return $this->latexFilter($string, $listwidth);
                }
            ),
            new TwigFilter('nl2texnl', $this->latexNewlineFilter(...)),
            new TwigFilter('latexPrepareImage', $this->prepareImage(...)),
            new TwigFilter('htmlPrepareImage', $this->prepareHtmlImage(...)),
            new TwigFilter('latexOutputImage', $this->outputImage(...)),
            new TwigFilter('latexGetImageDimensions', $this->getImageDimensions(...)),
        ];
    }

    /**
     * Turns newlines into latex-linebreaks.
     */
    public function latexNewlineFilter($text)
    {
        return str_replace("\n", '\\\\', (string) $text);
    }

    /**
     * HTML-Filter fuer Eingaben aus dem WYSIWYG-Editor.
     *
     * @param string $text
     *
     * @return string
     *
     * @throws Exception
     */
    public function latexFilter($text, int $listwidth = 7)
    {
        try {
            // return numeric values without conversion
            if (is_numeric($text)) {
                return $text;
            }

            if (!is_string($text)) {
                $this->logger->warning('Could not convert Latexinput '.DemosPlanTools::varExport($text, true));

                return '';
            }

            $this->logger->debug('latexFilter start: '.$text);
            // replace tilde temporary
            $text = str_replace('~', 'LATEXtextasciitildeLATEX', $text);

            // Prepare tagging URLs with according latex-markup
            $linkregex = '/https?:\/\/(www\.)?[\-\w@:%.+~#=]{2,256}\.[a-z]{2,4}\b([\-\w@:%+.~#?&\/=]*)/iuS';

            $urlHits = [];
            preg_match_all($linkregex, $text, $urlHits);

            $hitcount = 0;
            $sanitizedHits = [];
            $urlPlaceholderPattern = '-+-+-+';
            foreach ($urlHits[0] as $hit) {
                $hitRegex = sprintf('/%s(\s|$|<)/', preg_quote((string) $hit, '/'));
                $text = preg_replace($hitRegex, $urlPlaceholderPattern.++$hitcount.'$1', $text);

                // urls may contain % chars, these need to be sanitized for latex
                $sanitizedHit = str_replace('%', '\%', (string) $hit);
                $sanitizedHits[] = str_replace($hit, '\footnote{\protect\url{'.$sanitizedHit.'}}', (string) $hit);
            }

            // Tabellen-Tags behandeln
            $text = str_replace('<th>', '<td>', $text);

            // Findet td-Tags mit colspan.
            $text = preg_replace(
                '/<td [^>]*(colspan=([\"|\']?)([0-9]+))([\"|\']?).+>/Usi',
                '<tcs\\3>',
                $text
            );

            // Unterstreichungen behandeln
            $text = preg_replace(
                '/<span style=\"text-decoration\: underline\;\">(.*)<\/span>/Usi',
                '<u>\\1</u>',
                $text
            );

            // Durchstreichungen behandeln
            $text = preg_replace(
                '/<span style=\"text-decoration\: line\-through\;\">(.*)<\/span>/Usi',
                '<del>\\1</del>',
                $text
            );

            // Alle Tag-Parameter killen
            $text = preg_replace(
                '/<(p|br|table|tr|td|th|div|ol|u|del|i|strike|ul|li|b|strong|em|span)\s.*>/Usi',
                '<\\1>',
                $text
            );

            // Alle anderen Tags beseitigen
            $text = strip_tags(
                $text,
                '<p><table><tr><td><tcs2><tcs><tcs3><tcs4><tcs5><tcs6><th><br><ol><strike><u><s><del><i><ol><ul><li><b><strong><em><span><ins><mark><dp-obscure>'
            );

            // remove <ins> title attribute
            $text = preg_replace('/<ins[^>]*>(.*)<\/ins>/Usi', '<ins>\\1</ins>', $text);

            $this->logger->debug('text nach striptags'.$text);

            // UTF8!
            if (null != $text && '' != $text) {
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            }

            $text = str_replace("\r", '', $text);

            // Latex-Umbau
            $text = str_replace(array_keys(self::HTML_TO_LATEX), self::HTML_TO_LATEX, $text);
            if (false !== stripos($text, '<table')) {
                $text = $this->processTable($text);
            }
            $htmlToLatexList = [
                '<ul>'      => sprintf('\begin{itemize}[leftmargin=0.5cm,rightmargin=\dimexpr\linewidth-%scm-\leftmargin\relax]', $listwidth),
                '<ol>'      => sprintf('\begin{enumerate}[leftmargin=0.5cm,rightmargin=\dimexpr\linewidth-%scm-\leftmargin\relax]', $listwidth),
            ];
            $text = str_replace(array_keys($htmlToLatexList), $htmlToLatexList, $text);

            // Replace found URLs with latex-style URLs
            $hitcount = 0;
            foreach ($sanitizedHits as $sanitizedHit) {
                $text = str_replace($urlPlaceholderPattern.++$hitcount, $sanitizedHit, (string) $text);
            }

            // replace tilde placeholder to latext tilde
            $text = str_replace('LATEXtextasciitildeLATEX', '\textasciitilde', (string) $text);

            $posthtml = [
                '<p>',
                '</p>',
                '<br>',
                '<br />',
            ];

            $postlatex = [
                '',
                '\\\\', // "\\\\\n",
                '\\\\', // "\\\\\n",
                '\\\\', // "\\\\\n"
            ];

            // Kill situations in which a end{itemize} is directly followed by a latex paragraph feed
            $text = $this->killNewlineAfterEndItemize($text);

            // Eliminate all occurences of carriage returns
            $text = preg_replace('_\\r_si', '', $text);

            $text = str_replace($posthtml, $postlatex, $text);

            // Umsetzung von < und > im PDF, an letzter Stelle sonst werden html-tags zerstört
            $text = str_replace(
                ['<', '>'],
                ['\textless~', '\textgreater~'],
                $text
            );

            $this->logger->debug('nach html replace  '.$text);
        } catch (Exception $e) {
            $this->logger->error($e);
            throw $e;
        }

        return $text;
    }

    /**
     * Process Table.
     *
     * @param string $text
     */
    public function processTable($text)
    {
        $tablesmatcharray = null;
        preg_match_all("/<table>.*<\/table>/isU", $text, $tablesmatcharray);

        for ($t1 = 0, $t1Max = count($tablesmatcharray); $t1 < $t1Max; ++$t1) {
            $latextable = '';
            // avoid problems with invalid html table markup
            if (!is_array($tablesmatcharray[0]) || !array_key_exists($t1, $tablesmatcharray[0])) {
                continue;
            }
            $currenttable = $tablesmatcharray[0][$t1];

            $currenttable = str_replace("\n", '', (string) $currenttable);
            $currenttable = str_replace("\r", '', $currenttable);
            $currenttable = str_replace('<p>', '', $currenttable);
            $currenttable = str_replace('</p>', '', $currenttable);
            $currenttable = str_replace('<table>', '', $currenttable);
            $currenttable = str_replace('</table>', '', $currenttable);

            // Zähle die Anzahl der Spalten
            $countCellsArray = null;
            preg_match_all(
                "/<tr>.*<\/tr>/isU",
                $currenttable,
                $countCellsArray
            );

            // count the inner segments
            $firstrow = $countCellsArray[0][0];
            $numberofcells = substr_count((string) $firstrow, '<td>');

            if ($numberofcells > 0) {
                $cellwidth = round(14 / $numberofcells, 2);
            } else {
                $cellwidth = 14;
            }

            // Oben gefundene Multicolmn-Tags durch den entsprechenden LaTex-Code ersetzen
            for ($tci = 1; $tci < 11; ++$tci) {
                $currenttable = preg_replace(
                    '/<tcs'.$tci.">(.*)<\/td>/Usi",
                    "\multicolumn{".$tci.'}{|p{'.$tci * $cellwidth.'cm}|} {\\1}',
                    $currenttable
                );
            }

            // Hole dir die einzelnen Spalten in einen Array
            $oneTablerowsarray = null;
            preg_match_all(
                "/<tr>.*<\/tr>/isU",
                $currenttable,
                $oneTablerowsarray
            );

            //  Table definition
            $latextable .= "\n\\begin{longtable}{|";
            for ($tc = 0; $tc < $numberofcells; ++$tc) {
                $latextable .= 'p{'.$cellwidth.'cm}|';
            }
            $latextable .= "}\n\\hline\n";

            // Tabelle reihenweise durchgehen
            for ($tr = 0, $trMax = is_countable($oneTablerowsarray[0]) ? count($oneTablerowsarray[0]) : 0; $tr < $trMax; ++$tr) {
                $currentrow = $oneTablerowsarray[0][$tr];
                // Replace <tr>
                $currentrow = str_replace('<tr>', '', (string) $currentrow);
                $currentrow = str_replace(
                    '</tr>',
                    "\\\\\n\\hline\n",
                    $currentrow
                );

                $currentrow = str_replace('&  \\', ' \\', $currentrow);

                // Das letzte Spalten-& in einer Reihe entfernen
                if (false !== stripos($currentrow, '</td>')) {
                    $currentrow = substr_replace(
                        $currentrow,
                        '',
                        strrpos($currentrow, '</td>'),
                        5
                    );
                }
                $currenttable = str_replace(
                    $oneTablerowsarray[0][$tr],
                    $currentrow,
                    $currenttable
                );
            }

            // Replace <td>
            $currenttable = str_replace('</td>', '&', $currenttable);
            $currenttable = str_replace('<td>', '', $currenttable);

            $latextable .= $currenttable;

            $latextable .= "\n\\end{longtable}";
            // replace LaTex table back into text

            $text = str_replace($tablesmatcharray[0][$t1], $latextable, $text);

            // Table data
        }

        return $text;
    }

    /**
     * Removes LaTeX newlines after \end{itemize}.
     *
     * @param string $text
     *
     * @return string
     */
    public function killNewlineAfterEndItemize($text)
    {
        return preg_replace(
            "_\\\\end{itemize}(\s*?\\\\\\\\)+_si",
            '\\end{itemize}',
            $text
        );
    }

    /**
     * Ersetze die Platzhalter, die beim Import der Bilder eingefügt wurden durch
     * Platzhalter, die den Latexfilter überstehen.
     *
     * @param string $text
     *
     * @return string
     */
    public function prepareImage($text)
    {
        preg_match_all(
            '|[.*]?<!-- #Image-([a-z0-9&=\-].*?) -->[.*]?|',
            $text,
            $imageMatches,
            PREG_PATTERN_ORDER
        );
        // Wenn du kein Bild gefunden hast, durchsuche den nächsten Absatz
        if (count($imageMatches[1]) > 0) {
            // Wenn du ein oder mehrere Bilder gefunden hast gehe sie durch
            foreach ($imageMatches[1] as $matchKey => $match) {
                // und ersetze den Platzhalter durch das Imagetag mit dem korrkten Hash
                $currentImageTex = 'IMAGEPLACEHOLDER-'.$match.'IMAGEPLACEHOLDEREND';
                $text = preg_replace(
                    '|'.$imageMatches[0][$matchKey].'|',
                    $currentImageTex,
                    $text
                );
            }
        }

        return $this->prepareImageHtmlTag($text);
    }

    /**
     * Replace image html tags with the same format as former html comment
     * image information. They are converted into latex includegraphics
     * later on.
     *
     * @param string $text
     */
    protected function prepareImageHtmlTag($text): string
    {
        preg_match_all(
            '/<img.*?>/',
            $text,
            $imageMatches
        );
        // nothing to replace
        if (0 === (is_countable($imageMatches[0]) ? count($imageMatches[0]) : 0)) {
            return $text;
        }
        foreach ($imageMatches[0] as $imageMatch) {
            // build placeholderstring
            $currentImageTex = 'IMAGEPLACEHOLDER-';

            preg_match('|src=[\\\'"](\/app_dev\.php)?\/file\/([\w-]*[\/\w-]*)[\\\'"]|', (string) $imageMatch, $src);
            $currentImageTex .= $src[2] ?? '';

            // only add width and height if both are provided
            preg_match('|width=[\\\'"]([\d-]*)[\\\'"]|', (string) $imageMatch, $width);
            preg_match('|height=[\\\'"]([\d-]*)[\\\'"]|', (string) $imageMatch, $height);
            if (2 === count($width) && 2 === count($height)) {
                $currentImageTex .= '&width='.$width[1].'&height='.$height[1];
            }

            preg_match('|alt=[\\\'"]([[:print:]§]+)[\\\'"]|u', (string) $imageMatch, $alt);
            if (2 === count($alt)) {
                // replace doublequotes as alt text is wrapped in "
                $altText = str_replace('"', '\'', $alt[1]);
                // use strange placeholder as this string is treated by
                // $this->latexFilter() before outputted in $this->outputImage()
                $currentImageTex .= '&alt=ALTTEXTBEGIN'.$altText.'ALTTEXTEND';
            }
            $currentImageTex .= 'IMAGEPLACEHOLDEREND';

            // replace Image tag with placeholderstring
            $text = str_replace($imageMatch, $currentImageTex, $text);
        }

        return $text;
    }

    public function prepareHtmlImage($text)
    {
        preg_match_all(
            '|[.*]?<!-- #Image-([a-z0-9&=\-].*?) -->[.*]?|',
            (string) $text,
            $imageMatches,
            PREG_PATTERN_ORDER
        );
        // Wenn du kein Bild gefunden hast, durchsuche den nächsten Absatz
        if (count($imageMatches[1]) > 0) {
            // Wenn du ein oder mehrere Bilder gefunden hast gehe sie durch
            foreach ($imageMatches[1] as $matchKey => $match) {
                // und ersetze den Platzhalter durch das Imagetag mit dem korrkten Hash
                $currentImageTex = '<img src = "'.$match.'">';
                $text = preg_replace(
                    '|'.$imageMatches[0][$matchKey].'|',
                    $currentImageTex,
                    (string) $text
                );
            }
        }

        return $text;
    }

    /**
     * Ersetze den Platzhalter für Bilder durch die Latexanweisung dafür.
     *
     * @param string $text
     *
     * @return string
     */
    public function outputImage($text)
    {
        // until no alternative caption in latex is implemented, remove
        // alt text from Image
        $text = preg_replace('/ALTTEXTBEGIN(.*?)ALTTEXTEND/', '', $text);
        preg_match_all(
            // '/[.*]?IMAGEPLACEHOLDER-([\\a-z0-9\-&=]*)[.*]?(IMAGEPLACEHOLDEREND)?/U',
            '/[.*]?IMAGEPLACEHOLDER-([^IMAGEPLACEHOLDEREND]+)/',
            $text,
            $imageMatches,
            PREG_PATTERN_ORDER
        );
        // Wenn du kein Bild gefunden hast, durchsuche den nächsten Absatz
        if (count($imageMatches[1]) > 0) {
            // Wenn du ein oder mehrere Bilder gefunden hast gehe sie durch
            foreach ($imageMatches[1] as $matchKey => $match) {
                $parts = explode('\&', $match);
                // if contains / explode
                if(str_contains($parts[0], '/')) {
                    $parts = explode('/', $parts[0]);
                    $fileHash = $parts[1];
                } else {
                    $fileHash = $parts[0];
                }
                // Bestimme die Größe des Bildes
                if (isset($parts[1]) && isset($parts[2])) {
                    $widthParts = explode('=', $parts[1]);
                    $width = $widthParts[1] ?? null;
                    $heightParts = explode('=', $parts[2]);
                    $height = isset($heightParts[1]) ? str_replace('\\\\', '', $heightParts[1]) : null;
                    $sizeCommand = $this->getLatexSizeCommand($width, $height);
                } else {
                    $sizeCommand = $this->getImageDimensions($fileHash);
                }

                // und ersetze den Platzhalter durch das Imagetag mit dem korrkten Hash
                $currentImageTex = '
\begin{figure}[H]
\centering
\includegraphics'.$sizeCommand.'{'.$fileHash.'}
%fileName:'.$fileHash.':'.$fileHash.'%
\end{figure}
\FloatBarrier
\\
';
                // Ersetze die \ durch \\ im Regex
                $pregReplacePatternFileinfo = '|'.str_replace(
                    '\\',
                    '\\\\',
                    $imageMatches[0][$matchKey]
                ).'IMAGEPLACEHOLDEREND|';
                // Füge das Latexmarkup ein
                $text = preg_replace(
                    $pregReplacePatternFileinfo,
                    $currentImageTex,
                    $text
                );
            }
        }

        return $text;
    }

    /**
     * Convert pixel into Latex Sizecommand.
     *
     * @param int $width
     * @param int $height
     *
     * @return string
     */
    public function getLatexSizeCommand($width, $height)
    {
        // 1px == 0.02645833cm
        // https://www.unitconverters.net/typography/pixel-x-to-centimeter.htm
        $widthCm = $width * 0.02645833;
        $heightCm = $height * 0.02645833;

        // Prüfe, dass die Bilder nicht zu groß für a4 (210 * 297)mm sind
        $maxWidthCm = 18;
        $maxHeightCm = 26.7;

        if ($widthCm > $maxWidthCm || $heightCm > $maxHeightCm) {
            $wFactor = $widthCm / $maxWidthCm;
            $hFactor = $heightCm / $maxHeightCm;

            if ($wFactor > $hFactor) {
                $factor = $wFactor;
            } else {
                $factor = $hFactor;
            }

            // resize Image
            if (0 != $factor) {
                $widthCm = $widthCm / $factor;
                $heightCm = $heightCm / $factor;
            }
            $this->logger->info('Image resize to width: '.$widthCm.' and height: '.$heightCm);
        }

        return '[width='.$widthCm.'cm, height='.$heightCm.'cm]';
    }

    /**
     * Return Latex Sizecommand.
     *
     * @param string $hash
     *
     * @return string
     */
    public function getImageDimensions($hash)
    {
        try {
            $fileInfo = $this->fileService->getFileInfo($hash);
            if (is_file($fileInfo->getAbsolutePath())) {
                $sizeArray = getimagesize($fileInfo->getAbsolutePath());

                return $this->getLatexSizeCommand($sizeArray[0], $sizeArray[1]);
            }
        } catch (Exception) {
            // return default value
        }

        return '';
    }
}
