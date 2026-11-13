<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\PDF;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureTitlePageExporter
{
    private const LIGHT_GREY = 'lightgrey';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function createWriter(Procedure $procedure): PDF
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->setDefaultFontName('Helvetica');
        $phpWord->setDefaultFontSize(12);
        $phpWord->getDocInfo()->setTitle($this->translator->trans('titlepage', [], 'page-title'));

        $name = $procedure->getName();
        if ($name !== $procedure->getExternalName()) {
            $name .= ' ('.$procedure->getExternalName().')';
        }

        $section = $phpWord->addSection([
            'marginLeft'   => Converter::cmToTwip(1.5),
            'marginRight'  => Converter::cmToTwip(1.5),
            'marginTop'    => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(1),
        ]);

        // the HTML based PDF writer ignores headers, footers and paragraph borders
        $headerStyle = ['size' => 9];
        $headerCell = $section->addTable($this->tableStyle())->addRow()->addCell(null, $this->ruleStyle());
        $headerCell->addText($procedure->getOrgaName().' - '.$name, $headerStyle, ['spaceAfter' => 0]);
        $headerCell->addText('Ausgedruckt am '.$this->formatPrintDate(), $headerStyle, ['spaceAfter' => 0]);

        $section->addTextBreak(4);

        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 480];
        $titleCell = $section->addTable($this->tableStyle())->addRow()->addCell(null, $this->ruleStyle());
        $titleCell->addText($this->label('procedure.name').': '.$name, null, $center);
        $section->addTextBreak(1);
        $section->addText($this->label('procedure.agency').': '.$procedure->getOrgaName(), null, $center);

        $planningOffices = [];
        foreach ($procedure->getPlanningOffices() as $office) {
            $planningOffices[] = $office->getName();
        }
        $section->addText(
            $this->label('planningagency.participating').': '.implode(', ', $planningOffices),
            null,
            $center
        );

        $section->addText(
            $this->label('period.participation').': '
            .$this->formatPeriod($procedure->getStartDate(), $procedure->getEndDate())
            .' / '
            .$this->formatPeriod($procedure->getPublicParticipationStartDate(), $procedure->getPublicParticipationEndDate()),
            null,
            $center
        );

        return new PDF($phpWord);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableStyle(): array
    {
        return [
            'width'             => 100 * 50,
            'unit'              => 'pct',
            'borderTopStyle'    => 'none',
            'borderLeftStyle'   => 'none',
            'borderRightStyle'  => 'none',
            'borderBottomStyle' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleStyle(): array
    {
        return [
            'borderTopStyle'    => 'none',
            'borderLeftStyle'   => 'none',
            'borderRightStyle'  => 'none',
            'borderBottomSize'  => 12,
            'borderBottomStyle' => 'solid',
            'borderBottomColor' => self::LIGHT_GREY,
        ];
    }

    private function formatPrintDate(): string
    {
        return (new \IntlDateFormatter('de_DE', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'd. MMMM y, HH:mm'))
            ->format(new \DateTimeImmutable());
    }

    private function formatPeriod(\DateTimeInterface $start, \DateTimeInterface $end): string
    {
        return $start->format('d.m.Y').' - '.$end->format('d.m.Y');
    }

    private function label(string $key): string
    {
        return $this->translator->trans($key);
    }
}
