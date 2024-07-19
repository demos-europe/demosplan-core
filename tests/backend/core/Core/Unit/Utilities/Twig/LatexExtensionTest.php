<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Twig\Extension\LatexExtension;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;
use Twig\TwigFilter;

/**
 * Teste LatexExtension
 * Class LatexExtensionTest.
 *
 * @group UnitTest
 */
class LatexExtensionTest extends UnitTestCase
{
    /**
     * @var LatexExtension
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $containerStub = $this->getMockForAbstractClass(ContainerInterface::class);

        $fileService = self::$container->get(FileService::class);

        // Stubbe den Logger
        $stub = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->sut = new LatexExtension($containerStub, $fileService, $stub);
    }

    public function testGetFilters()
    {
        $result = $this->sut->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertInstanceOf(TwigFilter::class, $result[0]);
        static::assertSame('latex', $result[0]->getName());
    }

    /**
     * @dataProvider getLatexDataValues
     */
    public function testLatex($toTest, $expected)
    {
        $resultString = $this->sut->latexFilter($toTest);

        self::assertEquals($expected, $resultString);
    }

    public function getLatexDataValues(): array
    {
        return [
                ['<p>gr&ouml;&szlig;er &gt;</p>', 'größer \textgreater~\\\\'],
                ['<p>kleiner &lt;</p>', 'kleiner \textless~\\\\'],
                ['<p>und &amp;</p>', 'und \&\\\\'],
                ['<p>Prozent %</p>', 'Prozent \%\\\\'],
                ['<p>Dollar $</p>', 'Dollar \$\\\\'],
                ['<p>Paragraph &sect;</p>', 'Paragraph \S~\\\\'],
                ['<p>Dingsi &deg;</p>', 'Dingsi °\\\\'],
                ['<p>Dach ^</p>', 'Dach \textasciicircum~\\\\'],
                ['<p>#</p>', '\#\\\\'],
                ['<p>-</p>', '-\\\\'],
                ['<p>.</p>', '.\\\\'],
                ['<p>,</p>', ',\\\\'],
                ['<p>|</p>', '|\\\\'],
                ['<p>!</p>', '!\\\\'],
                ['<p>"</p>', '\dq \\\\'],
                ['<p>/</p>', '/\\\\'],
                ['<p>(</p>', '(\\\\'],
                ['<p>)</p>', ')\\\\'],
                ['<p>=</p>', '=\\\\'],
                ['<p>?</p>', '?\\\\'],
                ['<p>`</p>', '\textquoteleft \\\\'],
                ['<p>&acute;</p>', '\textquoteright \\\\'],
                ['<p>/</p>', '/\\\\'],
                ['<p>*</p>', '*\\\\'],
                ['<p>-</p>', '-\\\\'],
                ['<p>+</p>', '+\\\\'],
                ['<p>{</p>', '\{\\\\'],
                ['<p>[</p>', '\lbrack~\\\\'],
                ['<p>]</p>', '\rbrack~\\\\'],
                ['<p>}</p>', '\}\\\\'],
                ['<p>~</p>', '\textasciitilde\\\\'],
                ['<p>\</p>', '\textbackslash~\\\\'],
                ['<p>http://www.google.de</p>', '\footnote{\protect\url{http://www.google.de}}\\\\'],
                ['<p>http://www.google.de?q=abc&d=fgh</p>', '\footnote{\protect\url{http://www.google.de?q=abc&d=fgh}}\\\\'],
                ['<u>http://www.google.de</u>', '\uline{\footnote{\protect\url{http://www.google.de}}}'],
                ['<p>http://robob-dev.demos-europe.eu</p>', '\footnote{\protect\url{http://robob-dev.demos-europe.eu}}\\\\'],
                ['<p>https://de.wikipedia.org/wiki/Technische_Anleitung_zum_Schutz_gegen_L%C3%A4rm</p>', '\footnote{\protect\url{https://de.wikipedia.org/wiki/Technische_Anleitung_zum_Schutz_gegen_L\%C3\%A4rm}}\\\\'],
                ['<p>http://ad.ad.berlin.demos-europe.eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/</p><p>http://ad.ad.berlin.demos-europe.eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/ad-ad-berlin-demos-europe-eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/</p>',
                    '\footnote{\protect\url{http://ad.ad.berlin.demos-europe.eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/}}\\\\\footnote{\protect\url{http://ad.ad.berlin.demos-europe.eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/ad-ad-berlin-demos-europe-eu/brainstorming/2022/02/planung-und-priorisierung-von-weiterentwicklungen/}}\\\\', ],
            ];
    }

