<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;


use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Contracts\Translation\TranslatorInterface;

class HeaderFooterManager
{
    private HtmlHelper $htmlHelper;
    private TranslatorInterface $translator;
    private array $styles;

    public function __construct(
        HtmlHelper $htmlHelper,
        TranslatorInterface $translator,
        array $styles
    ) {
        $this->htmlHelper = $htmlHelper;
        $this->translator = $translator;
        $this->styles = $styles;
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

    public function addFooter(Section $section, Statement $statement): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable();
        $row = $table->addRow();

        $cell1 = $row->addCell($this->styles['footerCellWidth'], $this->styles['footerCell']);
        $footerLeftString = $this->getFooterLeftString($statement);
        $cell1->addText($footerLeftString, $this->styles['footerStatementInfoFont'], $this->styles['footerStatementInfoParagraph']);

        $cell2 = $row->addCell($this->styles['footerCellWidth'], $this->styles['footerCell']);
        $cell2->addPreserveText(
            $this->translator->trans('segments.export.pagination'),
            $this->styles['footerPaginationFont'],
            $this->styles['footerPaginationParagraph']
        );
    }

    private function addPreambleIfFirstHeader(Header $header, ?string $headerType): void
    {
        if (Footer::FIRST === $headerType) {
            $preamble = $this->translator->trans('docx.export.preamble');
            Html::addHtml($header, $this->htmlHelper->getHtmlValidText($preamble), false, false);
        }
    }

    private function getFooterLeftString(Statement $statement): string
    {
        $info = [];
        if ($this->validInfoString($statement->getUserName())) {
            $info[] = $statement->getUserName();
        }
        if ($this->validInfoString($statement->getExternId())) {
            $info[] = $statement->getExternId();
        }
        if ($this->validInfoString($statement->getInternId())) {
            $info[] = $statement->getInternId();
        }

        return implode(', ', $info);
    }

    private function validInfoString(?string $text): bool
    {
        return null !== $text && '' !== trim($text);
    }
}
