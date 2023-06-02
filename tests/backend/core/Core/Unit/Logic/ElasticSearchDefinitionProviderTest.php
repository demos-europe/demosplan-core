<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Project\Core\Unit\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\ElasticSearchDefinitionProvider;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use InvalidArgumentException;
use Tests\Base\UnitTestCase;

class ElasticSearchDefinitionProviderTest extends UnitTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $currentUser = $this->getContainer()->get(CurrentUserInterface::class);
        $mockGlobalConfig = $this->getMockBuilder(GlobalConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockGlobalConfig->method('getElasticsearchQueryDefinition')
            ->willReturn($this->getEsMockData());
        $this->sut = new ElasticSearchDefinitionProvider($currentUser, $mockGlobalConfig);
    }

    public function testGetAvailableFieldsInputError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->getAvailableFields('statementSegment', 'filter', 'planner');
    }

    public function testGetAvailableFieldsSuccess(): void
    {
        $availableFields = $this->sut->getAvailableFields('statementSegment', 'search', 'planner');
        self::assertIsArray($availableFields);
        self::assertEquals($this->getExpectedResult(), $availableFields);
    }

    private function getExpectedResult(): array
    {
        return [
            'text'                     => 'segment.text',
            'externId'                 => 'segment.external_id',
            'recommendation'           => 'segment.recommendation',
            'parentStatement.externId' => 'statement.external_id',
        ];
    }

    private function getEsMockData(): array
    {
        return [
            'statementSegment' => [
                'search' => [
                    'all'     => [
                        'text' => '',
                    ],
                    'planner' => [
                        'text'            => [
                            'field'    => 'text.text',
                            'titleKey' => 'segment.text',
                            'boost'    => 0.5,
                        ],
                        'externId'        => [
                            'titleKey' => 'segment.external_id',
                        ],
                        'recommendation'  => [
                            'titleKey' => 'segment.recommendation',
                        ],
                        'parentStatement' => [
                            'memo'     => [
                                'field'      => 'parentStatement.memo',
                                'titleKey'   => 'memo',
                                'permission' => 'field_statement_memo',
                            ],
                            'externId' => [
                                'field'    => 'parentStatement.externId',
                                'titleKey' => 'statement.external_id',
                            ],
                            'orgaName' => [
                                'field'      => 'parentStatement.meta.orgaName',
                                'boost'      => 0.2,
                                'titleKey'   => 'organisation.name',
                                'permission' => 'feature_institution_participation',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
