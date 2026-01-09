<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use Exception;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Style\Table;
use ReflectionException;

/**
 * Trait containing common formatting methods for assessment table exports.
 *
 * This trait provides shared functionality for DOCX and PDF exports to avoid code duplication.
 */
trait AssessmentTableFormattingTrait
{
    /**
     * Get logger instance.
     * Classes using this trait must implement this method.
     */
    abstract protected function getLogger();

    /**
     * Get default Docx Page Styles.
     */
    protected function getDefaultDocxPageStyles(ViewOrientation $orientation): array
    {
        $styles = [];
        // Benutze das ausgewählte Format
        $styles['orientation'] = [];
        // im Hochformat werden für LibreOffice anderen Breiten benötigt
        $styles['cellWidthTotal'] = 10000;
        $styles['firstCellWidth'] = 1500;
        $styles['cellWidth'] = 3850;
        $styles['cellWidthSecondThird'] = 7500;

        $tableStyle = $this->getDefaultDocxTableStyle();
        $styles['tableStyle'] = $tableStyle;
        $styles['cellStyleStatementDetails'] = ['gridSpan' => 2, 'bgColor' => 'f0f0f5', 'valign' => 'top'];
        $styles['textStyleStatementDetails'] = ['bold' => true];
        $styles['textStyleStatementDetailsParagraphStyles'] = ['spaceAfter' => 0];
        $styles['cellHeading'] = ['align' => 'center', 'valign' => 'center'];
        $styles['cellHeadingText'] = ['bold' => true, 'valign' => 'center', 'align' => 'center', 'name' => 'Arial', 'size' => 9];
        $styles['cellTop'] = ['valign' => 'top'];

        if ($orientation->isLandscape()) {
            $styles['cellWidthTotal'] = 14000;
            $styles['orientation'] = ['orientation' => 'landscape'];
            $styles['firstCellWidth'] = 2000;
            $styles['cellWidth'] = 6000;
            $styles['cellWidthSecondThird'] = 12000;
        }

        return $styles;
    }

    protected function getDefaultDocxTableStyle(): Table
    {
        $tableStyle = new Table();

        $tableStyle->setLayout(Table::LAYOUT_FIXED);
        $tableStyle->setBorderColor($this->tableStyle['borderColor']);
        $tableStyle->setBorderSize($this->tableStyle['borderSize']);
        $tableStyle->setCellMargin($this->tableStyle['cellMargin']);

        return $tableStyle;
    }

    /**
     * Generate Html imagetag to be used in PhphWord Html::addHtml().
     *
     * @param string $imageFile
     * @param int    $maxWidth  maximum image width in pixel
     *
     * @return string
     */
    protected function getDocxImageTag($imageFile, $maxWidth = 500)
    {
        $imgTag = '';
        $width = 300;
        $height = 300;
        $margin = 10;
        // phpword needs a local file, no need for flysystem
        if (!file_exists($imageFile)) {
            return $imgTag;
        }

        // get Image size
        $imageInfo = getimagesize($imageFile);
        if (2 < (is_countable($imageInfo) ? count($imageInfo) : 0)) {
            $width = $imageInfo[0] - $margin;
            $height = $imageInfo[1] - $margin;
        }

        // check that picture is not wider than allowed
        if ($width > $maxWidth) {
            $factor = $width / $maxWidth;

            // resize Image
            if (0 != $factor) {
                $width /= $factor;
                $height /= $factor;
            }
            $this->getLogger()->info('Docx Image resize to width: '.$width.' and height: '.$height);
        }

        return '<img height="'.$height.'" width="'.$width.'" src="'.$imageFile.'"/>';
    }

