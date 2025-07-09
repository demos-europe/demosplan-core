<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use DOMDocument;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class OdtImporter
{
    private string $odtFilePath;
    private ?ZipArchive $zipArchive;
    private array $styleMap = [];

    public function __construct(?ZipArchive $zipArchive = null)
    {
        $this->zipArchive = $zipArchive;
    }

    public function convert(string $odtFilePath): string
    {
        $zip = $this->zipArchive ?? new ZipArchive();
        if ($zip->open($odtFilePath) === true) {
            // save path as property to be used later
            $this->odtFilePath = $odtFilePath;
            $contentXml = $zip->getFromName('content.xml');
            //extract all pictures to a temporary folder
            if ($this->zipArchive === null) {
                $zip->extractTo(dirname($odtFilePath) . '/tmp');
            }
            $zip->close();

            $html = '';
            if ($contentXml !== false) {
                $html = $this->transformToHtml($contentXml);
            }
            if ($this->zipArchive === null) {
                $fs = new Filesystem();
                $fs->remove(dirname($odtFilePath) . '/tmp');
            }

            return $html;
        }

        throw new \Exception('Unable to open ODT file.');
    }

    private function transformToHtml(string $contentXml): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($contentXml);

        // Parse styles first to understand formatting
        $this->parseStyles($dom);

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
                        $html .= $this->processHeading($child);
                        break;
                    case 'table:table':
                        $html .= '<table>' . $this->processNodes($child) . '</table>';
                        break;
                    case 'table:table-row':
                        $html .= '<tr>' . $this->processNodes($child) . '</tr>';
                        break;
                    case 'table:table-cell':
                        $html .= $this->processTableCell($child);
                        break;
                    case 'table:covered-table-cell':
                        // Skip covered cells - they're handled by spanning
                        break;
                    case 'text:list':
                        $html .= $this->processList($child);
                        break;
                    case 'text:list-item':
                        $html .= '<li>' . $this->processNodes($child) . '</li>';
                        break;
                    case 'text:span':
                        $html .= $this->processSpan($child);
                        break;
                    case 'text:note':
                        $html .= $this->processNote($child);
                        break;
                    case 'text:soft-page-break':
                        $html .= '<hr class="page-break">';
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

    private function processHeading(\DOMNode $node): string
    {
        $level = $node->getAttribute('text:outline-level') ?: '1';
        $level = min(6, max(1, (int) $level)); // Ensure level is between 1-6
        return '<h' . $level . '>' . $this->processNodes($node) . '</h' . $level . '>';
    }

    private function processTableCell(\DOMNode $node): string
    {
        $attributes = '';

        // Handle column spanning
        $colspan = $node->getAttribute('table:number-columns-spanned');
        if ($colspan && $colspan > 1) {
            $attributes .= ' colspan="' . $colspan . '"';
        }

        // Handle row spanning
        $rowspan = $node->getAttribute('table:number-rows-spanned');
        if ($rowspan && $rowspan > 1) {
            $attributes .= ' rowspan="' . $rowspan . '"';
        }

        return '<td' . $attributes . '>' . $this->processNodes($node) . '</td>';
    }

    private function processList(\DOMNode $node): string
    {
        // Simple heuristic: if it contains numbers, make it ordered
        $listContent = $node->textContent;
        $isOrdered = preg_match('/^\s*\d+\./', $listContent) ||
            str_contains($node->getAttribute('text:style-name'), 'Num');

        $tag = $isOrdered ? 'ol' : 'ul';
        return '<' . $tag . '>' . $this->processNodes($node) . '</' . $tag . '>';
    }

    private function parseStyles(\DOMDocument $dom): void
    {
        $this->styleMap = [];

        $xpath = new \DOMXPath($dom);
        $styleNodes = $xpath->query('//office:automatic-styles/style:style[@style:family="text"]');

        foreach ($styleNodes as $styleNode) {
            $styleName = $styleNode->getAttribute('style:name');
            if (empty($styleName)) {
                continue;
            }

            $format = $this->extractTextFormat($xpath, $styleNode);
            if (!empty($format)) {
                $this->styleMap[$styleName] = $format;
            }
        }
    }

    private function extractTextFormat(\DOMXPath $xpath, \DOMElement $styleNode): array
    {
        $properties = $xpath->query('style:text-properties', $styleNode);
        if ($properties->length === 0) {
            return [];
        }

        $textProps = $properties->item(0);
        if ($textProps === null) {
            return [];
        }

        $format = [];

        if ($this->isBold($textProps)) {
            $format['bold'] = true;
        }

        if ($this->isItalic($textProps)) {
            $format['italic'] = true;
        }

        if ($this->isUnderlined($textProps)) {
            $format['underline'] = true;
        }

        return $format;
    }

    private function isBold(\DOMElement $textProps): bool
    {
        return $textProps->getAttribute('fo:font-weight') === 'bold' ||
               $textProps->getAttribute('style:font-weight-asian') === 'bold';
    }

    private function isItalic(\DOMElement $textProps): bool
    {
        return $textProps->getAttribute('fo:font-style') === 'italic' ||
               $textProps->getAttribute('style:font-style-asian') === 'italic';
    }

    private function isUnderlined(\DOMElement $textProps): bool
    {
        return $textProps->getAttribute('style:text-underline-style') === 'solid';
    }

    private function processSpan(\DOMNode $node): string
    {
        $styleName = $node->getAttribute('text:style-name');
        $content = $this->processNodes($node);

        // If we have no style information, return content as-is
        if (!$styleName || !isset($this->styleMap[$styleName])) {
            return $content;
        }

        $format = $this->styleMap[$styleName];

        // Apply formatting based on parsed style properties
        if (isset($format['bold'])) {
            $content = '<strong>' . $content . '</strong>';
        }

        if (isset($format['italic'])) {
            $content = '<em>' . $content . '</em>';
        }

        if (isset($format['underline'])) {
            $content = '<u>' . $content . '</u>';
        }

        return $content;
    }

    private function processNote(\DOMNode $node): string
    {
        $noteClass = $node->getAttribute('text:note-class');
        $citation = '';
        $body = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'text:note-citation') {
                $citation = $child->textContent;
            } elseif ($child->nodeName === 'text:note-body') {
                $body = $this->processNodes($child);
            }
        }

        $type = ($noteClass === 'endnote') ? 'endnote' : 'footnote';
        return '<sup class="' . $type . '" title="' . htmlspecialchars(strip_tags($body)) . '">' . $citation . '</sup>';
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
