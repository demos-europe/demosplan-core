<?php

namespace demosplan\DemosPlanCoreBundle\Logic\DocumentExporter;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseDocxExporter
{
    /**
     * @var array<string, mixed>
     */
    public array $styles;
    public function __construct(
        protected  StyleInitializer $styleInitializer,
        protected TranslatorInterface $translator,
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

    abstract public function addHeader(Section $section, Procedure $procedure, ?string $headerType = null): void;

    /**
     * @throws Exception
     */
    private function addNoStatementsMessage(PhpWord $phpWord, Section $section): WriterInterface
    {
        $noEntriesMessage = $this->translator->trans('statements.filtered.none');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);

        return IOFactory::createWriter($phpWord);
    }
}
