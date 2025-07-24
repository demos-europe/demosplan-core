<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools;

use DOMDocument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use ZipArchive;

class OdtImporter
{
    private string $odtFilePath;
    private ?ZipArchive $zipArchive;
    private array $styleMap = [];
    private array $listStyleMap = [];
    private array $headingStyleMap = [];
    private array $listContinuation = [];  // Maps list IDs to their continuation relationships
    private array $listCounters = [];      // Tracks current count for each list sequence

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
            $stylesXml = $zip->getFromName('styles.xml');
            //extract all pictures to a temporary folder
            if ($this->zipArchive === null) {
                $zip->extractTo(dirname($odtFilePath) . '/tmp');
            }
            $zip->close();

            $html = '';
            if ($contentXml !== false) {
                $html = $this->transformToHtml($contentXml, $stylesXml);
            }
            if ($this->zipArchive === null) {
                $fs = new Filesystem();
                $fs->remove(dirname($odtFilePath) . '/tmp');
            }

            return $html;
        }

        throw new \Exception('Unable to open ODT file.');
    }

    private function transformToHtml(string $contentXml, ?string $stylesXml = null): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($contentXml);

        // Parse styles first to understand formatting
        $this->parseStyles($dom);
        
        // Parse list styles from styles.xml to determine ordered vs unordered
        if ($stylesXml !== null && $stylesXml !== false) {
            $this->parseListStyles($stylesXml);
        }

        $html = '<html><body>';
        $html .= $this->processNodes($dom->documentElement);
        $html .= '</body></html>';

        // Clean up structural issues that may come from ODT
        $html = $this->cleanupStructuralIssues($html);

        return $html;
    }

    private function processNodes(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                switch ($child->nodeName) {
                    case 'text:p':
                        $html .= $this->processParagraph($child);
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
                        $content = $this->processNodes($child);
                        // Remove paragraph wrappers from list items but preserve other block elements
                        // Handle case where list item starts with a paragraph followed by other elements
                        $content = preg_replace('/^<p>(.*?)<\/p>(.*)$/s', '$1$2', trim($content));
                        // Also handle case where entire content is just a single paragraph
                        $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($content));
                        // Remove page breaks from list items as they're not meaningful in this context
                        $content = str_replace('<hr class="page-break">', '', $content);
                        $html .= '<li>' . $content . '</li>';
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
                    case 'draw:frame':
                        $html .= $this->processFrame($child);
                        break;
                    case 'text:s':
                        $html .= ' '; // Space element
                        break;
                    case 'text:tab':
                        $html .= ' '; // Convert tab to space for readability
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

        $content = $this->processNodes($node);

        // Remove paragraph wrappers from table cells to match expected format
        $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($content));

        return '<td' . $attributes . ' >' . $content . '</td>';
    }

    private function processList(\DOMNode $node): string
    {
        // Parse list attributes for continuation support
        $styleName = $node->getAttribute('text:style-name');
        $listType = $node->getAttribute('text:list-type');
        $continuesList = $node->getAttribute('text:continue-list');
        $listId = $node->getAttribute('xml:id') ?: uniqid('list_');
        
        // Determine if this is an ordered or unordered list using dynamic style detection
        $isOrdered = $this->getListType($styleName, $listType);
        
        if ($isOrdered) {
            // Handle list continuation for ordered lists
            $startValue = $this->getListStartValue($listId, $continuesList);
            $tag = 'ol';
            $attributes = $startValue > 1 ? ' start="' . $startValue . '"' : '';
        } else {
            // Unordered lists don't need continuation handling
            $tag = 'ul';
            $attributes = '';
        }
        
        // Process list content
        $content = $this->processNodes($node);
        
        // Update counters for continued lists
        if ($isOrdered) {
            $this->updateListCounters($listId, $content);
        }
        
        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    }

    /**
     * Determine if a list is ordered or unordered based on style analysis.
     * This method trusts the dynamic parseListStyles() analysis completely.
     */
    private function getListType(string $styleName, string $listType): bool
    {
        // First check if we have parsed style information from styles.xml
        if (!empty($styleName) && isset($this->listStyleMap[$styleName])) {
            return $this->listStyleMap[$styleName];
        }
        
        // Check for explicit list type attributes as fallback
        if ($listType === 'numbered' || $listType === 'ordered') {
            return true;
        }
        
        // Default to unordered - most lists should be bullet lists
        return false;
    }

    /**
     * Calculate the starting value for a list, handling continuation from previous lists.
     */
    private function getListStartValue(string $listId, string $continuesList): int
    {
        if (!empty($continuesList)) {
            // This list continues from another list
            $this->listContinuation[$listId] = $continuesList;
            
            // Find the current count of the list we're continuing from
            $parentCount = $this->listCounters[$continuesList] ?? 0;
            $startValue = $parentCount + 1;
            
            // Initialize this list's counter to continue the sequence
            $this->listCounters[$listId] = $parentCount;
            
            return $startValue;
        } else {
            // This is a new list sequence
            $this->listCounters[$listId] = 0;
            return 1;
        }
    }

    /**
     * Update list counters based on the number of list items processed.
     */
    private function updateListCounters(string $listId, string $content): void
    {
        // Count the number of <li> elements in the processed content
        $itemCount = substr_count($content, '<li>');
        
        if ($itemCount > 0) {
            // Update the counter for this list
            $this->listCounters[$listId] = ($this->listCounters[$listId] ?? 0) + $itemCount;
            
            // If this list continues from another, also update the parent counter
            $continuesFrom = $this->listContinuation[$listId] ?? null;
            if ($continuesFrom !== null) {
                $this->listCounters[$continuesFrom] = $this->listCounters[$listId];
            }
        }
    }

    /**
     * Clean up structural issues that may come from ODT conversion.
     * This fixes problems like headings nested inside list items.
     */
    private function cleanupStructuralIssues(string $html): string
    {
        // Fix headings nested inside list items (this is the main structural issue)
        // Pattern: <ol><li><h2>Heading</h2></li></ol> -> <h2>Heading</h2>
        $html = preg_replace(
            '/<ol[^>]*>\s*<li[^>]*>\s*(<h[1-6][^>]*>.*?<\/h[1-6]>)\s*<\/li>\s*<\/ol>/s',
            '$1',
            $html
        );
        
        // Also handle unordered lists with headings (just in case)
        $html = preg_replace(
            '/<ul[^>]*>\s*<li[^>]*>\s*(<h[1-6][^>]*>.*?<\/h[1-6]>)\s*<\/li>\s*<\/ul>/s',
            '$1',
            $html
        );

        // Note: Disabled automatic numbering removal to preserve structured headings like "2.1 Küstenmeer"
        // This was previously removing numbering patterns but we want to keep them for Zwischenüberschriften

        return $html;
    }

    private function parseStyles(\DOMDocument $dom): void
    {
        $this->styleMap = [];
        $this->headingStyleMap = [];

        $xpath = new \DOMXPath($dom);
        
        // Parse text styles
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
        
        // Parse paragraph styles for heading detection
        $paragraphStyles = $xpath->query('//office:automatic-styles/style:style[@style:family="paragraph"]');
        foreach ($paragraphStyles as $styleNode) {
            $styleName = $styleNode->getAttribute('style:name');
            if (empty($styleName)) {
                continue;
            }
            
            $headingLevel = $this->extractHeadingLevel($xpath, $styleNode);
            if ($headingLevel > 0) {
                $this->headingStyleMap[$styleName] = $headingLevel;
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

    /**
     * Extract heading level from paragraph style definition.
     */
    private function extractHeadingLevel(\DOMXPath $xpath, \DOMElement $styleNode): int
    {
        // Check if parent style indicates heading
        $parentStyleName = $styleNode->getAttribute('style:parent-style-name');
        if ($parentStyleName && str_contains($parentStyleName, 'Heading')) {
            // Extract level from parent style name like "Heading_1", "Heading_2"
            if (preg_match('/Heading[_\s]*(\d+)/', $parentStyleName, $matches)) {
                return (int) $matches[1];
            }
            return 1; // Default to level 1 for generic heading styles
        }
        
        // Check text properties for heading-like formatting
        $textProps = $xpath->query('style:text-properties', $styleNode)->item(0);
        if ($textProps) {
            $isBold = $this->isBold($textProps);
            $fontSize = $textProps->getAttribute('fo:font-size');
            
            // Detect heading based on bold + large font size
            if ($isBold && $fontSize) {
                $size = (int) filter_var($fontSize, FILTER_SANITIZE_NUMBER_INT);
                if ($size >= 14) {
                    return 1; // Large bold text likely a heading
                }
            }
        }
        
        return 0; // Not a heading
    }

    /**
     * Parse list styles from styles.xml to determine if lists are ordered or unordered.
     * This implements ODT specification-compliant list type detection.
     */
    private function parseListStyles(string $stylesXml): void
    {
        $this->listStyleMap = [];
        
        $stylesDom = new DOMDocument();
        $stylesDom->loadXML($stylesXml);
        $xpath = new \DOMXPath($stylesDom);
        
        // Register namespaces for ODT
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
        
        // Find all list style definitions
        $listStyles = $xpath->query('//text:list-style');
        
        foreach ($listStyles as $listStyle) {
            $styleName = $listStyle->getAttribute('style:name');
            if (empty($styleName)) {
                continue;
            }
            
            // Check the first level to determine if it's ordered or unordered
            // According to ODT spec: text:list-level-style-number = ordered, text:list-level-style-bullet = unordered
            $firstLevel = $xpath->query('text:list-level-style-number | text:list-level-style-bullet', $listStyle)->item(0);
            
            if ($firstLevel !== null) {
                $isOrdered = false;
                
                if ($firstLevel->nodeName === 'text:list-level-style-number') {
                    // Number-based lists are ordered - check for valid numbering format
                    $numFormat = $firstLevel->getAttribute('style:num-format');
                    // Any non-empty num-format indicates a numbered list (1, a, A, i, I, etc.)
                    // Empty num-format means no numbering (like "No List (WW)" style)
                    if (!empty($numFormat) && $numFormat !== '') {
                        $isOrdered = true;
                    }
                } elseif ($firstLevel->nodeName === 'text:list-level-style-bullet') {
                    // Bullet lists are unordered (any bullet character or symbol)
                    $isOrdered = false;
                }
                
                $this->listStyleMap[$styleName] = $isOrdered;
            }
        }
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

        // If content is empty, don't apply any formatting to avoid empty tags
        if (trim($content) === '') {
            return $content;
        }

        // If we have no style information, return content as-is
        if (!$styleName || !isset($this->styleMap[$styleName])) {
            return $content;
        }

        $format = $this->styleMap[$styleName];

        // Apply formatting based on parsed style properties in the expected order
        if (isset($format['bold'])) {
            $content = '<strong>' . $content . '</strong>';
        }

        // Apply underline first, then italic to get the expected nesting
        if (isset($format['underline'])) {
            $content = '<u>' . $content . '</u>';
        }

        if (isset($format['italic'])) {
            $content = '<em>' . $content . '</em>';
        }

        return $content;
    }

    private function processNote(\DOMNode $node): string
    {
        $citation = '';
        $body = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'text:note-citation') {
                $citation = $child->textContent;
            } elseif ($child->nodeName === 'text:note-body') {
                $body = $this->processNodes($child);
            }
        }

        // Remove paragraph wrappers from footnote body but preserve other formatting
        $cleanBody = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($body));
        // Strip HTML tags from footnote content for title attribute (title should be plain text)
        $cleanBody = strip_tags($cleanBody);
        // Trim leading and trailing whitespace from the cleaned body for title attribute
        $cleanBody = trim($cleanBody);
        return '<sup title="' . htmlspecialchars($cleanBody) . '">' . $citation . '</sup>';
    }

    private function processImage(\DOMNode $node): string
    {
        $xlinkHref = $node->getAttribute('xlink:href');
        if ($xlinkHref) {
            // Try to get image directly from ZIP archive first
            $imageData = $this->getImageDataFromZip($xlinkHref);
            
            // Fallback to file system if ZIP method fails
            if ($imageData === null) {
                $imagePath = dirname($this->odtFilePath) . '/tmp/' . $xlinkHref;
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                }
            }
            
            if ($imageData !== null && $imageData !== false) {
                $base64Data = base64_encode($imageData);
                $imageType = pathinfo($xlinkHref, PATHINFO_EXTENSION);
                
                // Extract width and height attributes from parent draw:frame or draw:image
                $attributes = '';
                $width = $this->getImageDimension($node, 'svg:width');
                $height = $this->getImageDimension($node, 'svg:height');
                
                if ($width) {
                    $attributes .= ' width="' . $width . '"';
                }
                if ($height) {
                    $attributes .= ' height="' . $height . '"';
                }
                
                return '<img src="data:image/' . $imageType . ';base64,' . $base64Data . '"' . $attributes . ' />';
            }
        }
        return '';
    }

    /**
     * Get image dimension from the draw:frame parent node or the image node itself.
     */
    private function getImageDimension(\DOMNode $node, string $attributeName): ?string
    {
        // Check the image node itself first
        $dimension = $node->getAttribute($attributeName);
        if ($dimension) {
            return $this->convertOdtDimensionToPixels($dimension);
        }
        
        // Check parent draw:frame node
        $parent = $node->parentNode;
        if ($parent && $parent->nodeName === 'draw:frame') {
            $dimension = $parent->getAttribute($attributeName);
            if ($dimension) {
                return $this->convertOdtDimensionToPixels($dimension);
            }
        }
        
        return null;
    }

    /**
     * Convert ODT dimension units to pixels for HTML.
     */
    private function convertOdtDimensionToPixels(string $dimension): string
    {
        // Remove units and convert to approximate pixel values
        // ODT typically uses cm, in, pt, etc.
        if (preg_match('/^(\d+(?:\.\d+)?)(.*)$/', $dimension, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2];
            
            switch ($unit) {
                case 'cm':
                    // 1 cm ≈ 37.8 pixels (96 DPI)
                    return (string) round($value * 37.8);
                case 'in':
                    // 1 inch = 96 pixels (96 DPI)
                    return (string) round($value * 96);
                case 'pt':
                    // 1 point = 1.33 pixels (96 DPI)
                    return (string) round($value * 1.33);
                case 'mm':
                    // 1 mm ≈ 3.78 pixels (96 DPI)
                    return (string) round($value * 3.78);
                case 'px':
                    // Already in pixels
                    return (string) round($value);
                default:
                    // Assume pixels if unknown unit
                    return (string) round($value);
            }
        }
        
        // If no numeric value found, return as-is (might be a percentage or other CSS value)
        return $dimension;
    }

    /**
     * Get image data directly from the ZIP archive.
     */
    private function getImageDataFromZip(string $xlinkHref): ?string
    {
        $zip = $this->zipArchive ?? new ZipArchive();
        if ($zip->open($this->odtFilePath) === true) {
            $imageData = $zip->getFromName($xlinkHref);
            $zip->close();
            return $imageData !== false ? $imageData : null;
        }
        return null;
    }

    /**
     * Process a paragraph node, handling caption detection and heading detection.
     */
    private function processParagraph(\DOMNode $node): string
    {
        // Check if this paragraph is a caption
        if ($this->isCaption($node)) {
            // Caption paragraphs are handled by processFrame, skip standalone ones
            return '';
        }

        $content = $this->processNodes($node);
        
        // Check if this paragraph should be converted to a heading
        $headingLevel = $this->detectHeadingLevel($node, $content);
        if ($headingLevel > 0) {
            return '<h' . $headingLevel . '>' . $content . '</h' . $headingLevel . '>';
        }
        
        // Check if this paragraph contains an image frame
        if ($this->containsImageFrame($node)) {
            // Look ahead for a caption paragraph
            $caption = $this->findFollowingCaption($node);
            if ($caption) {
                // Wrap image and caption in a figure element
                return '<figure>' . $content . '<figcaption>' . $this->processNodes($caption) . '</figcaption></figure>';
            }
        }
        
        return '<p>' . $content . '</p>';
    }

    /**
     * Process a draw:frame node that contains images.
     */
    private function processFrame(\DOMNode $node): string
    {
        // Process the frame's children (typically draw:image)
        $content = $this->processNodes($node);
        
        // The frame itself doesn't add HTML structure, just processes its content
        return $content;
    }

    /**
     * Check if a paragraph is a caption based on its style.
     */
    private function isCaption(\DOMNode $node): bool
    {
        $styleName = $node->getAttribute('text:style-name');
        
        // Common caption style names in ODT files
        $captionStyles = ['caption', 'Caption', 'Figure', 'Illustration', 'Abbildung'];
        
        foreach ($captionStyles as $style) {
            if (stripos($styleName, $style) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a paragraph contains an image frame.
     */
    private function containsImageFrame(\DOMNode $node): bool
    {
        $xpath = new \DOMXPath($node->ownerDocument);
        $frames = $xpath->query('.//draw:frame[draw:image]', $node);
        return $frames->length > 0;
    }

    /**
     * Find the caption paragraph that follows an image paragraph.
     */
    private function findFollowingCaption(\DOMNode $imageNode): ?\DOMNode
    {
        $nextSibling = $imageNode->nextSibling;
        
        // Skip text nodes (whitespace) to find the next element
        while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
            $nextSibling = $nextSibling->nextSibling;
        }
        
        // Check if the next element is a caption paragraph
        if ($nextSibling && $nextSibling->nodeName === 'text:p' && $this->isCaption($nextSibling)) {
            return $nextSibling;
        }
        
        return null;
    }

    /**
     * Import ODT file and convert to paragraph structure.
     * This method provides a consistent interface matching DocxImporter.
     */
    public function importOdt(File $file, string $elementId, string $procedure, string $category): array
    {
        // Convert ODT to HTML (existing functionality)
        $html = $this->convert($file->getRealPath());

        // Convert HTML to paragraph structure (new functionality)
        $paragraphs = $this->convertHtmlToParagraphStructure($html);

        // Return same structure as DocxImporter
        return [
            'procedure' => $procedure,
            'category' => $category,
            'elementId' => $elementId,
            'path' => $file->getRealPath(),
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * Convert ODT HTML output to paragraph structure using heading-first approach.
     * This method finds all headings first, then assigns content between headings.
     */
    private function convertHtmlToParagraphStructure(string $html): array
    {
        $dom = new \DOMDocument();
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);

        // Ensure proper UTF-8 encoding by adding meta tag
        if (!str_contains($html, 'charset')) {
            $html = '<meta charset="UTF-8">' . $html;
        }
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Get all headings in document order using heading-first approach
        $headings = $this->getAllHeadingsInDocumentOrder($xpath);

        $paragraphs = [];

        // Handle content before first heading (if any)
        if (!empty($headings)) {
            $preContent = $this->getContentBeforeFirstHeading($xpath, $headings[0]);
            if (!empty(trim(strip_tags($preContent)))) {
                $paragraphs[] = $this->createParagraphFromContent($preContent, 0);
            }
        }

        // Process each heading and its content
        foreach ($headings as $i => $iValue) {
            $heading = $iValue;
            $nextHeading = $headings[$i + 1] ?? null;

            // Get content between this heading and the next
            $content = $this->getContentBetweenHeadings($heading, $nextHeading, $xpath);

            // Create paragraph with heading as title
            $paragraphs[] = [
                'title' => $heading['title'],
                'text' => $content,
                'files' => null,
                'nestingLevel' => $heading['level'],
            ];
        }

        // If no headings found, create single paragraph from all content
        if (empty($headings)) {
            $allContent = $this->getAllContent($xpath);
            if (!empty(trim(strip_tags($allContent)))) {
                $paragraphs[] = $this->createParagraphFromContent($allContent, 0);
            }
        }

        return $paragraphs;
    }

    /**
     * Find all headings in document order with their metadata.
     */
    private function getAllHeadingsInDocumentOrder(\DOMXPath $xpath): array
    {
        // Find ALL headings regardless of nesting depth
        $headingNodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

        $headings = [];
        foreach ($headingNodes as $node) {
            $level = (int) substr($node->nodeName, 1); // Extract number from h1, h2, etc.
            $title = trim(strip_tags($node->textContent));

            // Skip empty headings
            if (!empty($title)) {
                $headings[] = [
                    'node' => $node,
                    'title' => $title,
                    'level' => $level,
                ];
            }
        }

        return $headings;
    }

    /**
     * Get content before the first heading.
     */
    private function getContentBeforeFirstHeading(\DOMXPath $xpath, array $firstHeading): string
    {
        $firstHeadingNode = $firstHeading['node'];
        $bodyChildren = $xpath->query('//body/*');

        $content = '';
        foreach ($bodyChildren as $node) {
            // Stop when we reach the first heading or its container
            if ($node->isSameNode($firstHeadingNode) || $this->nodeContainsHeading($node, $firstHeadingNode)) {
                // If the container contains the heading, extract content before it
                if ($this->nodeContainsHeading($node, $firstHeadingNode)) {
                    $content .= $this->extractContentBeforeHeading($node, $firstHeadingNode);
                }
                break;
            }

            // Add this node's HTML to content
            $content .= $this->serializeNode($node);
        }

        return $content;
    }

    /**
     * Get content between two headings.
     */
    private function getContentBetweenHeadings(array $currentHeading, ?array $nextHeading, \DOMXPath $xpath): string
    {
        $currentNode = $currentHeading['node'];
        $nextNode = $nextHeading['node'] ?? null;

        // Get all direct children of body to preserve structure
        $bodyChildren = $xpath->query('//body/*');

        $content = '';
        $foundCurrent = false;

        foreach ($bodyChildren as $node) {
            // Skip until we find the current heading or its container
            if (!$foundCurrent) {
                if ($node->isSameNode($currentNode) || $this->nodeContainsHeading($node, $currentNode)) {
                    $foundCurrent = true;
                    // If the current node contains the heading, we need to extract content after the heading
                    if ($this->nodeContainsHeading($node, $currentNode)) {
                        $content .= $this->extractContentAfterHeading($node, $currentNode);
                    }
                }
                continue;
            }

            // Stop when we reach the next heading or its container
            if ($nextNode && ($node->isSameNode($nextNode) || $this->nodeContainsHeading($node, $nextNode))) {
                // If the next node contains the heading, extract content before the heading
                if ($this->nodeContainsHeading($node, $nextNode)) {
                    $content .= $this->extractContentBeforeHeading($node, $nextNode);
                }
                break;
            }

            // Skip headings themselves to avoid duplication
            if (preg_match('/^h[1-6]$/', $node->nodeName)) {
                continue;
            }

            // Add this node's HTML to content using proper serialization
            $content .= $this->serializeNode($node);
        }

        return $content;
    }

    /**
     * Check if a node contains a specific heading node.
     */
    private function nodeContainsHeading(\DOMNode $container, \DOMNode $headingNode): bool
    {
        $xpath = new \DOMXPath($container->ownerDocument);
        $containedHeadings = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $container);

        foreach ($containedHeadings as $contained) {
            if ($contained->isSameNode($headingNode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all content from document when no headings are found.
     */
    private function getAllContent(\DOMXPath $xpath): string
    {
        $bodyNodes = $xpath->query('//body/*');
        $content = '';

        foreach ($bodyNodes as $node) {
            $content .= $this->serializeNode($node);
        }

        return $content;
    }

    /**
     * Create paragraph from content without heading.
     */
    private function createParagraphFromContent(string $content, int $nestingLevel): array
    {
        $textContent = trim(strip_tags($content));

        // Generate title from first sentence or first 50 characters
        $title = '';
        if (!empty($textContent)) {
            // Try to get first sentence
            $sentences = preg_split('/([.!?]+)/', $textContent, 2, PREG_SPLIT_DELIM_CAPTURE);
            $firstSentence = trim($sentences[0]);

            // Add back the punctuation if it exists
            if (isset($sentences[1]) && !empty($sentences[1])) {
                $firstSentence .= $sentences[1][0];
            }

            // Limit title length
            if (strlen($firstSentence) > 50) {
                $title = substr($firstSentence, 0, 50) . '...';
            } else {
                $title = $firstSentence;
            }
        }

        return [
            'title' => $title,
            'text' => $content,
            'files' => null,
            'nestingLevel' => $nestingLevel,
        ];
    }

    /**
     * Safely serialize a DOM node to HTML string.
     */
    private function serializeNode(\DOMNode $node): string
    {
        $html = $node->ownerDocument->saveHTML($node);

        // Clean up any artifacts from DOM processing but preserve specific attribute spacing
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        // Ensure the specific spacing format for table cells as expected by tests
        $html = preg_replace('/(<td[^>]*)"(\s*>)/', '$1" >', $html);

        return $html;
    }

    /**
     * Extract content from a container node that appears before a specific heading.
     */
    private function extractContentBeforeHeading(\DOMNode $container, \DOMNode $headingNode): string
    {
        $content = '';

        foreach ($container->childNodes as $child) {
            if ($child->isSameNode($headingNode)) {
                break;
            }

            if ($child->nodeType === XML_ELEMENT_NODE) {
                // Check if this child contains the heading
                if ($this->nodeContainsHeading($child, $headingNode)) {
                    $content .= $this->extractContentBeforeHeading($child, $headingNode);
                } else {
                    $content .= $this->serializeNode($child);
                }
            }
        }

        return $content;
    }

    /**
     * Extract content from a container node that appears after a specific heading.
     */
    private function extractContentAfterHeading(\DOMNode $container, \DOMNode $headingNode): string
    {
        $content = '';
        $foundHeading = false;

        foreach ($container->childNodes as $child) {
            if ($child->isSameNode($headingNode)) {
                $foundHeading = true;
                continue;
            }

            if ($foundHeading && $child->nodeType === XML_ELEMENT_NODE) {
                $content .= $this->serializeNode($child);
            } elseif (!$foundHeading && $child->nodeType === XML_ELEMENT_NODE) {
                // Check if this child contains the heading
                if ($this->nodeContainsHeading($child, $headingNode)) {
                    $content .= $this->extractContentAfterHeading($child, $headingNode);
                }
            }
        }

        return $content;
    }

    /**
     * Detect if a paragraph should be treated as a heading and at what level.
     */
    private function detectHeadingLevel(\DOMNode $node, string $content): int
    {
        // First check style-based detection
        $styleName = $node->getAttribute('text:style-name');
        if ($styleName && isset($this->headingStyleMap[$styleName])) {
            return $this->headingStyleMap[$styleName];
        }
        
        // Then check content patterns
        $patternLevel = $this->detectHeadingByPattern($content);
        if ($patternLevel > 0) {
            return $patternLevel;
        }
        
        // Finally check formatting-based detection
        return $this->detectHeadingByFormatting($node, $content);
    }

    /**
     * Detect heading level based on content patterns.
     */
    private function detectHeadingByPattern(string $content): int
    {
        $text = trim(strip_tags($content));
        
        // Skip policy items (single number followed by G or Z, with or without space)
        if (preg_match('/^\d+\s*[GZ]\s*$/u', $text)) {
            return 0; // Keep as paragraph
        }
        
        // Generic numerical patterns (avoiding specific language patterns)
        // Note: \s* allows for optional whitespace to handle both "7 Title" and "7Title" cases
        $patterns = [
            '/^\d+\.\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u' => 4, // "3.1.1.1 Sub-sub-subsection" or "3.1.1.1Sub-sub-subsection"
            '/^\d+\.\d+\.\d+\s*[A-ZÄÖÜ]/u' => 3, // "3.1.1 Sub-subsection" or "3.1.1Sub-subsection"
            '/^\d+\.\d+\s*[A-ZÄÖÜ]/u' => 2,     // "2.1 Subsection" or "2.1Subsection"
            '/^\d+\s*[A-ZÄÖÜ]/u' => 1,           // "1 Section Title" or "1Section"
            '/^\(\d+\)\s*[A-ZÄÖÜ]/u' => 2,      // "(1) Parenthetical heading" or "(1)Parenthetical"
        ];
        
        foreach ($patterns as $pattern => $level) {
            if (preg_match($pattern, $text)) {
                return $level;
            }
        }
        
        return 0;
    }

    /**
     * Detect heading level based on formatting (bold, size, etc.).
     */
    private function detectHeadingByFormatting(\DOMNode $node, string $content): int
    {
        // This method would analyze the formatting of the content
        // For now, return 0 (no heading detected)
        // Could be extended to analyze font sizes, bold formatting, etc.
        return 0;
    }
}