    public function testNewlineEliminationNewline()
    {
        $text = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}\n\\\\asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $expected = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $resultString = $this->sut->killNewlineAfterEndItemize($text);
        $this->assertTrue(is_string($resultString));
        $this->assertEquals($expected, $resultString);
    }

    public function testNewlineEliminationNoNewline()
    {
        $text = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}\\\\asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $expected = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $resultString = $this->sut->killNewlineAfterEndItemize($text);
        $this->assertTrue(is_string($resultString));
        $this->assertEquals($expected, $resultString);
    }

    public function testNewlineEliminationMuchWhitespace()
    {
        $text = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}\n\n     \\\\asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $expected = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $resultString = $this->sut->killNewlineAfterEndItemize($text);
        static::assertTrue(is_string($resultString));
        static::assertEquals($expected, $resultString);
    }

    public function testNewlineEliminationMultipleNewlinesAndSpace()
    {
        $text = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}\n \n     \\\\  \\\\asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $expected = "\\begin{itemize}\n\\item asdfdfef efwfkjwefw we fewf wfw eewfewf\n\\end{itemize}asdfasdfasdfadsfasdfadfasdfadsfa adsfasf adsf asdf asdfa\n asdfasdfadsfasfadsfsdfasdfs";
        $resultString = $this->sut->killNewlineAfterEndItemize($text);
        static::assertTrue(is_string($resultString));
        static::assertEquals($expected, $resultString);
    }

    public function testLatexFilterValidInteger()
    {
        $result = $this->sut->latexFilter(5);
        $this->assertTrue(is_numeric($result));

        $result = $this->sut->latexFilter(5.5);
        $this->assertTrue(is_numeric($result));

        $result = $this->sut->latexFilter('5,5');
        $this->assertTrue(is_string($result));
    }

    public function testPrepareImageTag()
    {
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p><!-- #Image-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252 --></p>";
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertTrue(7 === count($res));
        $this->assertStringContainsString('IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252IMAGEPLACEHOLDER', $res[6]);
    }

    public function testPreparedImageTagToLatexImageReal()
    {
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p><!-- #Image-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252 --></p>";
        $resultString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->latexFilter($resultString);
        $resultString = $this->sut->outputImage($resultString);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertCount(18, $res);
        $this->assertStringContainsString('\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[12]);
        $this->assertStringContainsString('%fileName:6e5f465d-0400-4d1f-8768-703990a358d9:6e5f465d-0400-4d1f-8768-703990a358d9%', $res[13]);
    }

    public function testPreparedImageTagToLatexImageBare()
    {
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9\&width=337\&height=252IMAGEPLACEHOLDEREND</p>";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertTrue(15 === count($res));
        $this->assertStringContainsString('\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[9]);
        $this->assertStringContainsString('%fileName:6e5f465d-0400-4d1f-8768-703990a358d9:6e5f465d-0400-4d1f-8768-703990a358d9%', $res[10]);
    }

    public function testPreparedImageTagToLatexImageBareNoSize()
    {
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9IMAGEPLACEHOLDEREND</p>";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertTrue(15 === count($res));
        $this->assertStringContainsString('\includegraphics{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[9]);
        $this->assertStringContainsString('%fileName:6e5f465d-0400-4d1f-8768-703990a358d9:6e5f465d-0400-4d1f-8768-703990a358d9%', $res[10]);
    }

    public function testPreparedImageTagToLatexImageBareMaxImageSize()
    {
        // too wide

        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9\&width=837\&height=552IMAGEPLACEHOLDEREND</p>";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertTrue(15 === count($res));
        $this->assertStringContainsString('\includegraphics[width=18cm, height=11.870967741935cm]{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[9]);

        // too high
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9\&width=137\&height=1052IMAGEPLACEHOLDEREND</p>";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);
        $this->assertTrue(is_array($res));
        $this->assertTrue(15 === count($res));
        $this->assertStringContainsString('\includegraphics[width=3.4770912547529cm, height=26.7cm]{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[9]);

        // too high and too wide
        $textToTest = "<p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan='2' >Colspan2</td><td rowspan='2' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9\&width=837\&height=1052IMAGEPLACEHOLDEREND</p>";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);
        $this->assertTrue(is_array($res));
        $this->assertTrue(15 === count($res));
        $this->assertStringContainsString('\includegraphics[width=18cm, height=22.623655913978cm]{6e5f465d-0400-4d1f-8768-703990a358d9}', $res[9]);
    }

    public function testPreparedImageTagToLatexImageBareMultiple()
    {
        $textToTest = "Nach \S~ 18 BNatSchG ist über die Belange des Naturschutzes und der Landschaftspflege im Bauleitplan unter entsprechender Anwendung der \S~\S~ 14 und 15 BNatSchG nach den Vorschriften des BauGB zu entscheiden, wenn aufgrund einer Aufstellung, Änderung oder Ergänzung eines Bauleitplanes Eingriffe in Natur und Landschaft zu erwarten sind. Nach Erlass „Verhältnis der naturschutzrechtlichen Eingriffsregelung zum Baurecht“, Gemeinsamer Runderlass des Innenministeriums und des Ministeriums für Energiewende vom 09.12.2013, gültig ab dem 01.01.2014, bereiten Überplanungen bereits beplanter Bereiche keine Eingriffe vor, wenn die Änderungen keine zusätzlichen Eingriffe vorbereiten (Ziffer 2.1 letzter Absatz). \\Die Stadt Reinfeld (Holstein) geht davon aus, dass vorgenanntes hier gegeben ist. Die Einbeziehung der vorhandenen Wohngrundstücke entlang Schillerstraße und Am Schiefen Kamp bereitet keine Eingriffe vor, die Festsetzungen des Bebauungsplanes Nr. 8 werden hier weitgehend unverändert übernommen. \\Die Festsetzung des vorhandenen Gewerbegebäudes mit vorhandenen Umfahrungen, Stellplätzen und Nebengebäuden/Nebenanlagen als Gewerbegebiet ist ebenfalls nicht mit zusätzlichen Eingriffen verbunden. Die Neufestsetzung der Baugrenze in einem Teilbereich südlich des vorhandenen Gebäudes ermöglicht dort zwar geringfügige Erweiterungen; diese wären aber im Rahmen des \S~ 34 BauGB zulässig, wenn es keinen Bebauungsplan geben würde. Der Bereich ist zudem im rechtskräftigen Bebauungsplan als WA-Gebiet und ebenfalls mit einem Baufenster festgesetzt.\\Aufgrund des Erhalts des Gewerbegebäudes verschieben sich die Flächenfestsetzungen für die geplante Neubebauung zwischen Gewerbe und vorhandenen Wohngrundstücken an Schillerstraße und Am Schiefenkamp. In der Summe geht die Stadt Reinfeld (Holstein) hier aber nicht von zusätzlichen Eingriffen aus. Die Grundflächenzahl für die geplanten Baugebiete bleibt mit 0,3 unverändert. Vorhandene Bäume bleiben weitgehend erhalten. Der Anteil der festgesetzten Grünflächen in diesem Bereich nimmt gegenüber den derzeitigen Festsetzungen zu (geplant ca. 7.000 m², B-Plan Nr. 8 ca. 4.600 m²). Die nachfolgenden Abbildungen skizzieren die Unterschiede auf und verdeutlichen, dass keine zusätzlichen Baurechte vorgesehen werden:\\IMAGEPLACEHOLDER-eb96a22e-d94d-410f-9d05-ccf501cb6636\&width=445\&height=325IMAGEPLACEHOLDEREND\\ Abb.: B-Plan 8-1 (Vorentwurf)\\IMAGEPLACEHOLDER-0c8f88bb-650e-4d93-976b-d107c98aeb43\&width=453\&height=316IMAGEPLACEHOLDEREND Abb.: B-Plan 8 (rechtskräftig)\\Mit der Planung tritt für die Gehölzbestandene Fläche an der Straße Am Zuschlag eine Verbesserung ein, da der Fußweg durch die Fläche und die Lärmschutzwand dort entfallen. Die im B-Plan 8 innerhalb dieser Grünfläche festgesetzte Wasserfläche wird nun als Grünfläche festgesetzt, da nach Ortsbesichtigung dort kein Gewässer vorhanden ist. Sollte in der Senke einmal Wasser stehen, ist diese durch die Festsetzung zum Erhalt von Bäumen, Sträuchern und Gewässern geschützt. Auch die Bäume an der Bahnstrecke im Nordosten des Plangebietes werden durch den Verzicht auf die Errichtung der Lärmschutzwand weniger beeinträchtigt.\\Die im Bebauungsplan Nr. 8 der Stadt Reinfeld (Holstein) im Rahmen der durchgeführten Eingriffs-/Ausgleichsbilanzierung vorgesehenen Ausgleichsmaßnahmen wurden bislang nur tlw. umgesetzt, da die Eingriffe noch nicht erfolgt sind. Die Begründung wird im weiteren Verfahren hierzu ergänzt.\\";
        $resultString = $this->sut->outputImage($textToTest);
        $this->assertTrue(is_string($resultString));

        $res = explode("\n", $resultString);

        $this->assertTrue(is_array($res));
        $this->assertTrue(17 === count($res));
        $this->assertStringContainsString('\includegraphics[width=11.77395685cm, height=8.59895725cm]{eb96a22e-d94d-410f-9d05-ccf501cb6636}', $res[3]);
        $this->assertStringContainsString('%fileName:eb96a22e-d94d-410f-9d05-ccf501cb6636:eb96a22e-d94d-410f-9d05-ccf501cb6636%', $res[4]);
        $this->assertStringContainsString('\includegraphics[width=11.98562349cm, height=8.36083228cm]{0c8f88bb-650e-4d93-976b-d107c98aeb43}', $res[11]);
        $this->assertStringContainsString('%fileName:0c8f88bb-650e-4d93-976b-d107c98aeb43:0c8f88bb-650e-4d93-976b-d107c98aeb43%', $res[12]);
    }

    public function testPrepareImageHtmlTag()
    {
        $textToTest = "<img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' >";
        $expected = 'IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9IMAGEPLACEHOLDEREND';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<img src='/file/baf2cdc3-675b-4213-badb-686c13eeaf97/6e5f465d-0400-4d1f-8768-703990a358d9' >";
        $expected = 'IMAGEPLACEHOLDER-baf2cdc3-675b-4213-badb-686c13eeaf97/6e5f465d-0400-4d1f-8768-703990a358d9IMAGEPLACEHOLDEREND';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<img src='/app_dev.php/file/6e5f465d-0400-4d1f-8768-703990a358d9' >";
        $expected = 'IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9IMAGEPLACEHOLDEREND';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' >";
        $expected = 'IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9IMAGEPLACEHOLDEREND';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text'> mit Text danach</p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND mit Text danach</p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text'> mit Text danach und weiterem Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' height='252' width='337' alt='Alternative Text'> </p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND mit Text danach und weiterem Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND </p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text ? & Pa\$§ Abs (4) _ / = !\"''> mit Text danach</p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt=ALTTEXTBEGINAlternative Text ? & Pa$§ Abs (4) _ / = !\'\'ALTTEXTENDIMAGEPLACEHOLDEREND mit Text danach</p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        // skip width & height if not both are provided

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' height='252' alt='Alternative Text'> mit Text danach und weiterem Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' height='252' alt='Alternative Text'> </p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND mit Text danach und weiterem Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND </p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' alt='Alternative Text'> mit Text danach und weiterem Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' height='252' alt='Alternative Text'> </p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND mit Text danach und weiterem Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&alt=ALTTEXTBEGINAlternative TextALTTEXTENDIMAGEPLACEHOLDEREND </p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);

        $textToTest = '<p>Mit Absatz</p><ul><li><p>Erster Listpunkt</p></li><li><p>Zweiter Listpunkt</p></li><li><p>drei</p></li></ul><p>Mit Absatz dahinter, Tabelle folgend</p><p><img src="/app_dev.php/file/30fb843d-9130-11ea-84d9-c8f750b60248" alt="Bug"><br><br><br><br><br></p><table><tbody><tr><td><p>1.1</p></td><td><p>1.2</p></td><td><p>1.3</p></td></tr><tr><td><p>2.1</p></td><td><p>2.2</p></td><td><p>2.3</p></td></tr></tbody></table><p><br></p><p></p><p><br><br><br><br><br></p><table><tbody><tr><td><ul><li><p>Liste</p></li><li><p>In</p></li><li><p>Tabelle</p></li><li><p><strong>fett</strong></p></li></ul></td><td><p>1.2 fett</p></td><td><p>1.3</p></td></tr><tr><td><p>2.1</p></td><td><p>Tabelle in Tabelle 2.2.1.12.2.1.22.2.2.12.2.2.2<br></p></td><td><p>2.3</p></td></tr></tbody></table><p><br></p><p></p><p>Dann eine komplexe Tabelle</p><p><br><br><br><br><br></p><table><tbody><tr><td colspan="2"><p>Colspan2</p></td><td rowspan="2"><p>Rowspan2</p></td></tr><tr><td><p>2.1</p></td><td><p>2.2</p></td></tr></tbody></table><p><br></p><p></p><p>Sodann ein Bild</p><p><img src="/app_dev.php/file/6a171bd6-90b3-11ea-961e-a86daaca30a0"></p><p><strong>Abbildung 1 Ich bin die Superblume</strong></p>';
        $expected = '<p>Mit Absatz</p><ul><li><p>Erster Listpunkt</p></li><li><p>Zweiter Listpunkt</p></li><li><p>drei</p></li></ul><p>Mit Absatz dahinter, Tabelle folgend</p><p>IMAGEPLACEHOLDER-30fb843d-9130-11ea-84d9-c8f750b60248&alt=ALTTEXTBEGINBugALTTEXTENDIMAGEPLACEHOLDEREND<br><br><br><br><br></p><table><tbody><tr><td><p>1.1</p></td><td><p>1.2</p></td><td><p>1.3</p></td></tr><tr><td><p>2.1</p></td><td><p>2.2</p></td><td><p>2.3</p></td></tr></tbody></table><p><br></p><p></p><p><br><br><br><br><br></p><table><tbody><tr><td><ul><li><p>Liste</p></li><li><p>In</p></li><li><p>Tabelle</p></li><li><p><strong>fett</strong></p></li></ul></td><td><p>1.2 fett</p></td><td><p>1.3</p></td></tr><tr><td><p>2.1</p></td><td><p>Tabelle in Tabelle 2.2.1.12.2.1.22.2.2.12.2.2.2<br></p></td><td><p>2.3</p></td></tr></tbody></table><p><br></p><p></p><p>Dann eine komplexe Tabelle</p><p><br><br><br><br><br></p><table><tbody><tr><td colspan="2"><p>Colspan2</p></td><td rowspan="2"><p>Rowspan2</p></td></tr><tr><td><p>2.1</p></td><td><p>2.2</p></td></tr></tbody></table><p><br></p><p></p><p>Sodann ein Bild</p><p>IMAGEPLACEHOLDER-6a171bd6-90b3-11ea-961e-a86daaca30a0IMAGEPLACEHOLDEREND</p><p><strong>Abbildung 1 Ich bin die Superblume</strong></p>';
        $resultString = $this->sut->prepareImage($textToTest);
        $this->assertSame($expected, $resultString);
    }

    public function testImageHtmlTagLatexOutput()
    {
        $textToTest = "<img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' >";
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $res = explode("\n", $resultString);
        $expected = '\includegraphics{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);

        $textToTest = "<img src='/file/baf2cdc3-675b-4213-badb-686c13eeaf97/6e5f465d-0400-4d1f-8768-703990a358d9' >";
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $res = explode("\n", $resultString);
        $expected = '\includegraphics{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);

        $textToTest = "<img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' >";
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $res = explode("\n", $resultString);
        $expected = '\includegraphics{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text'> mit Text danach</p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt="Alternative Text"IMAGEPLACEHOLDEREND mit Text danach</p>';
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $a = $this->sut->latexFilter($preparedString);
        $res = explode("\n", $resultString);
        $expected = '\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text'> mit Text danach und weiterem Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d8' width='337' height='252' alt='Alternative Text'> </p>";
        $expected = '<p>Sodann ein Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d9&width=337&height=252&alt="Alternative Text"IMAGEPLACEHOLDEREND mit Text danach und weiterem Bild IMAGEPLACEHOLDER-6e5f465d-0400-4d1f-8768-703990a358d8&width=337&height=252&alt="Alternative Text"IMAGEPLACEHOLDEREND </p>';
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $a = $this->sut->latexFilter($preparedString);
        $res = explode("\n", $resultString);
        $expected = '\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);
        $expected = '\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d8}';
        $this->assertSame($expected, $res[11]);

        $textToTest = "<p>Sodann ein Bild <img src='/file/6e5f465d-0400-4d1f-8768-703990a358d9' width='337' height='252' alt='Alternative Text ? & Pa\$§ Abs (4) _ / = !\"''> mit Text danach</p>";
        $preparedString = $this->sut->prepareImage($textToTest);
        $resultString = $this->sut->outputImage($this->sut->latexFilter($preparedString));
        $res = explode("\n", $resultString);
        $expected = '\includegraphics[width=8.91645721cm, height=6.66749916cm]{6e5f465d-0400-4d1f-8768-703990a358d9}';
        $this->assertSame($expected, $res[3]);
    }

    public function testTable()
    {
        self::markSkippedForCIIntervention();
        // table-structure needs to be checked more closely

        $textToTest = '<table><tr><th></th><th>test</th></tr><tr><td></td><td>test</td></tr></table>';
        $resultString = $this->sut->latexFilter($textToTest);

        $expected = '<table><tr><td></td><td>test</td></tr><tr><td></td><td>test</td></tr></table>';
        $this->assertEquals($expected, $resultString);
    }

    public function testName()
    {
        $result = $this->sut->getName();
        $this->assertTrue('latex_extension' === $result);
    }

    public function testInvalidValues()
    {
        $inputToTest = [];
        $resultString = $this->sut->latexFilter($inputToTest);
        $this->assertEquals('', $resultString);
    }

    public function testInsTag(): void
    {
        $output = $this->sut->latexFilter('<ins>Foobar</ins>');
        self::assertSame('Foobar', $output);
    }

    public function testKeyWordReplacement(): void
    {
        $html = array_keys(LatexExtension::HTML_TO_LATEX);
        $latex = LatexExtension::HTML_TO_LATEX;

        self::assertCount(count($html), $latex);

        $testText = implode('', $html);
        $expected = implode('', $latex);
        $textToCompare = $this->sut->latexFilter($testText);
        self::assertSame($expected, $textToCompare);
    }

    public function testListStylesAreEqualUlOl(): void
    {
        $ul = $this->sut->latexFilter('<ul>');
        $ol = $this->sut->latexFilter('<ol>');
        $pattern = '/\\\begin{(itemize|enumerate)}/';
        $partsExpectedToBeEqual = preg_replace($pattern, '', [$ul, $ol]);
        self::assertCount(2, $partsExpectedToBeEqual);
        self::assertSame($partsExpectedToBeEqual[0], $partsExpectedToBeEqual[1]);
    }

    public function testListWidthUlOl(): void
    {
        // Test the default list width value
        $ul = $this->sut->latexFilter('<ul>');
        $ol = $this->sut->latexFilter('<ol>');
        self::assertStringContainsString('linewidth-7cm-', $ul);
        self::assertStringContainsString('linewidth-7cm-', $ol);

        // Test with custome list width
        $ul = $this->sut->latexFilter('<ul>', 12);
        $ol = $this->sut->latexFilter('<ol>', 12);
        self::assertStringContainsString('linewidth-12cm-', $ul);
        self::assertStringContainsString('linewidth-12cm-', $ol);
        self::assertStringNotContainsString('linewidth-7cm-', $ul);
        self::assertStringNotContainsString('linewidth-7cm-', $ol);
    }

    public function testStrikeTagReplacement(): void
    {
        $text = '<p>test</p><p></p><p><strong>bold</strong></p><p><em>kursiv</em></p><p><u>unterstrichen</u></p><p><s>durchgestrichen</s></p><p><mark title="markierter Text">markiert</mark></p><p><dp-obscure>geschwärzt</dp-obscure></p>';

        self::assertStringContainsString('<s>', $text);
        self::assertStringContainsString('</s>', $text);
        self::assertStringNotContainsString('<del>', $text);
        self::assertStringNotContainsString('</del>', $text);

        $handledText = $this->sut->latexFilter($text);

        self::assertStringContainsString('\sout{', $handledText);
    }

    public function testDeletionTagReplacement(): void
    {
        $text = '<p>test</p><p></p><p><strong>bold</strong></p><p><em>kursiv</em></p><p><u>unterstrichen</u></p><p><del>durchgestrichen</del></p><p><mark title="markierter Text">markiert</mark></p><p><dp-obscure>geschwärzt</dp-obscure></p>';

        self::assertStringNotContainsString('<s>', $text);
        self::assertStringNotContainsString('</s>', $text);
        self::assertStringContainsString('<del>', $text);
        self::assertStringContainsString('</del>', $text);

        $handledText = $this->sut->latexFilter($text);

        self::assertStringContainsString('\sout{', $handledText);
    }

    public function testHighlightTagReplacement(): void
    {
        $text = '<p><strong>bold</strong></p> <p><em>kursiv</em></p> <p><em><u>kusrivunterstrichen</u></em></p> <p><u>unterstrichen</u></p> <p><s>durchgestrichen</s></p> <p><mark title="markierter Text">markiert</mark></p> <p><mark title="markierter Text"><strong>boldmarkiert</strong></mark></p> <p><dp-obscure>geschwärzt</dp-obscure></p> ';

        self::assertStringContainsString('<mark title="markierter Text">', $text);
        self::assertStringContainsString('</mark>', $text);

        $handledText = $this->sut->latexFilter($text);

        self::assertStringContainsString('\colorbox{yellow}{', $handledText);
    }
}
