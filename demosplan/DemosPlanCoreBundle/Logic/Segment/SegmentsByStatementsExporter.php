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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
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
        Slugify $slugify,
        TranslatorInterface $translator
    ) {
        parent::__construct($currentUser, $slugify, $translator);
    }

    public function getSynopseFileName(Procedure $procedure, string $suffix): string
    {
        return 'Synopse-'.$this->slugify->slugify($procedure->getName()).'.'.$suffix;
    }

    /**
     * @throws Exception
     */
    public function exportAll(Procedure $procedure, Statement ...$statements): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements($phpWord, $procedure, $statements);
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
        // unfortunately for xlsx export data needs to be an array
        foreach ($statements as $statement) {
            $segmentsOrStatements = collect([$statement]);
            if (!$statement->getSegmentsOfStatement()->isEmpty()) {
                $segmentsOrStatements = $statement->getSegmentsOfStatement();
            }
            foreach ($segmentsOrStatements as $segmentOrStatement) {
                $exportData[] = $this->convertIntoExportableArray($segmentOrStatement);
            }
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
        $this->addHeader($section, $procedure);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    private function exportStatements(PhpWord $phpWord, Procedure $procedure, array $statements): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure);

        for ($i = 0; $i < count($statements); ++$i) {
            $this->exportStatement($section, $statements[$i]);
            $section = $this->getNewSectionIfNeeded($phpWord, $section, $i, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    public function exportStatementSegmentsInSeparateDocx(Statement $statement, Procedure $procedure): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure);
        $this->exportStatement($section, $statement);

        return $phpWord;
    }

    public function exportStatement(Section $section, Statement $statement): void
    {
        $this->addStatementInfo($section, $statement);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement);
        $this->addFooter($section, $statement);
    }

    /**
     * Creates a file name from each given {@link Statement} to be used in the ZIP the
     * {@link Statement} is exported in.
     *
     * Initially the file name is created from the
     * submitters name and the extern ID of the statement. In case of one or multiple
     * duplicate file names based on this information the database ID is used additionally
     * for all conflicting {@link Statement}s. Non-conflicting {@link Statement}s will
     * still only use the submitter name and intern ID.
     *
     * @param array<int, Statement> $statements
     *
     * @return array<string, Statement>
     */
    public function mapStatementsToPathInZip(array $statements): array
    {
        $pathedStatements = [];
        $previousKeysOfReaddedDuplicates = [];
        foreach ($statements as $statement) {
            $pathInZip = $this->getPathInZip($statement, false);
            // in case of a duplicate, add the database ID to the name
            if (\array_key_exists($pathInZip, $pathedStatements)) {
                $duplicate = $pathedStatements[$pathInZip];
                $previousKeysOfReaddedDuplicates[$pathInZip] = $pathInZip;
                $duplicateExtendedPathInZip = $this->getPathInZip($duplicate, true);
                $pathedStatements[$duplicateExtendedPathInZip] = $duplicate;
                $pathInZip = $this->getPathInZip($statement, true);
            }

            if (\array_key_exists($pathInZip, $pathedStatements)) {
                throw new InvalidArgumentException('duplicated statement given');
            }

            $pathedStatements[$pathInZip] = $statement;
        }

        // Remove old keys of duplicates only after the previous loop has completed,
        // as otherwise a third duplicate would be added to the result array without
        // the extended path.
        foreach ($previousKeysOfReaddedDuplicates as $key) {
            unset($pathedStatements[$key]);
        }

        return $pathedStatements;
    }

    /**
     * Creates a file name from the given {@link Statement}.
     *
     * The file name is created from the submitters name and the extern ID of the statement.
     * If the trimmed extern ID is an empty string it will not be included in the result.
     * Optionally the database ID of the statement can be included too to ensure uniqueness.
     *
     * While the extern ID is set in normal parenthesis (`(1234)`), the database ID is set
     * in square brackets (`[abcd-ef12-…]`). This avoids confusion on the users part for
     * the case that the extern ID is an empty string and the database ID is included in
     * the result.
     */
    private function getPathInZip(Statement $statement, bool $withDbId): string
    {
        // prepare needed variables
        $dbId = $statement->getId();
        $externId = $statement->getExternId();

        $orgaName = $statement->getMeta()->getOrgaName();
        $authorSourceName = $orgaName;
        if (User::ANONYMOUS_USER_NAME === $orgaName) {
            $authorSourceName = $statement->getUserName();
        }
        if (null === $authorSourceName || '' === trim((string) $authorSourceName)) {
            $authorSourceName = $this->translator->trans('statement.name_source.unknown');
        }

        // determine and return the file name

        if ('' === trim((string) $externId)) {
            return $withDbId
                ? "$authorSourceName [$dbId].docx"
                : "$authorSourceName.docx";
        }

        return $withDbId
            ? "$authorSourceName ($externId) [$dbId].docx"
            : "$authorSourceName ($externId).docx";
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
        $exportData['tags'] = array_map($this->entityHelper->toArray(...), $exportData['tags']->toArray());
        foreach ($exportData['tags'] as $key => $tag) {
            $exportData['tags'][$key]['topicTitle'] = $tag['topic']->getTitle();
        }
        $exportData['topicNames'] = $segmentOrStatement->getTopicNames();
        $exportData['isClusterStatement'] = $segmentOrStatement->isClusterStatement();

        return $exportData;
    }
}
