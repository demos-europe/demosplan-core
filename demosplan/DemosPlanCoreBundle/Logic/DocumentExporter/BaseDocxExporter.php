<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\DocumentExporter;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseDocxExporter
{
    /**
     * @var array<string, mixed>
     */
    public array $styles;

    public function __construct(
        protected StyleInitializer $styleInitializer,
        protected TranslatorInterface $translator,
        protected HtmlHelper $htmlHelper,
    ) {
        $this->styles = $styleInitializer->initialize();
    }

    /**
     * @throws Exception
     */
    public function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @throws Exception
     */
    private function addNoStatementsMessage(PhpWord $phpWord, Section $section): WriterInterface
    {
        $noEntriesMessage = $this->translator->trans('statements.filtered.none');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);

        return IOFactory::createWriter($phpWord);
    }

    public function addHeader(Section $section, Procedure $procedure, ?string $headerType = null): void
    {
        $header = null === $headerType ? $section->addHeader() : $section->addHeader($headerType);
        $header->addText(
            $procedure->getName(),
            $this->styles['documentTitleFont'],
            $this->styles['documentTitleParagraph']
        );

        $this->addPreambleIfFirstHeader($header, $headerType);

        $currentDate = new DateTime();
        $header->addText(
            $this->translator->trans('segments.export.statement.export.date', ['date' => $currentDate->format('d.m.Y')]),
            $this->styles['currentDateFont'],
            $this->styles['currentDateParagraph']
        );
    }

    private function addPreambleIfFirstHeader(Header $header, ?string $headerType): void
    {
        if (Footer::FIRST === $headerType) {
            $preamble = $this->translator->trans('docx.export.preamble');
            Html::addHtml($header, $this->htmlHelper->getHtmlValidText($preamble), false, false);
        }
    }
}
