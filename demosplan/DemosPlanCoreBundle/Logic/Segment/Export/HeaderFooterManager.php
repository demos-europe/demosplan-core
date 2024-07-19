<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;


use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Contracts\Translation\TranslatorInterface;

class HeaderFooterManager
{
    private HTMLSanitizer $htmlSanitizer;
    private TranslatorInterface $translator;
    private array $styles;

    public function __construct(
        HTMLSanitizer $htmlSanitizer,
        TranslatorInterface $translator,
        array $styles
    ) {
        $this->htmlSanitizer = $htmlSanitizer;
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

    private function addPreambleIfFirstHeader(Header $header, ?string $headerType): void
    {
        if (Footer::FIRST === $headerType) {
            $preamble = $this->translator->trans('docx.export.preamble');
            Html::addHtml($header, $this->getHtmlValidText($preamble), false, false);
        }
    }

    private function getHtmlValidText(string $text): string
    {
        /** @var string $text $text */
        $text = str_replace('<br>', '<br/>', $text);

        // strip all a tags without href
        $pattern = '/<a\s+(?!.*?\bhref\s*=\s*([\'"])\S*\1)(.*?)>(.*?)<\/a>/i';
        $text = preg_replace($pattern, '$3', $text);

        // avoid problems in phpword parser
        return $this->htmlSanitizer->purify($text);
    }
}
