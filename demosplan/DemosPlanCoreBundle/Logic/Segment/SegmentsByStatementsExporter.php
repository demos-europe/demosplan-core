<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\RecommendationConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Doctrine\Common\Collections\ArrayCollection;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use ReflectionException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsByStatementsExporter extends SegmentsExporter
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        CurrentUserInterface $currentUser,
        private readonly EntityHelper $entityHelper,
        HTMLSanitizer $htmlSanitizer,
        ImageLinkConverter $imageLinkConverter,
        private readonly RecommendationConverter $recommendationConverter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator
    ) {
        parent::__construct(
            $currentUser,
            $htmlSanitizer,
            $imageLinkConverter,
            $slugify,
            $styleInitializer,
            $translator
        );
    }

    /**
     * @throws Exception
     */
    public function exportAll(array $tableHeaders, Procedure $procedure, Statement ...$statements): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements($phpWord, $procedure, $statements, $tableHeaders);
    }

    /**
     * Exports Segments or the Statement itself, in case of unsegmented Statement.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws ReflectionException
     * @throws HandlerException
     */
    public function exportAllXlsx(Statement ...$statements): IWriter
    {
        Settings::setOutputEscapingEnabled(true);
        $exportData = [];
        $adjustedRecommendations = [];
        // unfortunately for xlsx export data needs to be an array
        foreach ($statements as $statement) {
            $segmentsOrStatements = collect([$statement]);
            if (!$statement->getSegmentsOfStatement()->isEmpty()) {
                $segmentsOrStatements = $statement->getSegmentsOfStatement();
                $adjustedRecommendations[] = $this->recommendationConverter->convertImagesToReferencesInRecommendations(
                    $this->sortSegmentsByOrderInProcedure($segmentsOrStatements->toArray())
                );
            }
            foreach ($segmentsOrStatements as $segmentOrStatement) {
                $exportData[] = $this->convertIntoExportableArray($segmentOrStatement);
            }
        }

        foreach ($adjustedRecommendations as $recommendation) {
            $exportData =
                $this->recommendationConverter->updateRecommendationsWithTextReferences($exportData, $recommendation);
        }

        $columnsDefinition = $this->assessmentTableXlsExporter->selectFormat('segments');

        return $this->assessmentTableXlsExporter->createExcel($exportData, $columnsDefinition);
    }

    /**
     * @throws Exception
     */
    private function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    private function exportStatements(PhpWord $phpWord, Procedure $procedure, array $statements, array $tableHeaders): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        foreach ($statements as $index => $statement) {
            $this->exportStatement($section, $statement, $tableHeaders);
            $section = $this->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    public function exportStatementSegmentsInSeparateDocx(Statement $statement, Procedure $procedure, array $tableHeaders): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);
        $this->exportStatement($section, $statement, $tableHeaders);

        return $phpWord;
    }

    public function exportStatement(Section $section, Statement $statement, array $tableHeaders): void
    {
        $this->addStatementInfo($section, $statement);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders);
        $this->addFooter($section, $statement);
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

    /**
     * @param array<int, Statement> $statements
     */
    private function getNewSectionIfNeeded(PhpWord $phpWord, Section $section, int $i, array $statements): Section
    {
        if ($this->isNotLastStatement($statements, $i)) {
            $section = $phpWord->addSection($this->styles['globalSection']);
        }

        return $section;
    }

    /**
     * @param array<int, Statement> $statements
     */
    private function isNotLastStatement(array $statements, int $i): bool
    {
        return $i !== count($statements) - 1;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    private function convertIntoExportableArray(StatementInterface $segmentOrStatement): array
    {
        $exportData = $this->entityHelper->toArray($segmentOrStatement);
        $exportData['meta'] = $this->entityHelper->toArray($exportData['meta']);
        $exportData['submitDateString'] = $segmentOrStatement->getSubmitDateString();
        $exportData['countyNames'] = $segmentOrStatement->getCountyNames();
        $exportData['meta']['authoredDate'] = $segmentOrStatement->getAuthoredDateString();

        // Some data is stored on parentStatement instead on Segment and have to get from there
        if ($segmentOrStatement instanceof Segment) {
            $exportData['meta']['orgaCity'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaCity();
            $exportData['meta']['orgaStreet'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaStreet();
            $exportData['meta']['orgaPostalCode'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaPostalCode();
            $exportData['meta']['orgaEmail'] = $segmentOrStatement->getParentStatementOfSegment()->getOrgaEmail();
            $exportData['meta']['authorName'] = $segmentOrStatement->getParentStatementOfSegment()->getAuthorName();
            $exportData['meta']['submitName'] = $segmentOrStatement->getParentStatementOfSegment()->getSubmitterName();
            $exportData['meta']['houseNumber'] = $segmentOrStatement->getParentStatementOfSegment()->getMeta()->getHouseNumber();
            $exportData['memo'] = $segmentOrStatement->getParentStatementOfSegment()->getMemo();
            $exportData['internId'] = $segmentOrStatement->getParentStatementOfSegment()->getInternId();
            $exportData['oName'] = $segmentOrStatement->getParentStatementOfSegment()->getOName();
            $exportData['meta']['authoredDate'] = $segmentOrStatement->getParentStatementOfSegment()->getAuthoredDateString();
            $exportData['dName'] = $segmentOrStatement->getParentStatementOfSegment()->getDName();
            $exportData['status'] = $segmentOrStatement->getPlace()->getName(); // Segments using place instead of status
            $exportData['fileNames'] = $segmentOrStatement->getParentStatementOfSegment()->getFileNames();
            $exportData['submitDateString'] = $segmentOrStatement->getParentStatementOfSegment()->getSubmitDateString();
        }
        $exportData['tagNames'] = $segmentOrStatement->getTagNames();
        /** @var ArrayCollection $tagsCollection */
        $tagsCollection = $exportData['tags'];
        $exportData['tags'] = array_map($this->entityHelper->toArray(...), $tagsCollection->toArray());
        foreach ($exportData['tags'] as $key => $tag) {
            /** @var TagTopic $tagTopic */
            $tagTopic = $tag['topic'];
            $exportData['tags'][$key]['topicTitle'] = $tagTopic->getTitle();
        }
        $exportData['topicNames'] = $segmentOrStatement->getTopicNames();
        $exportData['isClusterStatement'] = $segmentOrStatement->isClusterStatement();

        return $exportData;
    }
}
