<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export\Odt;

use demosplan\DemosPlanCoreBundle\Exception\OdtProcessingException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\ODText;
use PhpOffice\PhpWord\Writer\WriterInterface;
use ZipArchive;

/**
 * PhpWord's ODText writer drops table borders: `Writer/ODText/Style/Table`
 * only emits alignment, and `Writer/ODText/Element/Table` attaches no cell
 * style. The dispatch sits behind `private` Part\Content helpers, so we
 * cannot override it via inheritance. Wraps a stock ODText writer and patches
 * `content.xml` after the .odt is written: one bordered table-cell automatic
 * style, referenced from every `<table:table-cell>`.
 */
class OdtBorderedWriter implements WriterInterface
{
    private const CELL_STYLE_NAME = 'DPlanBorderedCell';
    private const CELL_STYLE_XML = '<style:style style:name="'.self::CELL_STYLE_NAME.'" style:family="table-cell">'
        .'<style:table-cell-properties fo:border="0.5pt solid #000000" fo:padding="0.05cm"/>'
        .'</style:style>';

    private readonly ODText $inner;

    public function __construct(?PhpWord $phpWord = null)
    {
        $this->inner = new ODText($phpWord);
    }

    public function save(string $filename): void
    {
        $targetIsOutputStream = 'php://output' === $filename || 'php://stdout' === $filename;

        try {
            $tempDir = DemosPlanPath::getTemporaryPath();
        } catch (\Throwable) {
            throw OdtProcessingException::processingFailed('Could not allocate temp file for ODT writer.');
        }

        $workFile = $targetIsOutputStream ? \tempnam($tempDir, 'odtb_') : $filename;
        if (false === $workFile) {
            throw OdtProcessingException::processingFailed('Could not allocate temp file for ODT writer.');
        }

        $this->inner->save($workFile);
        $this->injectBorders($workFile);

        if ($targetIsOutputStream) {
            // Open the patched .odt for reading. 'rb' = read, binary mode —
            // important so ZIP bytes are passed through verbatim on every platform.
            $stream = \fopen($workFile, 'rb');
            if (false !== $stream) {
                // Stream from current position to EOF straight into php://output in
                // ~8 KiB chunks. Same destination as echo, i.e. the HTTP response body
                // under FPM. readfile() does the same in one call but is on FPM's
                // disable_functions list; this fopen + fpassthru + fclose trio is not.
                \fpassthru($stream);

                // fpassthru advances the handle's position to EOF but does not close it.
                \fclose($stream);
            }

            // Remove the temp file; bytes are already on the wire.
            @\unlink($workFile);
        }
    }

    private function injectBorders(string $odtPath): void
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($odtPath)) {
            throw OdtProcessingException::unableToOpenFile($odtPath);
        }

        $content = $zip->getFromName('content.xml');
        if (false === $content) {
            $zip->close();
            throw OdtProcessingException::processingFailed('ODT archive is missing content.xml.');
        }

        $patched = $this->patchContentXml($content);

        if ($patched !== $content) {
            $zip->deleteName('content.xml');
            $zip->addFromString('content.xml', $patched);
        }

        $zip->close();
    }

    private function patchContentXml(string $xml): string
    {
        if (false === \strpos($xml, '<table:table-cell')) {
            return $xml;
        }

        $withStyle = \preg_replace(
            '#(</office:automatic-styles>)#',
            self::CELL_STYLE_XML.'$1',
            $xml,
            1
        );

        if (null === $withStyle || $withStyle === $xml) {
            return $xml;
        }

        return \preg_replace(
            '#<table:table-cell(?![^>]*table:style-name=)#',
            '<table:table-cell table:style-name="'.self::CELL_STYLE_NAME.'"',
            $withStyle
        ) ?? $withStyle;
    }
}
