<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Elements;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadElementsData;
use demosplan\DemosPlanCoreBundle\Logic\Elements\PlanningDocumentCategoryTreeReorderer;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Doctrine\Common\Collections\ArrayCollection;
use Tests\Base\FunctionalTestCase;

class PlanningDocumentCategoryTreeReordererTest extends FunctionalTestCase
{
    /**
     * @var PlanningDocumentCategoryTreeReorderer
     */
    protected $sut;

    /**
     * @dataProvider getInsertAtData
     */
    public function testInsertAt(
        array $inputArray,
        string $target,
        int $index,
        bool $updateIndices,
        array $expectedOutput
    ): void {
        $list = new ArrayCollection($inputArray);
        $this->invokeProtectedMethod([$this->sut, 'insertAt'], $list, $target, $index, $updateIndices);
        self::assertEquals($expectedOutput, $list->toArray());
    }

    /**
     * @dataProvider getChangeNecessaryTestData
     */
    public function testIsChangeNecessary(
        string $categoryFixture,
        ?string $newParentFixture,
        ?int $newOrder,
        bool $necessary
    ): void {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();
        $this->enablePermissions(['feature_admin_element_edit']);

        $category = $this->getElementReference($categoryFixture);
        $newParentId = null === $newParentFixture
            ? null
            : $this->getElementReference($newParentFixture)->getId();
        $data = $this->sut->getReorderingData(
            $category->getId(),
            $newParentId,
            $newOrder,
            $category->getProcedure()->getId()
        );
        $changeNecessary = $this->sut->isChangeNecessary($data);
        self::assertSame($necessary, $changeNecessary);
    }

    public function testUpdateEntitiesWithMoveToOtherCategory(): void
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();
        $this->enablePermissions(['feature_admin_element_edit']);

        $category = $this->getElementReference(LoadElementsData::TEST_ELEMENT_1);
        self::assertNull($category->getParent());
        self::assertSame(1, $category->getOrder());
        $newParent = $this->getElementReference(LoadElementsData::ELEMENT_CATEGORY_FILE);
        $data = $this->sut->getReorderingData(
            $category->getId(),
            $newParent->getId(),
            2,
            $category->getProcedure()->getId()
        );

        $this->sut->updateEntities($data);
        self::assertSame(0, $category->getOrder());
        self::assertSame($newParent, $category->getParent());
        self::assertContains($category, $newParent->getChildren());
    }

    public function testUpdateEntitiesWithMoveToRoot(): void
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();
        $this->enablePermissions(['feature_admin_element_edit']);

        $category = $this->getElementReference(LoadElementsData::TEST_ELEMENT_2);
        self::assertNotNull($category->getParent());
        self::assertSame(2, $category->getOrder());
        $data = $this->sut->getReorderingData(
            $category->getId(),
            null,
            0,
            $category->getProcedure()->getId()
        );

        $this->sut->updateEntities($data);
        self::assertSame(0, $category->getOrder());
        self::assertNull($category->getParent());
        self::assertNotContains($category->getId(), $data->getPreviousNeighbors());
    }

    public function testUpdateEntitiesWithNullIndex(): void
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();
        $this->enablePermissions(['feature_admin_element_edit']);

        $category = $this->getElementReference(LoadElementsData::TEST_ELEMENT_2);
        self::assertNotNull($category->getParent());
        self::assertSame(2, $category->getOrder());
        $data = $this->sut->getReorderingData(
            $category->getId(),
            null,
            null,
            $category->getProcedure()->getId()
        );

        $this->sut->updateEntities($data);
        self::assertSame(3, $category->getOrder());
        self::assertNull($category->getParent());
        self::assertNotContains($category->getId(), $data->getPreviousNeighbors());
    }

    public function getInsertAtData(): array
    {
        $inputWithOneItem = [
            0 => 'f',
        ];
        $appendableInputWithFiveItems = [
            1 => 'a',
            2 => 'b',
            3 => 'c',
            4 => 'd',
            5 => 'e',
        ];
        $inputWithThreeItems = [
            0 => 'a',
            1 => 'b',
            2 => 'c',
        ];
        $inputWithFiveItems = [
            0 => 'a',
            1 => 'b',
            3 => 'c',
            4 => 'd',
            5 => 'e',
        ];
        $expectedOutputWithSixItems = [
            0 => 'a',
            1 => 'b',
            2 => 'f',
            3 => 'c',
            4 => 'd',
            5 => 'e',
        ];

        return [
            // #0: displace middle
            [
                $inputWithThreeItems,
                'd',
                1,
                true,
                [
                    0 => 'a',
                    1 => 'd',
                    2 => 'b',
                    3 => 'c',
                ],
            ],
            // #1: replace middle
            [
                $inputWithThreeItems,
                'd',
                1,
                false,
                [
                    0 => 'a',
                    1 => 'd',
                    2 => 'c',
                ],
            ],
            // #2: displace middle with later hole
            [
                [
                    0 => 'a',
                    1 => 'b',
                    2 => 'c',
                    4 => 'd',
                    5 => 'e',
                ],
                'f',
                1,
                true,
                [
                    0 => 'a',
                    1 => 'f',
                    2 => 'b',
                    3 => 'c',
                    4 => 'd',
                    5 => 'e',
                ],
            ],
            // #3: displace at hole at beginning
            [
                $appendableInputWithFiveItems,
                'f',
                0,
                true,
                array_merge($inputWithOneItem, $appendableInputWithFiveItems),
            ],
            // #4: displace at hole in middle
            [
                $inputWithFiveItems,
                'f',
                2,
                true,
                $expectedOutputWithSixItems,
            ],
            // #5: replace at hole at middle
            [
                $inputWithFiveItems,
                'f',
                2,
                false,
                $expectedOutputWithSixItems,
            ],
        ];
    }

    public function getChangeNecessaryTestData(): array
    {
        return [
            // #0: testIsChangeNecessaryNoWithoutParent
            [
                LoadElementsData::TEST_ELEMENT_1,
                null,
                1,
                false,
            ],
            // #1: testIsChangeNecessaryNoWithParent
            [
                LoadElementsData::TEST_ELEMENT_2,
                LoadElementsData::TEST_ELEMENT_1,
                2,
                false,
            ],
            // #2: testIsChangeNecessaryYesBecauseHierarchy
            [
                LoadElementsData::TEST_ELEMENT_1,
                LoadElementsData::ELEMENT_CATEGORY_FILE,
                1,
                true,
            ],
            // #3: testIsChangeNecessaryYesBecauseOrder
            [
                LoadElementsData::TEST_ELEMENT_2,
                null,
                3,
                true,
            ],
            // #4: testIsChangeNecessaryYesBecauseHierarchyAndOrder
            [
                LoadElementsData::TEST_ELEMENT_1,
                LoadElementsData::ELEMENT_CATEGORY_FILE,
                2,
                true,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(PlanningDocumentCategoryTreeReorderer::class);
        $currentProcedureProvider = $this->getContainer()->get(CurrentProcedureService::class);
        $category = $this->getElementReference(LoadElementsData::TEST_ELEMENT_1);
        $currentProcedureProvider->setProcedure($category->getProcedure());
    }
}
