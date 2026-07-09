<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Extends {@see TemplateProcessor} with the ability to embed images directly into the OOXML
 * ZIP archive and inject the corresponding VML image runs into a paragraph populated via
 * {@see TemplateProcessor::setComplexBlock()}.
 *
 * After `setComplexBlock` serialises a {@see \PhpOffice\PhpWord\Element\TextRun} into
 * `$tempDocumentMainPart`, the original placeholder is gone and cannot be targeted again.
 * Images are therefore embedded through a two-step approach:
 *
 *  1. Each `<img>` in the segment HTML is replaced with the sentinel string
 *     {@see self::IMAGE_SENTINEL} before the TextRun is built. The sentinel text ends up
 *     verbatim inside one or more `<w:t>` elements in the serialised XML.
 *  2. {@see self::embedAndInjectNextImage()} adds the image bytes to `word/media/`, registers
 *     a relationship in `word/_rels/document.xml.rels`, updates `[Content_Types].xml`, and
 *     replaces the first remaining sentinel with a VML `<v:imagedata r:id="rIdN"/>` run.
 *     Calling this once per image in document order consumes sentinels left-to-right.
 *
 * Using relationship-based (`r:id`) image references rather than embedded `data:` URIs
 * ensures compatibility with LibreOffice and other OOXML consumers that do not support
 * VML imagedata with inline data URI sources.
 */
class StatementTemplateProcessor extends TemplateProcessor
{
    public const IMAGE_SENTINEL = 'DPLANIMG';

    /**
     * Embeds $imageData into the DOCX ZIP archive, registers an OOXML relationship,
     * and injects a VML image run at the position of the next {@see self::IMAGE_SENTINEL}
     * in the main document part.
     *
     * The image file is stored as `word/media/image{rId}.{ext}` (e.g. `word/media/image5.png`).
     * A relationship entry with the same `rId` is appended to the main-part `.rels` document,
     * and a content-type override is registered in `[Content_Types].xml`.
     */
    public function embedAndInjectNextImage(
        string $imageData,
        string $mimeType,
        string $widthPt,
        string $heightPt,
    ): void {
        // Map MIME type to a file extension for the word/media/ entry. The extension is
        // cosmetic in OOXML: Word and LibreOffice resolve the image format from the
        // ContentType attribute in [Content_Types].xml, not from the filename. The default
        // branch covers image/png (the most common case — detectMimeType() already falls back
        // to it) and any exotic format finfo may return (bmp, tiff, …); those files end up
        // with a .png extension but render correctly because the ContentType is still accurate.
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'png',
        };

        // A DOCX contains multiple parts (document.xml, header1.xml, footer1.xml, …), each
        // with its own word/_rels/{part}.xml.rels. $tempDocumentRelations is therefore a map
        // of partName → relationshipsXml. The main part (typically 'word/document.xml') is
        // where the image references live, so all three artefacts — ZIP entry, content-type
        // override, and relationship — are registered against it.
        // getNextRelationsIndex() reads the existing .rels to find the highest current rId
        // and returns the next free integer, ensuring no collision with pre-existing entries.
        $mainPartName = $this->getMainPartName();
        $rId = $this->getNextRelationsIndex($mainPartName);
        $imgFilename = 'image'.$rId.'.'.$extension;

        // 1. Write the raw image bytes into the ZIP archive at word/media/{filename}.
        //    TemplateProcessor keeps the DOCX open as a ZipArchive until saveAs() is called,
        //    so addFromString() writes into the in-memory ZIP without touching the disk yet.
        $this->zipClass->addFromString('word/media/'.$imgFilename, $imageData);

        // 2. Register a content-type override in [Content_Types].xml so that Word knows
        //    which MIME type to associate with this media file. Without this entry the file
        //    is present in the ZIP but Word refuses to render it.
        //    str_replace targets '</Types>' — the closing tag of the single root element
        //    mandated by the OOXML spec, so there is always exactly one occurrence to replace:
        //      <Types xmlns="…">
        //        <Default Extension="rels" ContentType="…"/>
        //        <Override PartName="/word/document.xml" ContentType="…"/>
        //        …
        //      </Types>
        $contentTypeEntry = '<Override PartName="/word/media/'.$imgFilename
            .'" ContentType="'.$mimeType.'"/>';
        $this->tempDocumentContentTypes = str_replace(
            '</Types>',
            $contentTypeEntry.'</Types>',
            $this->tempDocumentContentTypes,
        );

        // 3. Append an image relationship to word/_rels/document.xml.rels. The Relationship
        //    element maps rId{N} → media/{filename} so that VML's r:id attribute can reference
        //    the file by ID rather than by path, which is the OOXML-standard indirection layer.
        //    str_replace targets '</Relationships>' — the closing tag of the single root element,
        //    so there is always exactly one occurrence to replace:
        //      <Relationships xmlns="…">
        //        <Relationship Id="rId1" Type="…/officeDocument" Target="document.xml"/>
        //        <Relationship Id="rId2" Type="…/styles"         Target="styles.xml"/>
        //        …
        //      </Relationships>
        $relationEntry = '<Relationship Id="rId'.$rId
            .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
            .' Target="media/'.$imgFilename.'"/>';
        $this->tempDocumentRelations[$mainPartName] = str_replace(
            '</Relationships>',
            $relationEntry.'</Relationships>',
            $this->tempDocumentRelations[$mainPartName],
        );

