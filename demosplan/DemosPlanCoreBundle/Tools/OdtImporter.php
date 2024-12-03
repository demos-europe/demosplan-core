<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use DOMDocument;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class OdtImporter
{
    private string $odtFilePath;

    public function convert(string $odtFilePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($odtFilePath) === true) {
            // save path as property to be used later
            $this->odtFilePath = $odtFilePath;
            $contentXml = $zip->getFromName('content.xml');
            //extract all pictures to a temporary folder
            $zip->extractTo(dirname($odtFilePath) . '/tmp');
            $zip->close();

            $html = '';
            if ($contentXml !== false) {
                $html = $this->transformToHtml($contentXml);
            }
            $fs = new Filesystem();
            $fs->remove(dirname($odtFilePath) . '/tmp');

            return $html;
        }

        throw new \Exception('Unable to open ODT file.');
    }

    private function transformToHtml(string $contentXml): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($contentXml);

        $html = '<html><body>';
        $html .= $this->processNodes($dom->documentElement);
        $html .= '</body></html>';

        return $html;
    }

    private function processNodes(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                switch ($child->nodeName) {
                    case 'text:p':
                        $html .= '<p>' . $this->processNodes($child) . '</p>';
                        break;
                    case 'text:h':
                        $html .= '<h1>' . $this->processNodes($child) . '</h1>';
                        break;
                    case 'table:table':
                        $html .= '<table>' . $this->processNodes($child) . '</table>';
                        break;
                    case 'table:table-row':
                        $html .= '<tr>' . $this->processNodes($child) . '</tr>';
                        break;
                    case 'table:table-cell':
                        $html .= '<td>' . $this->processNodes($child) . '</td>';
                        break;
                    case 'draw:image':
                        $html .= $this->processImage($child);
                        break;
                    default:
                        $html .= $this->processNodes($child);
                        break;
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->nodeValue);
            }
        }

        return $html;
    }

    private function processImage(\DOMNode $node): string
    {
        $xlinkHref = $node->getAttribute('xlink:href');
        if ($xlinkHref) {
            $imagePath = dirname($this->odtFilePath) . '/tmp/' . $xlinkHref;
            if (file_exists($imagePath)) {
                $imageData = base64_encode(file_get_contents($imagePath));
                $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
                return '<img src="data:image/' . $imageType . ';base64,' . $imageData . '" />';
            }
        }
        return '';
    }
}
