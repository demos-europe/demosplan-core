<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Logic\FilterUiDataProvider;
use ReflectionProperty;
use Tests\Base\FunctionalTestCase;

class FilterUiDataProviderTest extends FunctionalTestCase
{
    /**
     * @var FilterUiDataProvider
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FilterUiDataProvider::class);
        $reflectionProperty = new ReflectionProperty($this->sut, 'relativeFilterNamesPath');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->sut, '../tests/backend/core/Core/Functional/res/tagFilterNames.yaml');
    }

    public function testExpectedFilterNames(): void
    {
        self::markSkippedForCIIntervention();

        $filterNames = $this->sut->getFilterNames();
        $expected = [
            'tags'  => [
                'comparisonOperator'  => 'ARRAY_CONTAINS_VALUE',
                'labelTranslationKey' => 'tags',
                'rootPath'            => 'tags',
                'grouping'            => [
                    'labelTranslationKey' => 'topic',
                    'targetPath'          => 'tags.topic.label',
                ],
            ],
            'place' => [
                'comparisonOperator'  => '=',
                'labelTranslationKey' => 'workflow.place',
                'rootPath'            => 'place',
            ],
        ];

        self::assertEquals($expected, $filterNames);
    }

    public function testAddSelectedFieldWithoutSelection(): void
    {
        $filterNames = $this->sut->getFilterNames();
        $expectedResult = [
            'tags' => [
                'comparisonOperator'  => 'ARRAY_CONTAINS_VALUE',
                'labelTranslationKey' => 'tags',
                'rootPath'            => 'tags',
                'selected'            => false,
                'grouping'            => [
                    'labelTranslationKey' => 'topic',
                    'targetPath'          => 'tags.topic.label',
                ],
            ],
        ];
        $filter = [
            'conditionOne' => [
                'condition' => [
                    'path'  => 'tags.topic.label',
                    'value' => 'foobar',
                ],
            ],
        ];
        $filterNames = $this->sut->addSelectedField($filterNames, $filter);
        self::assertEquals($expectedResult, $filterNames);
    }

    public function testAddSelectedFieldWithSelection(): void
    {
        $filterNames = $this->sut->getFilterNames();
        $expectedResult = [
            'tags' => [
                'comparisonOperator'  => 'ARRAY_CONTAINS_VALUE',
                'labelTranslationKey' => 'tags',
                'rootPath'            => 'tags',
                'selected'            => true,
                'grouping'            => [
                    'labelTranslationKey' => 'topic',
                    'targetPath'          => 'tags.topic.label',
                ],
            ],
        ];
        $filter = [
            'conditionOne' => [
                'condition' => [
                    'path'  => 'tags',
                    'value' => 'foobar',
                ],
            ],
        ];
        $filterNames = $this->sut->addSelectedField($filterNames, $filter);
        self::assertEquals($expectedResult, $filterNames);
    }
}