    /**
     * Statement in unified data format.
     *
     * @return array - formatted statement
     *
     * @throws ReflectionException
     *
     * @deprecated Use {@link formatStatementObject} instead
     */
    public function formatStatementArray(array $statement): array
    {
        return [
            'type'                          => 'statement',
            'attachments'                   => $statement['attachments'] ?? null,
            'authoredDate'                  => $statement['meta']['authoredDate'] ?? null,
            'cluster'                       => $statement['cluster'] ?? null,
            'documentTitle'                 => $statement['document']['title'] ?? null,
            'externId'                      => $statement['externId'] ?? null,
            'formerExternId'                => $statement['formerExternId'] ?? null,
            'elementTitle'                  => $statement['element']['title'] ?? null,
            'files'                         => $statement['files'] ?? null,
            'orgaName'                      => $statement['meta']['orgaName'] ?? null,
            'orgaDepartmentName'            => $statement['meta']['orgaDepartmentName'] ?? null,
            'originalId'                    => $statement['original']['ident'] ?? null,
            'paragraphTitle'                => $statement['paragraph']['title'] ?? null,
            'parentId'                      => $statement['parent']['ident'] ?? null,
            'polygon'                       => $statement['polygon'] ?? null,
            'publicAllowed'                 => $statement['publicAllowed'] ?? null,
            'publicCheck'                   => $statement['publicCheck'] ?? null,
            'publicStatement'               => $statement['publicStatement'] ?? null,
            'publicVerified'                => $statement['publicVerified'] ?? null,
            'publicVerifiedTranslation'     => $statement['publicVerifiedTranslation'] ?? null,
            'recommendation'                => $statement['recommendation'] ?? null,
            'votePla'                       => $statement['votePla'] ?? null,
            'submit'                        => $statement['submit'] ?? null,
            'submitName'                    => $statement['meta']['submitName'] ?? null,
            'authorName'                    => $statement['meta']['authorName'] ?? null,
            'text'                          => $statement['text'] ?? null,
            'votes'                         => $statement['votes'] ?? null,
            'votesNum'                      => $statement['votesNum'] ?? null,
            'likesNum'                      => $statement['likesNum'] ?? null,
            'fragments'                     => [],
            'userState'                     => $statement['meta']['userState'] ?? null,
            'userOrganisation'              => $statement['meta']['userOrganisation'] ?? null,
            'userGroup'                     => $statement['meta']['userGroup'] ?? null,
            'movedToProcedureName'          => $statement['movedToProcedureName'] ?? null,
            'movedFromProcedureName'        => $statement['movedFromProcedureName'] ?? null,
            'userPosition'                  => $statement['meta']['userPosition'] ?? null,
            'isClusterStatement'            => $statement['isClusterStatement'] ?? null,
            'name'                          => $statement['name'] ?? null,
            'isSubmittedByCitizen'          => $statement['isSubmittedByCitizen'] ?? null,
        ];
    }

    /**
     * Statement in unified data format.
     *
     * @return array formatted statement
     *
     * @throws ReflectionException
     */
    public function formatStatementObject(Statement $statement): array
    {
        $item = $this->statementService->convertToLegacy($statement);
        $item['parent'] = $this->statementService->convertToLegacy($statement->getParent());
        $item['original'] = $this->statementService->convertToLegacy($statement->getOriginal());

        return $this->formatStatementArray($item);
    }

    /**
     * Fragment in unified data format.
     *
     * @return array - formatted fragment
     *
     * @throws Exception
     *
     * @deprecated Use {@link formatFragmentObject} instead
     */
    public function formatFragmentArray(array $statement, array $fragment): array
    {
        $tmpElementId = $fragment['elementId'];
        $tmpElementTitle = $fragment['elementTitle'];

        $item = $this->formatStatementArray($statement);
        $item['sortIndex'] = $fragment['sortIndex'];

        // override selected item fields with fragment content:
        $item['type'] = 'fragment';
        $item['created'] = $fragment['created'] ?? null;

        $item['text'] = '';
        $item['recommendation'] = '';
        // we need to fetch Fragment, as text fields are not mapped in statement
        // index for performance reasons
        $statementFragment = $this->statementHandler->getStatementFragment($fragment['id']);
        if ($statementFragment instanceof StatementFragment) {
            // pretend as if consideration would be an recommendation
            // as it has the same behaviour
            $item['recommendation'] = $statementFragment->getConsideration();
            $item['text'] = $statementFragment->getText();
        }

        $item['elementId'] = $tmpElementId;
        $item['elementTitle'] = $tmpElementTitle;

        return $item;
    }

