<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Exception\AssessmentExportOptionsException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentExportOptions;
use Tests\Base\FunctionalTestCase;

class AssessmentExportOptionsTest extends FunctionalTestCase
{
    /**
     * @var AssessmentExportOptions
     */
    protected $sut = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(AssessmentExportOptions::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->sut = null;
    }

    public function testMergeOptionsFailsWithoutDefaults()
    {
        $this->expectException(AssessmentExportOptionsException::class);
        $this->sut->mergeOptions([]);
    }

    public function testMergeOptionsFailsWithIncompleteDefaults()
    {
        $this->expectException(AssessmentExportOptionsException::class);

        $this->sut->mergeOptions(
            [
                'defaults'            => [],
                'original_statements' => [],
                'fragment_list'       => [],
            ]
        );
    }

    public function testMergeOptionsMerges()
    {
        $options = [
            'defaults'            => [
                'print' => true,
            ],
            'assessment_table'    => [
                'print' => false,
            ],
            'original_statements' => [],
            'fragment_list'       => [],
        ];

        $mergedOptions = $this->sut->mergeOptions($options);

        static::assertTrue($mergedOptions['fragment_list']['print']);
        static::assertFalse($mergedOptions['assessment_table']['print']);
    }

    public function testValidateFailsWithMissingSections()
    {
        $this->expectException(AssessmentExportOptionsException::class);

        $options = [
            'defaults' => [],
        ];

        $this->sut->validateOptionSet($options);
    }

    public function testValidateAllowsMissingSectionsOnProjectOptions()
    {
        self::markSkippedForCIIntervention();

        $options = [
            'defaults' => [],
        ];

        $this->sut->validateOptionSet($options, true);
    }

    public function testGetGetsNamedSection()
    {
        $all = $this->sut->all();
        $assessmentTable = $this->sut->get('assessment_table');

        static::assertEquals($all['assessment_table'], $assessmentTable);
    }
}
