<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Export;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocxExporter;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\ExportTemplate;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\PhpWord;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;

class DocxExporterGroupedNumberingTest extends FunctionalTestCase
{
    protected ?DocxExporter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(DocxExporter::class);
        $this->loginTestUser();
    }

    public function testStatementsAreNumberedContinuouslyAcrossGroups(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        new ExportFieldsConfiguration($procedure->_real());
        $statements = StatementFactory::createMany(3, ['procedure' => $procedure]);

        $subgroupA = new StatementEntityGroup('A');
        $subgroupA->setEntry('1', $statements[0]->_real());
        $subgroupA->setEntry('2', $statements[1]->_real());
        $subgroupB = new StatementEntityGroup('B');
        $subgroupB->setEntry('3', $statements[2]->_real());
        $root = new StatementEntityGroup('root');
        $root->setSubgroup('a', $subgroupA);
        $root->setSubgroup('b', $subgroupB);

        $section = (new PhpWord())->addSection();

        // Act
        $this->renderGroup($root, $section, true);

        // Assert
        self::assertSame(['1', '2', '3'], $this->extractStatementNumbers($section));
    }

    public function testStatementsAreNotNumberedIfNumberingIsDisabled(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        new ExportFieldsConfiguration($procedure->_real());
        $statement = StatementFactory::createOne(['procedure' => $procedure]);
        $group = new StatementEntityGroup('A');
        $group->setEntry('1', $statement->_real());
        $root = new StatementEntityGroup('root');
        $root->setSubgroup('a', $group);

        $section = (new PhpWord())->addSection();

        // Act
        $this->renderGroup($root, $section, false);

        // Assert
        self::assertSame([], $this->extractStatementNumbers($section));
    }

    private function renderGroup(StatementEntityGroup $group, Section $section, bool $numberStatements): void
    {
        $orientation = ViewOrientation::createLandscape();
        $stylesMethod = new ReflectionMethod($this->sut, 'getDefaultDocxPageStyles');
        $styles = $stylesMethod->invoke($this->sut, $orientation);

        $renderMethod = new ReflectionMethod($this->sut, 'renderStatementsInGroup');
        $renderMethod->invoke(
            $this->sut,
            $group,
            $section,
            $styles,
            $orientation,
            false,
            $numberStatements,
            ExportTemplate::PORTRAIT_WITH_PRIORITIZATION->value
        );
    }

    /**
     * @return list<string> the numbers rendered as "Nr: <number>" in the document, in document order
     */
    private function extractStatementNumbers(Section $section): array
    {
        $prefix = $this->getContainer()->get('translator')->trans('statement.nr').': ';
        $numbers = [];
        foreach ($section->getElements() as $element) {
            if (!$element instanceof Table) {
                continue;
            }
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        if ($cellElement instanceof Text && str_starts_with($cellElement->getText(), $prefix)) {
                            $numbers[] = substr($cellElement->getText(), strlen($prefix));
                        }
                    }
                }
            }
        }

        return $numbers;
    }
}