    /**
     * @throws Exception
     */
    protected function renderGroup(
        StatementEntityGroup $group,
        callable $entriesRenderFunction,
        Section $section,
        int $depth = 0,
    ): void {
        $section->addTitle($group->getTitle(), $depth + 2);

        foreach ($group->getSubgroups() as $subgroup) {
            $this->renderGroup(
                $subgroup,
                $entriesRenderFunction,
                $section,
                $depth + 1
            );
        }

        if (0 !== (is_countable($group->getEntries()) ? count($group->getEntries()) : 0)) {
            $entriesRenderFunction($section, $group->getEntries());
        }
    }

    /**
     * T10049
     * Centralisation of logic to generate string of externId.
     *
     * Includes "Kopie von" in case of current statement is a copy of a statement and placeholder statement information.
     *
     * @param array $statementArray
     *
     * @deprecated use {@link AssessmentTableServiceOutput::createExternIdStringFromObject} instead
     */
    public function createExternIdString($statementArray): string
    {
        $externIdString = '';

        // add "copyof"
        if (isset($statementArray['originalId']) && isset($statementArray['parentId'])
            && $statementArray['originalId'] != $statementArray['parentId']
            && false === is_null($statementArray['parentId'])) {
            $externIdString .= $this->translator->trans('copyof').' ';
        }

        $externIdString .= $statementArray['externId'];

        // add former externID in case of statement was moved from another procedure

        // was moved?
        if (array_key_exists('formerExternId', $statementArray) && false === is_null($statementArray['formerExternId'])) {
            $externIdString .= ' ('.$this->translator->trans('formerExternId').': '.$statementArray['formerExternId'].' '.$this->translator->trans('from').' '.$statementArray['movedFromProcedureName'].')';
        } elseif (array_key_exists('placeholderStatement', $statementArray)
            && false === is_null($statementArray['placeholderStatement'])) {
            // dont know, if $statementArray['placeholderStatement'] is an object or array. -> handle both cases:
            if ($statementArray['placeholderStatement'] instanceof Statement) {
                $formerExternId = $statementArray['placeholderStatement']->getExternId();
                $nameOfFormerProcedure = $statementArray['placeholderStatement']->getProcedure()->getName();
            } else {
                $formerExternId = $statementArray['placeholderStatement']['externId'];
                $nameOfFormerProcedure = $statementArray['placeholderStatement']['procedure']['name'];
            }
            $externIdString .= ' ('.$this->translator->trans('formerExternId').': '.$formerExternId.' '.$this->translator->trans('from').' '.$nameOfFormerProcedure.')';
        }

        // if statement was moved into another procedure, this will usually be displayed in the textfield of the statement
        return $externIdString;
    }

    /**
     * T10049
     * Centralisation of logic to generate string of externId.
     *
     * Includes "Kopie von" in case of current statement is a copy of a statement and placeholder statement information.
     */
    public function createExternIdStringFromObject(Statement $statement): string
    {
        $externIdString = $statement->getExternId();

        // add "copyof"
        if (null !== $statement->getParentId()
            && $statement->getOriginalId() != $statement->getParentId()) {
            $externIdString = $this->translator->trans('copyof').' '.$externIdString;
        }

        // add former externID in case of statement was moved from another procedure
        // was moved?
        $placeholderStatement = $statement->getPlaceholderStatement();
        if (null !== $statement->getFormerExternId()) {
            $formerExternId = $statement->getFormerExternId();
            $nameOfFormerProcedure = $statement->getMovedFromProcedureName();
            $externIdString .= $this->createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure);
        } elseif (null !== $placeholderStatement) {
            $formerExternId = $placeholderStatement->getExternId();
            $nameOfFormerProcedure = $placeholderStatement->getProcedure()->getName();
            $externIdString .= $this->createFormerProcedureSuffix($formerExternId, $nameOfFormerProcedure);
        }

        // if statement was moved into another procedure, this will usually be displayed in the textfield of the statement
        return $externIdString;
    }
}
