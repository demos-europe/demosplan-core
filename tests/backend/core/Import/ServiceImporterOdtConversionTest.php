<?php

declare(strict_types=1);

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Tools\OdtImporter;
use demosplan\DemosPlanCoreBundle\Tools\DocxImporterInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Tools\PdfCreatorInterface;
use League\Flysystem\FilesystemOperator;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ServiceImporterOdtConversionTest extends TestCase
{
    private ServiceImporter $serviceImporter;
    private OdtImporter $odtImporter;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $docxImporter = $this->createMock(DocxImporterInterface::class);
        $odtImporter = $this->createMock(OdtImporter::class);
        $fileService = $this->createMock(FileService::class);
        $defaultStorage = $this->createMock(FilesystemOperator::class);
        $globalConfig = $this->createMock(GlobalConfigInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $messageBag = $this->createMock(MessageBagInterface::class);
        $paragraphRepository = $this->createMock(ParagraphRepository::class);
        $paragraphService = $this->createMock(ParagraphService::class);
        $pdfCreator = $this->createMock(PdfCreatorInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $client = $this->createMock(RpcClient::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->serviceImporter = new ServiceImporter(
            $docxImporter,
            $odtImporter,
            $fileService,
            $defaultStorage,
            $globalConfig,
            $logger,
            $messageBag,
            $paragraphRepository,
            $paragraphService,
            $pdfCreator,
            $router,
            $client,
            $eventDispatcher
        );

        $this->odtImporter = new OdtImporter();
    }

    public function testConvertHtmlToParagraphStructureWithHeadings(): void
    {
        $html = '<html><body>
            <p>Introduction text before any heading.</p>
            <h1>Main Heading</h1>
            <p>This is the first paragraph content.</p>
            <h2>Sub Heading</h2>
            <p>This is the second paragraph with some more detailed content that should be handled properly.</p>
            <h3>Sub Sub Heading</h3>
            <p>Another paragraph here.</p>
        </body></html>';

        $result = $this->callOdtImporterPrivateMethod('convertHtmlToParagraphStructure', [$html]);

        // Should have 4 paragraphs: intro + 3 headings
        $this->assertCount(4, $result);

        // Check introduction paragraph (content before first heading)
        $this->assertEquals('Introduction text before any heading.', $result[0]['title']);
        $this->assertEquals(0, $result[0]['nestingLevel']);
        $this->assertStringContainsString('<p>Introduction text before any heading.</p>', $result[0]['text']);

        // Check first heading and its content
        $this->assertEquals('Main Heading', $result[1]['title']);
        $this->assertEquals(1, $result[1]['nestingLevel']);
        $this->assertStringContainsString('<p>This is the first paragraph content.</p>', $result[1]['text']);

        // Check second heading and its content
        $this->assertEquals('Sub Heading', $result[2]['title']);
        $this->assertEquals(2, $result[2]['nestingLevel']);
        $this->assertStringContainsString('<p>This is the second paragraph with some more detailed content that should be handled properly.</p>', $result[2]['text']);

        // Check third heading and its content
        $this->assertEquals('Sub Sub Heading', $result[3]['title']);
        $this->assertEquals(3, $result[3]['nestingLevel']);
        $this->assertStringContainsString('<p>Another paragraph here.</p>', $result[3]['text']);
    }

    public function testConvertHtmlToParagraphStructureWithLongContent(): void
    {
        $longText = 'This is a very long paragraph that contains much more than one hundred characters and should be properly truncated to create a meaningful title while preserving the full content in the text field for the user to read.';
        $html = "<html><body><p>$longText</p></body></html>";

        $result = $this->callOdtImporterPrivateMethod('convertHtmlToParagraphStructure', [$html]);

        $this->assertCount(1, $result);
        // When there's no heading, title is generated from first part of content
        $this->assertStringContainsString('This is a very long paragraph', $result[0]['title']);
        $this->assertStringContainsString('...', $result[0]['title']);
        $this->assertLessThanOrEqual(53, strlen($result[0]['title'])); // 50 chars + "..."
        $this->assertStringContainsString($longText, $result[0]['text']);
        $this->assertEquals(0, $result[0]['nestingLevel']);
    }

    public function testConvertHtmlToParagraphStructureSkipsEmptyElements(): void
    {
        $html = '<html><body>
            <p>Content paragraph</p>
            <p></p>
            <p>   </p>
            <p>&nbsp;</p>
            <p>Another content paragraph</p>
        </body></html>';

        $result = $this->callOdtImporterPrivateMethod('convertHtmlToParagraphStructure', [$html]);

        // Should create one paragraph with all non-empty content
        $this->assertCount(1, $result);
        // Title is generated from the combined text content
        $this->assertStringStartsWith('Content paragraph', $result[0]['title']);
        $this->assertStringContainsString('<p>Content paragraph</p>', $result[0]['text']);
        $this->assertStringContainsString('<p>Another content paragraph</p>', $result[0]['text']);
        $this->assertEquals(0, $result[0]['nestingLevel']);
    }

    public function testConvertHtmlToParagraphStructureWithMultipleSentences(): void
    {
        $html = '<html><body>
            <p>First sentence here. Second sentence continues. Third sentence ends it.</p>
        </body></html>';

        $result = $this->callOdtImporterPrivateMethod('convertHtmlToParagraphStructure', [$html]);

        $this->assertCount(1, $result);
        // When no heading, title is generated from first sentence
        $this->assertEquals('First sentence here.', $result[0]['title']);
        $this->assertStringContainsString('First sentence here. Second sentence continues. Third sentence ends it.', $result[0]['text']);
        $this->assertEquals(0, $result[0]['nestingLevel']);
    }

    public function testOdtImporterProducesExpectedHtmlFromSimpleDoc(): void
    {
        // Test that the ODT importer produces the expected HTML from the example file
        $odtFilePath = __DIR__ . '/res/SimpleDoc.odt';
        $this->assertFileExists($odtFilePath, 'SimpleDoc.odt test file should exist');
        
        $odtImporter = new OdtImporter();
        $actualHtml = $odtImporter->convert($odtFilePath);
        
        // The expected HTML structure that should be produced by the ODT importer
        $expectedHtmlContent = [
            '<h1>Testüberschrift</h1>',
            '<p>Mein <strong>fetter</strong> Absatz<sup title="Erste Fußnote im Fließtext">1</sup>',
            '<em><u>kursiv-unterstrichener</em></u>',
            '<sup title="Ich bin die Fußnote">2</sup>',
            '<td colspan="2" >Colspan2</td>',
            '<td rowspan="3" >Rowspan3</td>',
            '<h2>Überschrift2</h2>',
            '<ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul>',
            '<strong>Abbildung </strong><strong>1</strong><strong> Ich bin die Superblume</strong>',
            '<h3>Überschrift3</h3>',
            '<sup title="Mit Fußnote auf <strong>neuer</strong> Seite">3</sup>',
            '<ul><li>Eins</li><li>Zwei</li></ul>',
            '<h4>Nummerierte Überschrift</h4>',
            '<ol><li>Nummerierten Liste 1</li><li>Nummer 2</li>',
            '<sup title="Und Endnote">I</sup>',
            '<ul><li>Jetzt</li><li><strong>F</strong><strong>ett</strong></li>',
            'Mit einem Absatz am Ende.'
        ];
        
        // Verify that the HTML contains all expected elements
        foreach ($expectedHtmlContent as $expectedElement) {
            $this->assertStringContainsString($expectedElement, $actualHtml, 
                "Expected HTML element not found: $expectedElement"
            );
        }
        
        // Verify that all 4 headings are present
        $this->assertStringContainsString('<h1>Testüberschrift</h1>', $actualHtml);
        $this->assertStringContainsString('<h2>Überschrift2</h2>', $actualHtml);
        $this->assertStringContainsString('<h3>Überschrift3</h3>', $actualHtml);
        $this->assertStringContainsString('<h4>Nummerierte Überschrift</h4>', $actualHtml);
        
        // Store the actual HTML for debugging purposes
        echo "\n=== ACTUAL HTML OUTPUT FROM ODT IMPORTER ===\n";
        echo $actualHtml;
        echo "\n=== END ACTUAL HTML OUTPUT ===\n";
    }

    public function testConvertHtmlToParagraphStructureWithExpectedOdtOutput(): void
    {
        // This is the expected HTML output from the ODT importer for SimpleDoc.odt
        $expectedOdtHtml = '<html><body>
            <h1>Testüberschrift</h1>
            <p></p><p>Mein <strong>fetter</strong> Absatz<sup title="Erste Fußnote im Fließtext">1</sup> mit <em><u>kursiv-unterstrichener</em></u> Fußnote<sup title="Ich bin die Fußnote">2</sup></p><table>
<tr>
<td colspan="2" >Colspan2</td><td>1.3</td></tr>
<tr>
<td>1.1</td><td>1.2</td><td rowspan="3" >Rowspan3</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
<tr>
<td>3.1</td><td>3.2</td></tr>
<tr>
<td>4.1</td><td>4.2</td><td>4.3</td></tr>
</table>
<p></p><table>
<tr>
<td>1.1</td><td colspan="2" >Colspan2</td></tr>
<tr>
<td rowspan="2" >Rowspan2 first</td><td>1.2</td><td rowspan="3" >Rowspan3</td></tr>
<tr>
<td>2.2</td></tr>
<tr>
<td>3.1</td><td>3.2</td></tr>
<tr>
<td>4.1</td><td>4.2</td><td>4.3</td></tr>
</table>
<p></p>
            <h2>Überschrift2</h2>
            <p>Mit Absatz</p><ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul><p>Mit Absatz dahinter, Tabelle folgend</p><table>
<tr>
<td>1.1</td><td>1.2</td><td>1.3</td></tr>
<tr>
<td>2.1</td><td>2.2</td><td>2.3</td></tr>
</table>
<p></p><table>
<tr>
<td><ul><li>Liste</li><li>In</li><li>Tabelle</li><li><strong>fett</strong></li></ul></td><td>1.2 fett</td><td>1.3</td></tr>
<tr>
<td>2.1</td><td>Tabelle in Tabelle    2.2.1.12.2.1.22.2.2.12.2.2.2
</td><td>2.3</td></tr>
</table>
<p></p><p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan="2" >Colspan2</td><td rowspan="2" >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p><img src="/app_dev.php/file/fc48c66c-560f-4aff-96a4-45b524cbb1ae/db18df1d-b0b4-4955-890e-f71b01860d91" width="337" height="252"></p><p><strong>Abbildung </strong><strong>1</strong><strong> Ich bin die Superblume</strong></p>
            <h3>Überschrift3</h3>
            <p>Zweiter Absatz<sup title="Mit Fußnote auf <strong>neuer</strong> Seite">3</sup> mit Liste ohne Absatz dahinter</p><ul><li>Eins</li><li>Zwei</li></ul><p></p>
            <h4>Nummerierte Überschrift</h4>
            <p>Mit einer</p><ol><li>Nummerierten Liste 1</li><li>Nummer 2</li><li>Nummer 2.1</li><li>Nummer 2.2</li><li>Nummer 3</li></ol><p>Mit Absatz<sup title="Und Endnote">I</sup> dahinter</p><p>Fast Ende mit Liste</p><ul><li>Jetzt</li><li><strong>F</strong><strong>ett</strong></li><li><strong>eingerückt</strong></li><li>Schlüß </li></ul><p>Mit einem Absatz am Ende.</p>
        </body></html>';

        $result = $this->callOdtImporterPrivateMethod('convertHtmlToParagraphStructure', [$expectedOdtHtml]);

        // Should have exactly 4 paragraphs
        $this->assertCount(4, $result, 'Should produce exactly 4 paragraphs from the ODT content');
        
        // Verify paragraph 1: Testüberschrift
        $this->assertEquals('Testüberschrift', $result[0]['title']);
        $this->assertEquals(1, $result[0]['nestingLevel']);
        $this->assertStringContainsString('<p>Mein <strong>fetter</strong> Absatz<sup title="Erste Fußnote im Fließtext">1</sup>', $result[0]['text']);
        $this->assertStringContainsString('<td colspan="2" >Colspan2</td>', $result[0]['text']);
        $this->assertStringContainsString('<td rowspan="3" >Rowspan3</td>', $result[0]['text']);
        
        // Verify paragraph 2: Überschrift2
        $this->assertEquals('Überschrift2', $result[1]['title']);
        $this->assertEquals(2, $result[1]['nestingLevel']);
        $this->assertStringContainsString('<p>Mit Absatz</p>', $result[1]['text']);
        $this->assertStringContainsString('<ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul>', $result[1]['text']);
        $this->assertStringContainsString('<strong>Abbildung </strong><strong>1</strong><strong> Ich bin die Superblume</strong>', $result[1]['text']);
        
        // Verify paragraph 3: Überschrift3
        $this->assertEquals('Überschrift3', $result[2]['title']);
        $this->assertEquals(3, $result[2]['nestingLevel']);
        $this->assertStringContainsString('<p>Zweiter Absatz<sup title="Mit Fußnote auf &lt;strong&gt;neuer&lt;/strong&gt; Seite">3</sup>', $result[2]['text']);
        $this->assertStringContainsString('<ul><li>Eins</li><li>Zwei</li></ul>', $result[2]['text']);
        
        // Verify paragraph 4: Nummerierte Überschrift
        $this->assertEquals('Nummerierte Überschrift', $result[3]['title']);
        $this->assertEquals(4, $result[3]['nestingLevel']);
        $this->assertStringContainsString('<ol><li>Nummerierten Liste 1</li><li>Nummer 2</li>', $result[3]['text']);
        $this->assertStringContainsString('<sup title="Und Endnote">I</sup>', $result[3]['text']);
        $this->assertStringContainsString('<ul><li>Jetzt</li><li><strong>F</strong><strong>ett</strong></li>', $result[3]['text']);
        $this->assertStringContainsString('Mit einem Absatz am Ende.', $result[3]['text']);
        
        // Verify that the expected content structure is present
        $this->assertStringContainsString('<p></p><p>Mein <strong>fetter</strong> Absatz<sup title="Erste Fußnote im Fließtext">1</sup> mit <em><u>kursiv-unterstrichener</em></u> Fußnote<sup title="Ich bin die Fußnote">2</sup></p>', $result[0]['text']);
        $this->assertStringContainsString('<p>Mit Absatz</p><ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul>', $result[1]['text']);
        $this->assertStringContainsString('<p>Zweiter Absatz<sup title="Mit Fußnote auf &lt;strong&gt;neuer&lt;/strong&gt; Seite">3</sup> mit Liste ohne Absatz dahinter</p>', $result[2]['text']);
        $this->assertStringContainsString('<p>Mit einer</p><ol><li>Nummerierten Liste 1</li><li>Nummer 2</li><li>Nummer 2.1</li><li>Nummer 2.2</li><li>Nummer 3</li></ol>', $result[3]['text']);
    }


    public function testFullOdtConversionWorkflow(): void
    {
        // Test the complete workflow with a realistic ODT HTML structure
        $file = $this->createMock(File::class);
        $file->method('getRealPath')->willReturn('/tmp/test.odt');

        // Mock the ODT importer to return realistic HTML matching actual ODT output
        $realisticOdtHtml = '<html><body>
            <h1>Testüberschrift</h1>
            <p></p><p>Mein <strong>fetter</strong> Absatz<sup title=\'Erste Fußnote im Fließtext\'>1</sup> mit <em><u>kursiv-unterstrichener</em></u> Fußnote<sup title=\'Ich bin die Fußnote\'>2</sup></p><table>
<tr>
<td colspan=\'2\' >Colspan2</td><td>1.3</td></tr>
<tr>
<td>1.1</td><td>1.2</td><td rowspan=\'3\' >Rowspan3</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
<tr>
<td>3.1</td><td>3.2</td></tr>
<tr>
<td>4.1</td><td>4.2</td><td>4.3</td></tr>
</table>
<p></p><table>
<tr>
<td>1.1</td><td colspan=\'2\' >Colspan2</td></tr>
<tr>
<td rowspan=\'2\' >Rowspan2 first</td><td>1.2</td><td rowspan=\'3\' >Rowspan3</td></tr>
<tr>
<td>2.2</td></tr>
<tr>
<td>3.1</td><td>3.2</td></tr>
<tr>
<td>4.1</td><td>4.2</td><td>4.3</td></tr>
</table>
<p></p>
            <h2>Überschrift2</h2>
            <p>Mit Absatz</p><ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul><p>Mit Absatz dahinter, Tabelle folgend</p><table>
<tr>
<td>1.1</td><td>1.2</td><td>1.3</td></tr>
<tr>
<td>2.1</td><td>2.2</td><td>2.3</td></tr>
</table>
<p></p><table>
<tr>
<td><ul><li>Liste</li><li>In</li><li>Tabelle</li><li><strong>fett</strong></li></ul></td><td>1.2 fett</td><td>1.3</td></tr>
<tr>
<td>2.1</td><td>Tabelle in Tabelle    2.2.1.12.2.1.22.2.2.12.2.2.2
</td><td>2.3</td></tr>
</table>
<p></p><p>Dann eine komplexe Tabelle</p><table>
<tr>
<td colspan=\'2\' >Colspan2</td><td rowspan=\'2\' >Rowspan2</td></tr>
<tr>
<td>2.1</td><td>2.2</td></tr>
</table>
<p></p><p>Sodann ein Bild</p><p><img src=\'/app_dev.php/file/fc48c66c-560f-4aff-96a4-45b524cbb1ae/fcf9ee47-13fa-43c2-8cd6-9731ab1212fc\' width=\'337\' height=\'252\'></p><p><strong>Abbildung </strong><strong>1</strong><strong> Ich bin die Superblume</strong></p>
            <h3>Überschrift3</h3>
            <p>Zweiter Absatz<sup title=\'Mit Fußnote auf <strong>neuer</strong> Seite\'>3</sup> mit Liste ohne Absatz dahinter</p><ul><li>Eins</li><li>Zwei</li></ul><p></p>
            <h4>Nummerierte Überschrift</h4>
            <p>Mit einer</p><ol><li>Nummerierten Liste 1</li><li>Nummer 2</li><li>Nummer 2.1</li><li>Nummer 2.2</li><li>Nummer 3</li></ol><p>Mit Absatz<sup title=\'Und Endnote\'>I</sup> dahinter</p><p>Fast Ende mit Liste</p><ul><li>Jetzt</li><li><strong>F</strong><strong>ett</strong></li><li><strong>eingerückt</strong></li><li>Schlüß </li></ul><p>Mit einem Absatz am Ende.</p>
        </body></html>';

        // Create a mocked OdtImporter for this test
        $odtImporter = $this->createMock(OdtImporter::class);
        $odtImporter->method('convert')->willReturn($realisticOdtHtml);
        
        // Mock the new importOdt method to return the expected structure
        $odtImporter->method('importOdt')->willReturnCallback(function($file, $elementId, $procedure, $category) use ($realisticOdtHtml) {
            // Use the real OdtImporter to convert HTML to paragraph structure
            $realOdtImporter = new OdtImporter();
            $reflection = new ReflectionClass($realOdtImporter);
            $method = $reflection->getMethod('convertHtmlToParagraphStructure');
            $method->setAccessible(true);
            $paragraphs = $method->invokeArgs($realOdtImporter, [$realisticOdtHtml]);
            
            return [
                'procedure' => $procedure,
                'category' => $category,
                'elementId' => $elementId,
                'path' => $file->getRealPath(),
                'paragraphs' => $paragraphs,
            ];
        });

        // Create ServiceImporter with mocked ODT importer
        $serviceImporter = new ServiceImporter(
            $this->createMock(DocxImporterInterface::class),
            $odtImporter,
            $this->createMock(FileService::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(GlobalConfigInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBagInterface::class),
            $this->createMock(ParagraphRepository::class),
            $this->createMock(ParagraphService::class),
            $this->createMock(PdfCreatorInterface::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(RpcClient::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        $result = $serviceImporter->importOdtFile($file, 'element123', 'procedure456', 'paragraph');

        $this->assertEquals('procedure456', $result['procedure']);
        $this->assertEquals('paragraph', $result['category']);
        $this->assertEquals('element123', $result['elementId']);
        $this->assertArrayHasKey('paragraphs', $result);

        $paragraphs = $result['paragraphs'];
        $this->assertCount(4, $paragraphs);

        // Verify the 4 expected paragraphs
        $this->assertEquals('Testüberschrift', $paragraphs[0]['title']);
        $this->assertEquals(1, $paragraphs[0]['nestingLevel']);
        $this->assertStringContainsString('<p>Mein <strong>fetter</strong> Absatz', $paragraphs[0]['text']);
        $this->assertStringContainsString('<sup title="Erste Fußnote im Fließtext">1</sup>', $paragraphs[0]['text']);
        $this->assertStringContainsString('<table>', $paragraphs[0]['text']);
        $this->assertStringContainsString('Colspan2', $paragraphs[0]['text']);

        $this->assertEquals('Überschrift2', $paragraphs[1]['title']);
        $this->assertEquals(2, $paragraphs[1]['nestingLevel']);
        $this->assertStringContainsString('<p>Mit Absatz</p>', $paragraphs[1]['text']);
        $this->assertStringContainsString('<ul><li>Erster Listpunkt</li><li>Zweiter Listpunkt</li></ul>', $paragraphs[1]['text']);
        $this->assertStringContainsString('Sodann ein Bild', $paragraphs[1]['text']);
        // The image paragraph content is there even if img tag processing varies
        $this->assertStringContainsString('Abbildung', $paragraphs[1]['text']);

        $this->assertEquals('Überschrift3', $paragraphs[2]['title']);
        $this->assertEquals(3, $paragraphs[2]['nestingLevel']);
        $this->assertStringContainsString('<p>Zweiter Absatz<sup title="Mit Fußnote auf &lt;strong&gt;neuer&lt;/strong&gt; Seite">3</sup>', $paragraphs[2]['text']);
        $this->assertStringContainsString('<ul><li>Eins</li><li>Zwei</li></ul>', $paragraphs[2]['text']);

        $this->assertEquals('Nummerierte Überschrift', $paragraphs[3]['title']);
        $this->assertEquals(4, $paragraphs[3]['nestingLevel']);
        $this->assertStringContainsString('<ol><li>Nummerierten Liste 1</li>', $paragraphs[3]['text']);
        $this->assertStringContainsString('<sup title="Und Endnote">I</sup>', $paragraphs[3]['text']);
        $this->assertStringContainsString('Mit einem Absatz am Ende.', $paragraphs[3]['text']);
    }

    private function callPrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->serviceImporter);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->serviceImporter, $args);
    }

    private function callOdtImporterPrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->odtImporter);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->odtImporter, $args);
    }
}