        // 4. Build the VML — Vector Markup Language — run that references the image by its relationship ID.
        //    <w:pict> wraps the VML shape so that it is treated as inline content inside the
        //    paragraph run. type="#_x0000_t75" identifies this as a picture-frame shape:
        //    msosptPictureFrame (0x4B) in the MSOSPT "Microsoft Office Shape Preset Type" enum defined
        //    in — https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-odraw/9c0c5c01-9e90-41aa-ba15-477dacb4cc8e
        //    The style attribute carries the display dimensions in points; r:id points to the
        //    relationship registered above so the renderer knows which file to load.
        $vmlRunXml = '<w:r>'
            .'<w:pict>'
            .'<v:shape'
            .' xmlns:v="urn:schemas-microsoft-com:vml"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' type="#_x0000_t75"'
            .' style="width:'.$widthPt.';height:'.$heightPt.'">'
            .'<v:imagedata'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' r:id="rId'.$rId.'"'
            .' o:title=""/>'
            .'</v:shape>'
            .'</w:pict>'
            .'</w:r>';

        // 5. Locate the sentinel left by buildRichTextAndImageRuns() and replace it with
        //    the VML run. The limit of 1 replacement ensures images are consumed in the
        //    same order they were collected — one call per image, left-to-right in the document.
        $this->injectNextImageRunXml($vmlRunXml);
    }

    /**
     * Replaces the first occurrence of {@see self::IMAGE_SENTINEL} in the main document
     * XML with $vmlRunXml. The sentinel may appear:
     *
     *  - as the sole content of a `<w:t>` node → the entire `<w:r>` is swapped out, or
     *  - embedded within a larger text node → the `<w:t>` is split and the VML run is
     *    inserted between the left and right text halves, preserving run properties.
     */
    private function injectNextImageRunXml(string $vmlRunXml): void
    {
        $sentinel = preg_quote(self::IMAGE_SENTINEL, '/');

        // The pattern matches a complete <w:r> run that contains the sentinel inside its <w:t>.
        // Annotated breakdown:
        //
        //   <w:r(?P<runAttributes>[^>]*)>
        //     Opening run tag. [^>]* greedily captures any attributes (e.g. w:rsidR="00AB1234")
        //     or empty string if none. Stops at > so it cannot bleed into child elements.
        //
        //   \s*
        //     PhpWord pretty-prints the XML with newlines and indentation between child elements.
        //
        //   (?P<runProperties><w:rPr(?:[^>]*\/>|[^>]*>.*?<\/w:rPr>))?
        //     Optionally captures the run-properties block (bold, italic, colour, …).
        //     The inner non-capturing group handles the two valid forms:
        //       [^>]*\/>           — self-closing:    <w:rPr/>
        //       [^>]*>.*?<\/w:rPr> — with children:   <w:rPr><w:b/></w:rPr>
        //     .*? is lazy so it stops at the first </w:rPr>. The trailing /s modifier on the
        //     whole pattern (after the closing delimiter) makes . also match newlines, which is
        //     necessary here because PhpWord indents the child elements of <w:rPr> across lines.
        //     The outer ? makes the entire block optional (absent when no formatting is applied).
        //
        //   \s*
        //     Whitespace between <w:rPr> and <w:t>.
        //
        //   <w:t(?P<textAttributes>[^>]*)>
        //     Opening text tag. Captures attributes, most commonly xml:space="preserve"
        //     which PhpWord adds when the text node starts or ends with a space character.
        //
        //   (?P<textBefore>[^<]*)
        //     Plain text preceding the sentinel in the same text node. [^<]* stops at any
        //     opening tag, ensuring we never capture across element boundaries.
        //
        //   {$sentinel}
        //     The literal sentinel string DPLANIMG.
        //
        //   (?P<textAfter>[^<]*)
        //     Plain text following the sentinel in the same text node (same [^<]* logic).
        //
        //   <\/w:t>\s*<\/w:r>
        //     Closing tags with optional whitespace between them.
        $pattern = '/<w:r(?P<runAttributes>[^>]*)>\s*(?P<runProperties><w:rPr(?:[^>]*\/>|[^>]*>.*?<\/w:rPr>))?\s*<w:t(?P<textAttributes>[^>]*)>(?P<textBefore>[^<]*)'.$sentinel.'(?P<textAfter>[^<]*)<\/w:t>\s*<\/w:r>/s';

        $this->tempDocumentMainPart = preg_replace_callback(
            $pattern,
            static function (array $match) use ($vmlRunXml): string {
                // If there was text before the sentinel in the same run, emit it as its own
                // <w:r> with the same properties — otherwise it would be lost.
                $result = '';
                if ('' !== $match['textBefore']) {
                    $result .= '<w:r'.$match['runAttributes'].'>'.$match['runProperties']
                        .'<w:t'.$match['textAttributes'].'>'.$match['textBefore'].'</w:t></w:r>';
                }

                // The image run replaces exactly the sentinel position.
                $result .= $vmlRunXml;

                // Same for text that followed the sentinel: preserve it in a new run.
                // xml:space="preserve" is added unconditionally here because trailing spaces
                // (e.g. after the sentinel in a mixed run) would otherwise be collapsed by XML parsers.
                if ('' !== $match['textAfter']) {
                    $result .= '<w:r'.$match['runAttributes'].'>'.$match['runProperties']
                        .'<w:t xml:space="preserve">'.$match['textAfter'].'</w:t></w:r>';
                }

                return $result;
            },
            $this->tempDocumentMainPart,
            1, // consume one sentinel per call — images are injected in document order
        ) ?? $this->tempDocumentMainPart;
    }
}
