<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\FunctionalLogicException;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapHandler;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use InvalidArgumentException;
use Tests\Base\FunctionalTestCase;

class MapHandlerTest extends FunctionalTestCase
{
    /** @var MapHandler */
    protected $sut;

    /** @var Procedure */
    protected $testProcedure;
    /**
     * @var ManagerRegistry|null
     */
    protected $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(MapHandler::class);
        $this->testProcedure = $this->fixtures->getReference('testProcedure2');
        $this->doctrine = self::getContainer()->get('doctrine');
    }

    public function testGetGisLayer()
    {
        /** @var GisLayer $testGisLayer5 */
        $testGisLayer5 = $this->fixtures->getReference('testGisLayer5');
        $gisLayer = $this->sut->getGisLayer($testGisLayer5->getId());

        static::assertInstanceOf(GisLayer::class, $gisLayer);
        static::assertEquals($gisLayer->getId(), $testGisLayer5->getId());
        static::assertEquals($gisLayer->getTreeOrder(), $testGisLayer5->getTreeOrder());
        static::assertEquals($gisLayer->getCategoryId(), $testGisLayer5->getCategoryId());
        static::assertEquals($gisLayer->getName(), $testGisLayer5->getName());
        static::assertEquals($gisLayer->getOrder(), $testGisLayer5->getOrder());
    }

    public function testSetGisLayerCategory()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayer $testGisLayer5 */
        $testGisLayer5 = $this->fixtures->getReference('testGisLayer5');
        /** @var GisLayer $testGisLayer7 */
        $testGisLayer7 = $this->fixtures->getReference('testGisLayer7');

        $numberOfGisLayersBefore = $this->countEntries(GisLayer::class);
        $numberOfGisLayerCategoriesBefore = $this->countEntries(GisLayerCategory::class);

        $numberOfRootGisLayersBefore = $testGisLayerCategoryRoot->getGisLayers()->count();

        $result1 = $this->sut->updateGisLayer($testGisLayer5->getId(), ['categoryId' => $testGisLayerCategoryRoot->getId()]);
        $result2 = $this->sut->updateGisLayer($testGisLayer7->getId(), ['categoryId' => $testGisLayerCategoryRoot->getId()]);

        static::assertTrue(is_array($result1));
        static::assertTrue(is_array($result2));

        $gisLayer5 = $this->sut->getGisLayer($testGisLayer5->getId());
        $gisLayer7 = $this->sut->getGisLayer($testGisLayer7->getId());
        $testGisLayerCategoryRoot = $this->sut->getGisLayerCategory($testGisLayerCategoryRoot->getId());

        static::assertEquals($gisLayer5->getCategoryId(), $testGisLayerCategoryRoot->getId());
        static::assertEquals(0, $gisLayer5->getTreeOrder());
        static::assertEquals($gisLayer7->getCategoryId(), $testGisLayerCategoryRoot->getId());
        static::assertEquals(0, $gisLayer7->getTreeOrder());
        static::assertCount($numberOfRootGisLayersBefore + 2, $testGisLayerCategoryRoot->getGisLayers());

        static::assertEquals($numberOfGisLayersBefore, $this->countEntries(GisLayer::class));
        static::assertEquals($numberOfGisLayerCategoriesBefore, $this->countEntries(GisLayerCategory::class));
    }

    public function testChangeParent()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayerCategory $testGisLayerCategory3 */
        $testGisLayerCategory3 = $this->fixtures->getReference('testGisLayerCategory3');
        $numberOfChildrenOfRootBefore = count($testGisLayerCategoryRoot->getChildren());
        $numberOfChildrenOfCategory3Before = count($testGisLayerCategory3->getChildren());

        static::assertEmpty($testGisLayerCategory2->getChildren());
        static::assertEquals(0, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory2->getParentId());

        $updateData = [
            'parentId' => $testGisLayerCategory3->getId(),
        ];

        $this->sut->updateGisLayerCategory($testGisLayerCategory2->getId(), $updateData);
        $updatedGisLayerCategory = $this->sut->getGisLayerCategory($testGisLayerCategory2->getId());

        static::assertEquals($testGisLayerCategory3->getId(), $updatedGisLayerCategory->getParentId());
        static::assertEmpty($testGisLayerCategory2->getChildren());
        static::assertEquals(0, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals($testGisLayerCategory3->getId(), $testGisLayerCategory2->getParentId());
        static::assertCount(
            $numberOfChildrenOfRootBefore - 1,
            $testGisLayerCategoryRoot->getChildren()
        );
        static::assertCount(
            $numberOfChildrenOfCategory3Before + 1,
            $testGisLayerCategory3->getChildren()
        );
    }

    public function testRemoveParent()
    {
        /** @var GisLayerCategory $testGisLayerCategory6 */
        $testGisLayerCategory6 = $this->fixtures->getReference('testGisLayerCategory6');
        $currentPartent = $testGisLayerCategory6->getParent();
        $numberOfChildrenBefore = count($currentPartent->getChildren());
        $updateData = ['parentId' => null];
        $this->sut->updateGisLayerCategory($testGisLayerCategory6->getId(), $updateData);
        $updatedCategory = $this->sut->getGisLayerCategory($testGisLayerCategory6->getId());

        // expect no changes:
        static::assertEquals($updatedCategory->getId(), $updatedCategory->getId());
        static::assertEquals($updatedCategory->getParentId(), $updatedCategory->getParentId());
        static::assertEquals($updatedCategory->getParent(), $currentPartent);
        static::assertEquals($updatedCategory->getName(), $updatedCategory->getName());
        static::assertEquals($updatedCategory->getTreeOrder(), $updatedCategory->getTreeOrder());
        static::assertEquals($numberOfChildrenBefore, count($updatedCategory->getParent()->getChildren()));
    }

    public function testSetChildren()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayerCategory $testGisLayerCategory3 */
        $testGisLayerCategory3 = $this->fixtures->getReference('testGisLayerCategory3');
        /** @var GisLayerCategory $testGisLayerCategory5 */
        $testGisLayerCategory5 = $this->fixtures->getReference('testGisLayerCategory5');

        $numberOfChildrenOfRootBefore = count($testGisLayerCategoryRoot->getChildren());
        $numberOfChildrenOfCategory5Before = count($testGisLayerCategory3->getChildren());

        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory2->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory3->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory5->getParentId());

        $updateData = [
            'children' => [$testGisLayerCategory2, $testGisLayerCategory3],
        ];

        $this->sut->updateGisLayerCategory($testGisLayerCategory5->getId(), $updateData);
        $updatedGisLayerCategory = $this->sut->getGisLayerCategory($testGisLayerCategory5->getId());

        static::assertEquals($testGisLayerCategoryRoot->getId(), $updatedGisLayerCategory->getParentId());
        static::assertCount(count($updateData['children']), $updatedGisLayerCategory->getChildren());
        static::assertEquals($updatedGisLayerCategory->getId(), $testGisLayerCategory2->getParentId());
        static::assertEquals($updatedGisLayerCategory->getId(), $testGisLayerCategory3->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $updatedGisLayerCategory->getParentId());

        static::assertCount(
            $numberOfChildrenOfRootBefore - count($updateData['children']),
            $testGisLayerCategoryRoot->getChildren()
        );

        static::assertCount(
            $numberOfChildrenOfCategory5Before + count($updateData['children']),
            $testGisLayerCategory5->getChildren()
        );
    }

    public function testRemoveChildren()
    {
        $this->expectException(Exception::class);

        /** @var GisLayerCategory $testGisLayerCategory5 */
        $testGisLayerCategory5 = $this->fixtures->getReference('testGisLayerCategory5');
        $this->sut->updateGisLayerCategory($testGisLayerCategory5->getId(), ['children' => []]);
    }

    public function testSetGisLayerToCategory()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayer $testGisLayer2 */
        $testGisLayer2 = $this->fixtures->getReference('testGisLayer2');
        /** @var GisLayer $testGisLayer3 */
        $testGisLayer3 = $this->fixtures->getReference('testGisLayer3');

        // because of test data, this layers have no category:
        static::assertEquals($testGisLayerCategoryRoot, $testGisLayer2->getCategory());
        static::assertEquals($testGisLayerCategoryRoot, $testGisLayer3->getCategory());
        static::assertEmpty($testGisLayerCategory2->getGisLayers());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory2->getParentId());

        $updateData = [
            'gisLayers' => [$testGisLayer2, $testGisLayer3],
        ];

        $this->sut->updateGisLayerCategory($testGisLayerCategory2->getId(), $updateData);
        $updatedGisLayerCategory = $this->sut->getGisLayerCategory($testGisLayerCategory2->getId());

        static::assertEquals($testGisLayerCategoryRoot->getId(), $updatedGisLayerCategory->getParentId());
        static::assertEmpty($testGisLayerCategory2->getChildren());
        static::assertEquals(0, $testGisLayerCategory2->getTreeOrder());
        static::assertCount(count($updateData['gisLayers']), $testGisLayerCategory2->getGisLayers());
        static::assertEquals($testGisLayer2->getCategoryId(), $testGisLayerCategory2->getId());
        static::assertEquals($testGisLayer3->getCategoryId(), $testGisLayerCategory2->getId());
    }

    // test change treeOrder:

    public function testChangeTreeOrderWithGisLayersOnly()
    {
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayer $testGisLayer5 */
        $testGisLayer5 = $this->fixtures->getReference('testGisLayer5');
        /** @var GisLayer $testGisLayer7 */
        $testGisLayer7 = $this->fixtures->getReference('testGisLayer7');

        static::assertEmpty($testGisLayerCategory2->getGisLayers());

        // prepare data:
        $this->sut->updateGisLayer($testGisLayer5->getId(), ['categoryId' => $testGisLayerCategory2->getId()]);
        $this->sut->updateGisLayer($testGisLayer7->getId(), ['categoryId' => $testGisLayerCategory2->getId()]);

        static::assertNotEquals(5, $testGisLayer5->getTreeOrder());
        static::assertNotEquals(7, $testGisLayer7->getTreeOrder());

        $updateData = [
            'included' => [
                [
                    'id'         => $testGisLayer7->getId(),
                    'type'       => 'GisLayer',
                    'attributes' => [
                        'treeOrder' => 7,
                    ],
                ],
                [
                    'id'         => $testGisLayer5->getId(),
                    'type'       => 'GisLayer',
                    'attributes' => [
                        'treeOrder' => 5,
                    ],
                ],
            ],
        ];

        $this->sut->updateElementsOfRootCategory($updateData);

        $gisLayer5 = $this->sut->getGisLayer($testGisLayer5->getId());
        $gisLayer7 = $this->sut->getGisLayer($testGisLayer7->getId());

        static::assertEquals(5, $gisLayer5->getTreeOrder());
        static::assertEquals(7, $gisLayer7->getTreeOrder());
        static::assertEquals($testGisLayerCategory2->getId(), $gisLayer7->getCategoryId());
        static::assertEquals($testGisLayerCategory2->getId(), $gisLayer5->getCategoryId());
    }

    public function testChangeTreeOrderWithGisLayerCategoriesOnly()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayerCategory $testGisLayerCategory1 */
        $testGisLayerCategory1 = $this->fixtures->getReference('testGisLayerCategory1');
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayerCategory $testGisLayerCategory3 */
        $testGisLayerCategory3 = $this->fixtures->getReference('testGisLayerCategory3');
        /** @var GisLayerCategory $testGisLayerCategory4 */
        $testGisLayerCategory4 = $this->fixtures->getReference('testGisLayerCategory4');

        $numberOfChildrenOfRootCategoryBefore = count($testGisLayerCategoryRoot->getChildren());

        static::assertEmpty($testGisLayerCategory1->getChildren());
        static::assertEmpty($testGisLayerCategory2->getChildren());
        static::assertEmpty($testGisLayerCategory3->getChildren());
        static::assertEmpty($testGisLayerCategory4->getChildren());

        static::assertEquals(0, $testGisLayerCategory1->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory3->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory4->getTreeOrder());

        $updateData = [
            'included' => [
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory1->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory1->getName(),
                        'treeOrder' => 1,
                        'isVisible' => $testGisLayerCategory1->isVisible(),
                        'parentId'  => $testGisLayerCategory1->getParentId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory2->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory2->getName(),
                        'treeOrder' => 2,
                        'isVisible' => $testGisLayerCategory2->isVisible(),
                        'parentId'  => $testGisLayerCategory2->getParentId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory3->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory3->getName(),
                        'treeOrder' => 3,
                        'isVisible' => $testGisLayerCategory3->isVisible(),
                        'parentId'  => $testGisLayerCategory3->getParentId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory4->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory4->getName(),
                        'treeOrder' => 4,
                        'isVisible' => $testGisLayerCategory4->isVisible(),
                        'parentId'  => $testGisLayerCategory4->getParentId(),
                    ],
                ],
            ],
        ];

        $this->sut->updateElementsOfRootCategory($updateData);

        $testGisLayerCategory1 = $this->sut->getGisLayerCategory($testGisLayerCategory1->getId());
        $testGisLayerCategory2 = $this->sut->getGisLayerCategory($testGisLayerCategory2->getId());
        $testGisLayerCategory3 = $this->sut->getGisLayerCategory($testGisLayerCategory3->getId());
        $testGisLayerCategory4 = $this->sut->getGisLayerCategory($testGisLayerCategory4->getId());

        static::assertEquals(1, $testGisLayerCategory1->getTreeOrder());
        static::assertEquals(2, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals(3, $testGisLayerCategory3->getTreeOrder());
        static::assertEquals(4, $testGisLayerCategory4->getTreeOrder());
        static::assertCount($numberOfChildrenOfRootCategoryBefore, $testGisLayerCategoryRoot->getChildren());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory1->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory2->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory3->getParentId());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory4->getParentId());

        // update parents also:
        $updateData = [
            'included' => [
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory1->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory1->getName(),
                        'treeOrder' => 1,
                        'isVisible' => $testGisLayerCategory1->isVisible(),
                        'parentId'  => $testGisLayerCategoryRoot->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory2->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory2->getName(),
                        'treeOrder' => 2,
                        'isVisible' => $testGisLayerCategory2->isVisible(),
                        'parentId'  => $testGisLayerCategory1->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory3->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory3->getName(),
                        'treeOrder' => 3,
                        'isVisible' => $testGisLayerCategory3->isVisible(),
                        'parentId'  => $testGisLayerCategory2->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory4->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory4->getName(),
                        'treeOrder' => 4,
                        'isVisible' => $testGisLayerCategory4->isVisible(),
                        'parentId'  => $testGisLayerCategory3->getId(),
                    ],
                ],
            ],
        ];

        $this->sut->updateElementsOfRootCategory($updateData);

        $testGisLayerCategory1 = $this->sut->getGisLayerCategory($testGisLayerCategory1->getId());
        $testGisLayerCategory2 = $this->sut->getGisLayerCategory($testGisLayerCategory2->getId());
        $testGisLayerCategory3 = $this->sut->getGisLayerCategory($testGisLayerCategory3->getId());
        $testGisLayerCategory4 = $this->sut->getGisLayerCategory($testGisLayerCategory4->getId());

        static::assertEquals(1, $testGisLayerCategory1->getTreeOrder());
        static::assertEquals(2, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals(3, $testGisLayerCategory3->getTreeOrder());
        static::assertEquals(4, $testGisLayerCategory4->getTreeOrder());
        static::assertCount($numberOfChildrenOfRootCategoryBefore - 3, $testGisLayerCategoryRoot->getChildren());
        static::assertCount(1, $testGisLayerCategory1->getChildren());
        static::assertCount(1, $testGisLayerCategory2->getChildren());
        static::assertCount(1, $testGisLayerCategory3->getChildren());
        static::assertCount(0, $testGisLayerCategory4->getChildren());
        static::assertEquals($testGisLayerCategoryRoot->getId(), $testGisLayerCategory1->getParentId());
        static::assertEquals($testGisLayerCategory1->getId(), $testGisLayerCategory2->getParentId());
        static::assertEquals($testGisLayerCategory2->getId(), $testGisLayerCategory3->getParentId());
        static::assertEquals($testGisLayerCategory3->getId(), $testGisLayerCategory4->getParentId());
    }

    public function testChangeTreeOrderWithGisLayerAndCategories()
    {
        /** @var GisLayerCategory $testGisLayerCategoryRoot */
        $testGisLayerCategoryRoot = $this->fixtures->getReference('testGisLayerCategoryRoot');
        /** @var GisLayerCategory $testGisLayerCategory1 */
        $testGisLayerCategory1 = $this->fixtures->getReference('testGisLayerCategory1');
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategory2');
        /** @var GisLayerCategory $testGisLayerCategory3 */
        $testGisLayerCategory3 = $this->fixtures->getReference('testGisLayerCategory3');
        /** @var GisLayerCategory $testGisLayerCategory4 */
        $testGisLayerCategory4 = $this->fixtures->getReference('testGisLayerCategory4');
        /** @var GisLayerCategory $testGisLayerCategory5 */
        $testGisLayerCategory5 = $this->fixtures->getReference('testGisLayerCategory5');
        /** @var GisLayerCategory $testGisLayerCategory6 */
        $testGisLayerCategory6 = $this->fixtures->getReference('testGisLayerCategory6');
        /** @var GisLayer $testGisLayer5 */
        $testGisLayer5 = $this->fixtures->getReference('testGisLayer5');
        /** @var GisLayer $testGisLayer7 */
        $testGisLayer7 = $this->fixtures->getReference('testGisLayer7');

        $numberOfChildrenOfRootCategoryBefore = count($testGisLayerCategoryRoot->getChildren());
        $numberOfGisLayerOfRootCategoryBefore = count($testGisLayerCategoryRoot->getGisLayers());

        static::assertEmpty($testGisLayerCategory1->getChildren());
        static::assertEmpty($testGisLayerCategory2->getChildren());
        static::assertEmpty($testGisLayerCategory3->getChildren());
        static::assertEmpty($testGisLayerCategory4->getChildren());
        static::assertCount(1, $testGisLayerCategory5->getChildren());
        static::assertEmpty($testGisLayerCategory6->getChildren());

        static::assertEquals(0, $testGisLayerCategory1->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory2->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory3->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory4->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory5->getTreeOrder());
        static::assertEquals(0, $testGisLayerCategory6->getTreeOrder());

        // because of test data this gislayer have no category
        static::assertEquals($testGisLayerCategory1, $testGisLayer5->getCategory());
        static::assertEquals($testGisLayerCategory1, $testGisLayer7->getCategory());

        $updateData = [
            'included' => [
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory1->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory1->getName(),
                        'treeOrder' => 1,
                        'isVisible' => $testGisLayerCategory1->isVisible(),
                        'parentId'  => $testGisLayerCategoryRoot->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory2->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory2->getName(),
                        'treeOrder' => 2,
                        'isVisible' => $testGisLayerCategory2->isVisible(),
                        'parentId'  => $testGisLayerCategory1->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory3->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory3->getName(),
                        'treeOrder' => 3,
                        'isVisible' => $testGisLayerCategory3->isVisible(),
                        'parentId'  => $testGisLayerCategory2->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory4->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory4->getName(),
                        'treeOrder' => 4,
                        'isVisible' => $testGisLayerCategory4->isVisible(),
                        'parentId'  => $testGisLayerCategory3->getId(),
                    ],
                ],
                [
                    'type'          => 'GisLayerCategory',
                    'id'            => $testGisLayerCategory5->getId(),
                    'relationships' => [],
                    'attributes'    => [
                        'name'      => $testGisLayerCategory5->getName(),
                        'treeOrder' => 4,
                        'isVisible' => $testGisLayerCategory5->isVisible(),
                        'parentId'  => $testGisLayerCategory4->getId(),
                    ],
                ],
                [
                    'id'         => $testGisLayer7->getId(),
                    'type'       => 'GisLayer',
                    'attributes' => [
                        'treeOrder'  => 0,
                        'categoryId' => $testGisLayerCategory3->getId(),
                    ],
                ],
                [
                    'id'         => $testGisLayer5->getId(),
                    'type'       => 'GisLayer',
                    'attributes' => [
                        'treeOrder'  => 1,
                        'categoryId' => $testGisLayerCategory3->getId(),
                    ],
                ],
            ],
        ];

        $this->sut->updateElementsOfRootCategory($updateData);

        $updatedRoot = $this->sut->getGisLayerCategory($testGisLayerCategoryRoot->getId());
        $updatedGisLayerCategory1 = $this->sut->getGisLayerCategory($testGisLayerCategory1->getId());
        $updatedGisLayerCategory2 = $this->sut->getGisLayerCategory($testGisLayerCategory2->getId());
        $updatedGisLayerCategory3 = $this->sut->getGisLayerCategory($testGisLayerCategory3->getId());
        $updatedGisLayerCategory4 = $this->sut->getGisLayerCategory($testGisLayerCategory4->getId());
        $updatedGisLayerCategory5 = $this->sut->getGisLayerCategory($testGisLayerCategory5->getId());
        $updatedGisLayerCategory6 = $this->sut->getGisLayerCategory($testGisLayerCategory6->getId());

        // check for changed parent/children structure of categories:
        static::assertNull($updatedRoot->getParent());
        static::assertCount($numberOfChildrenOfRootCategoryBefore - 4, $updatedRoot->getChildren());

        static::assertCount($numberOfGisLayerOfRootCategoryBefore, $updatedRoot->getGisLayers());
        static::assertTrue($updatedRoot->getChildren()->contains($updatedGisLayerCategory1));
        static::assertEquals($updatedGisLayerCategory1->getParentId(), $updatedRoot->getId());

        static::assertCount(1, $updatedGisLayerCategory1->getChildren());
        static::assertTrue($updatedGisLayerCategory1->getChildren()->contains($updatedGisLayerCategory2));
        static::assertEquals($updatedGisLayerCategory2->getParentId(), $updatedGisLayerCategory1->getId());

        static::assertCount(1, $updatedGisLayerCategory2->getChildren());
        static::assertTrue($updatedGisLayerCategory2->getChildren()->contains($updatedGisLayerCategory3));
        static::assertEquals($updatedGisLayerCategory3->getParentId(), $updatedGisLayerCategory2->getId());

        static::assertCount(1, $updatedGisLayerCategory3->getChildren());
        static::assertTrue($updatedGisLayerCategory3->getChildren()->contains($updatedGisLayerCategory4));
        static::assertEquals($updatedGisLayerCategory4->getParentId(), $updatedGisLayerCategory3->getId());

        static::assertCount(1, $updatedGisLayerCategory4->getChildren());
        static::assertTrue($updatedGisLayerCategory4->getChildren()->contains($updatedGisLayerCategory5));
        static::assertEquals($updatedGisLayerCategory5->getParentId(), $updatedGisLayerCategory4->getId());

        static::assertCount(1, $updatedGisLayerCategory5->getChildren());
        static::assertTrue($updatedGisLayerCategory5->getChildren()->contains($updatedGisLayerCategory6));
        static::assertEquals($updatedGisLayerCategory6->getParentId(), $updatedGisLayerCategory5->getId());

        static::assertCount(0, $updatedGisLayerCategory6->getChildren());

        // check for changed Category of Gislayers:
        static::assertEquals($testGisLayerCategory3->getId(), $testGisLayer7->getCategory()->getId());
        static::assertEquals($testGisLayerCategory3->getId(), $testGisLayer5->getCategory()->getId());
        static::assertEquals(1, $testGisLayer5->getTreeOrder());
        static::assertEquals(0, $testGisLayer7->getTreeOrder());
    }

    public function testDeleteGisLayerCategory()
    {
        /** @var GisLayerCategory $testGisLayerCategory1 */
        $testGisLayerCategory1 = $this->fixtures->getReference('testGisLayerCategory4');
        $categoryId = $testGisLayerCategory1->getId();

        $numberOfCategoriesBefore = $this->countEntries(GisLayerCategory::class);

        $success = $this->sut->deleteGisLayerCategory($categoryId);
        static::assertTrue($success);
        static::assertEquals($numberOfCategoriesBefore - 1, $this->countEntries(GisLayerCategory::class));
        $gisLayerCategories = $this->getEntries(GisLayerCategory::class, ['id' => $categoryId]);
        static::assertEmpty($gisLayerCategories);
    }

    public function testDenyDeleteGisLayerCategory()
    {
        /** @var GisLayerCategory $testGisLayerCategory2 */
        $testGisLayerCategory2 = $this->fixtures->getReference('testGisLayerCategoryRoot');
        $categoryId = $testGisLayerCategory2->getId();
        $numberOfCategoriesBefore = $this->countEntries(GisLayerCategory::class);

        $success = $this->sut->deleteGisLayerCategory($categoryId);
        static::assertFalse($success);
        static::assertEquals($numberOfCategoriesBefore, $this->countEntries(GisLayerCategory::class));
    }

    public function testNewRootGisLayerCategory()
    {
        $procedure = $this->fixtures->getReference('testProcedure2');
        $data = [
            'name'        => 'neuer RootGisLayer Name',
            'procedureId' => $procedure->getId(),
        ];

        $gisLayerCategory = $this->sut->addGisLayerCategory($data);
        $gisLayerCategory = $this->sut->getGisLayerCategory($gisLayerCategory->getId());

        static::assertInstanceOf(GisLayerCategory::class, $gisLayerCategory);
        static::assertNull($gisLayerCategory->getParent());
        static::assertEmpty($gisLayerCategory->getChildren());
        static::assertEquals($data['name'], $gisLayerCategory->getName());
    }

    public function testNewGisLayerCategory()
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var GisLayerCategory $category */
        $category = $this->fixtures->getReference('testGisLayerCategoryRoot');

        $data = [
            'name'        => 'neuer GisLayer Name',
            'procedureId' => $procedure->getId(),
            'parentId'    => $category->getId(),
        ];

        $gisLayerCategory = $this->sut->addGisLayerCategory($data);
        $gisLayerCategory = $this->sut->getGisLayerCategory($gisLayerCategory->getId());

        static::assertInstanceOf(GisLayerCategory::class, $gisLayerCategory);
        static::assertNotNull($gisLayerCategory->getParent());
        static::assertInstanceOf(GisLayerCategory::class, $gisLayerCategory->getParent());
        static::assertEmpty($gisLayerCategory->getChildren());
        static::assertEquals($data['name'], $gisLayerCategory->getName());
    }

    public function testDenyNewGisLayerCategoryWithoutName()
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var GisLayerCategory $category */
        $category = $this->fixtures->getReference('testGisLayerCategoryRoot');

        $data = [
            'name'        => '',
            'procedureId' => $procedure->getId(),
            'parentId'    => $category->getId(),
        ];

        $this->sut->addGisLayerCategory($data);
    }

    public function testUpdateGisLayerCategoryArray()
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var GisLayerCategory $gisLayerCategory */
        $gisLayerCategory = $this->fixtures->getReference('testGisLayerCategory2');

        static::assertFalse($gisLayerCategory->isRoot());
        $parentBefore = $gisLayerCategory->getParent();

        $data = [
            'id'          => $gisLayerCategory->getId(),
            'name'        => 'neuer GisLayer Name updated',
            'procedureId' => $procedure->getId(),
        ];

        static::assertNotEquals($data['name'], $gisLayerCategory->getName());

        $gisLayerCategory = $this->sut->updateGisLayerCategory($data['id'], $data);
        static::assertInstanceOf(GisLayerCategory::class, $gisLayerCategory);
        static::assertEquals($data['name'], $gisLayerCategory->getName());
        static::assertFalse($gisLayerCategory->isRoot());
        static::assertEquals($parentBefore, $gisLayerCategory->getParent());
    }

    public function testUpdateGisLayerRootCategoryArray()
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var GisLayerCategory $gisLayerCategory */
        $gisLayerCategory = $this->fixtures->getReference('testGisLayerCategoryRoot');
        static::assertTrue($gisLayerCategory->isRoot());

        $data = [
            'id'          => $gisLayerCategory->getId(),
            'name'        => 'neuer GisLayer Name updated',
            'procedureId' => $procedure->getId(),
        ];

        static::assertNotEquals($data['name'], $gisLayerCategory->getName());

        $gisLayerCategory = $this->sut->updateGisLayerCategory($data['id'], $data);
        static::assertInstanceOf(GisLayerCategory::class, $gisLayerCategory);
        static::assertEquals($data['name'], $gisLayerCategory->getName());
        static::assertTrue($gisLayerCategory->isRoot());
    }

    public function testGetVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('invisibleGisLayer2');
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        static::assertEquals($gisLayer1->getVisibilityGroupId(), $gisLayer2->getVisibilityGroupId());
        static::assertFalse($gisLayer1->hasDefaultVisibility());
        static::assertFalse($gisLayer2->hasDefaultVisibility());

        $gisLayersOfGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(2, $gisLayersOfGroup);
    }

    public function testAddGisLayerToVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();
        static::assertNotNull($visibilityGroupId);

        $numberOfMemberBefore = count($this->sut->getVisibilityGroup($visibilityGroupId));

        /** @var GisLayer $gisLayer1 */
        $gisLayer3 = $this->fixtures->getReference('testGisLayer3');
        static::assertNull($gisLayer3->getVisibilityGroupId());

        $updatedGisLayer = $this->sut->updateGisLayer($gisLayer3->getId(), ['visibilityGroupId' => $visibilityGroupId], false);
        $updatedGisLayer = $this->sut->getGisLayer($updatedGisLayer->getId());
        static::assertEquals($visibilityGroupId, $updatedGisLayer->getVisibilityGroupId());
        static::assertCount($numberOfMemberBefore + 1, $this->sut->getVisibilityGroup($visibilityGroupId));
    }

    public function testSetVisibilityOfVisibilityGroup()
    {
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('invisibleGisLayer2');

        // set default visibility of gisLayer1 to true
        $gisLayer1->setDefaultVisibility(true);
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        static::assertEquals($visibilityGroupId, $gisLayer1->getVisibilityGroupId());
        static::assertEquals($visibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertTrue($gisLayer1->hasDefaultVisibility());
        static::assertFalse($gisLayer2->hasDefaultVisibility());

        $successful = $this->sut->setVisibilityOfVisibilityGroup(
            $visibilityGroupId,
            [
                'defaultVisibility' => $gisLayer1->hasDefaultVisibility(),
                'procedureId'       => $gisLayer2->getProcedureId(),
            ]
        );
        static::assertTrue($successful);

        $gisLayer2 = $this->sut->getGisLayer($gisLayer2->getId());

        // default visibility of gisLayer2 was updated via visibilityGroup und has to be true
        static::assertTrue($gisLayer2->hasDefaultVisibility());
    }

    public function testSetVisibilityOfGroupAllMember()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('invisibleGisLayer2');
        /** @var GisLayer $gisLayer2 */
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        static::assertEquals($visibilityGroupId, $gisLayer1->getVisibilityGroupId());
        static::assertEquals($visibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertFalse($gisLayer1->hasDefaultVisibility());
        static::assertFalse($gisLayer2->hasDefaultVisibility());

        $data = [
            'type'              => 'overlay',
            'name'              => 'globale testkarte',
            'url'               => 'http://www.globaletestkarte.de',
            'Layer'             => '0',
            'procedureId'       => '',
            'globalGisId'       => null,
            'defaultVisibility' => true,
        ];

        $updatedGisLayer = $this->sut->updateGisLayer($gisLayer1->getId(), $data, false);
        $updatedGisLayer = $this->sut->getGisLayer($updatedGisLayer->getId());
        $gisLayer2 = $this->sut->getGisLayer($gisLayer2->getId());

        static::assertTrue($updatedGisLayer->hasDefaultVisibility());
        static::assertEquals($visibilityGroupId, $updatedGisLayer->getVisibilityGroupId());

        // default visibility of gisLayer2 was updated indirectly via visibilityGroup
        static::assertTrue($gisLayer2->hasDefaultVisibility());
        static::assertEquals($visibilityGroupId, $gisLayer2->getVisibilityGroupId());
    }

    public function testCreateVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('testGisLayer4');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('testGisLayer2');
        /** @var GisLayer $gisLayer3 */
        $gisLayer3 = $this->fixtures->getReference('testGisLayer3');

        // check setup
        static::assertNull($gisLayer1->getVisibilityGroupId());
        static::assertNull($gisLayer2->getVisibilityGroupId());
        static::assertNull($gisLayer3->getVisibilityGroupId());
        static::assertFalse($gisLayer1->isBaseLayer());
        static::assertFalse($gisLayer2->isBaseLayer());
        static::assertFalse($gisLayer3->isBaseLayer());

        $newVisibilityGroupId = 'b7f0ad69-b2f5-4045-8c1a-11111111test';

        $this->sut->updateGisLayer($gisLayer1->getId(), ['visibilityGroupId' => $newVisibilityGroupId], false);
        $this->sut->updateGisLayer($gisLayer2->getId(), ['visibilityGroupId' => $newVisibilityGroupId], false);
        $this->sut->updateGisLayer($gisLayer3->getId(), ['visibilityGroupId' => $newVisibilityGroupId], false);

        $gisLayer1 = $this->sut->getGisLayer($gisLayer1->getId());
        $gisLayer2 = $this->sut->getGisLayer($gisLayer2->getId());
        $gisLayer3 = $this->sut->getGisLayer($gisLayer3->getId());

        static::assertEquals($newVisibilityGroupId, $gisLayer1->getVisibilityGroupId());
        static::assertEquals($newVisibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertEquals($newVisibilityGroupId, $gisLayer3->getVisibilityGroupId());

        $gisLayersOfGroup = $this->sut->getVisibilityGroup($newVisibilityGroupId);

        static::assertCount(3, $gisLayersOfGroup);
        static::assertContains($gisLayer1, $gisLayersOfGroup);
        static::assertContains($gisLayer2, $gisLayersOfGroup);
        static::assertContains($gisLayer3, $gisLayersOfGroup);

        // check site of gisLayers:
        static::assertEquals($newVisibilityGroupId, $gisLayer1->getVisibilityGroupId());
        static::assertEquals($newVisibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertEquals($newVisibilityGroupId, $gisLayer3->getVisibilityGroupId());
    }

    public function testSetGisLayerToVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(2, $visibilityGroup);

        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('testGisLayer4');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('testGisLayer2');
        /** @var GisLayer $gisLayer3 */
        $gisLayer3 = $this->fixtures->getReference('testGisLayer3');
        static::assertNull($gisLayer1->getVisibilityGroupId());
        static::assertNull($gisLayer2->getVisibilityGroupId());
        static::assertNull($gisLayer3->getVisibilityGroupId());
        static::assertFalse($gisLayer1->isBaseLayer());
        static::assertFalse($gisLayer2->isBaseLayer());
        static::assertFalse($gisLayer3->isBaseLayer());

        $this->sut->updateGisLayer($gisLayer1->getId(), ['visibilityGroupId' => $visibilityGroupId], false);
        $this->sut->updateGisLayer($gisLayer2->getId(), ['visibilityGroupId' => $visibilityGroupId], false);
        $this->sut->updateGisLayer($gisLayer3->getId(), ['visibilityGroupId' => $visibilityGroupId], false);

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(5, $visibilityGroup);
        static::assertEquals($visibilityGroupId, $gisLayer1->getVisibilityGroupId());
        static::assertEquals($visibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertEquals($visibilityGroupId, $gisLayer3->getVisibilityGroupId());
    }

    public function testUnSetGisLayerToVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('invisibleGisLayer2');
        static::assertNotNull($gisLayer1->getVisibilityGroupId());
        static::assertNotNull($gisLayer2->getVisibilityGroupId());
        static::assertEquals($gisLayer1->getVisibilityGroupId(), $gisLayer2->getVisibilityGroupId());
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(2, $visibilityGroup);

        $this->sut->updateGisLayer($gisLayer1->getId(), ['visibilityGroupId' => null]);

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(1, $visibilityGroup);
        static::assertEquals($visibilityGroupId, $gisLayer2->getVisibilityGroupId());
        static::assertNull($gisLayer1->getVisibilityGroupId());
    }

    public function testDeleteVisibilityGroup()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        /** @var GisLayer $gisLayer2 */
        $gisLayer2 = $this->fixtures->getReference('invisibleGisLayer2');
        static::assertNotNull($gisLayer1->getVisibilityGroupId());
        static::assertNotNull($gisLayer2->getVisibilityGroupId());
        static::assertEquals($gisLayer1->getVisibilityGroupId(), $gisLayer2->getVisibilityGroupId());
        $visibilityGroupId = $gisLayer1->getVisibilityGroupId();

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        static::assertCount(2, $visibilityGroup);

        // unset All:
        foreach ($visibilityGroup as $gisLayer) {
            $this->sut->updateGisLayer($gisLayer->getId(), ['visibilityGroupId' => null]);
        }

        $visibilityGroup = $this->sut->getVisibilityGroup($visibilityGroupId);
        self::assertEmpty($visibilityGroup);
    }

    public function testUnsetVisibilityGroupOnSetUserToggleVisibility()
    {
        $this->expectException(FunctionalLogicException::class);

        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');

        // check setup:
        static::assertNotNull($gisLayer1->getVisibilityGroupId());
        static::assertTrue($gisLayer1->getUserToggleVisibility());

        $this->sut->updateGisLayer($gisLayer1->getId(), ['userToggleVisibility' => false]);
    }

    public function testSetVisibilityGroup()
    {
        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        // check setup:
        static::assertNotNull($gisLayer1->getVisibilityGroupId());
        static::assertTrue($gisLayer1->getUserToggleVisibility());

        $this->sut->updateGisLayer($gisLayer1->getId(),
            ['visibilityGroupId' => '123456789012345678901234567890123456']);
        $gisLayer1 = $this->sut->getGisLayer($gisLayer1->getId());

        static::assertEquals('123456789012345678901234567890123456', $gisLayer1->getVisibilityGroupId());
        static::assertTrue($gisLayer1->getUserToggleVisibility());
    }

    public function testDenyUnSetOfUserToggleVisibility()
    {
        $this->expectException(FunctionalLogicException::class);

        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');

        $this->sut->updateGisLayer($gisLayer1->getId(), ['userToggleVisibility' => false]);
        $gisLayer1 = $this->sut->getGisLayer($gisLayer1->getId());
        static::assertFalse($gisLayer1->canUserToggleVisibility());
        // gisLayer is Member of visibilityGroup
        static::assertNotNull($gisLayer1->getVisibilityGroupId());

        $this->sut->updateGisLayer($gisLayer1->getId(),
            ['visibilityGroupId' => '123456789012345678901234567890123456']);
        $gisLayer1 = $this->sut->getGisLayer($gisLayer1->getId());

        static::assertFalse($gisLayer1->getUserToggleVisibility());
        static::assertNull($gisLayer1->getVisibilityGroupId());
    }

    public function testDenySetVisibilityGroupIdOnBaseLayer()
    {
        $this->expectException(FunctionalLogicException::class);

        /** @var GisLayer $gisLayer1 */
        $gisLayer1 = $this->fixtures->getReference('testGisLayer1');
        static::assertTrue($gisLayer1->isBaseLayer());

        // set visibilityGroupId to BaseLayer is not allowed
        $this->sut->updateGisLayer($gisLayer1->getId(),
            ['visibilityGroupId' => '123456789012345678901234567890123456']);
    }

    public function testUpdateGlobalGis()
    {
        $data = [
            'type'        => 'base',
            'name'        => 'globale testkarte',
            'url'         => 'http://www.globaletestkarte.de',
            'Layer'       => '0',
            'procedureId' => '',
            'globalGisId' => null,
        ];
        $globalLayer = $this->sut->addGis($data);

        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);

        $data['type'] = 'overlay';
        $data['ident'] = $globalLayer['ident'];
        $data['id'] = $data['ident'];
        $this->sut->updateGis($data);

        $numberOfEntriesAfter = $this->countEntries(GisLayer::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        $udpatedGlobalGis = $this->sut->getSingleGis($globalLayer['ident']);
        static::assertEquals($udpatedGlobalGis['type'], $data['type']);
        static::assertEquals($udpatedGlobalGis['name'], $data['name']);
        static::assertEquals($udpatedGlobalGis['url'], $data['url']);

        static::assertEquals($udpatedGlobalGis['pId'], $data['procedureId']);
        static::assertEquals($udpatedGlobalGis['gId'], $data['globalGisId']);

        $listOfUpdated = $this->doctrine->getManager()->getRepository(
            GisLayer::class
        )->findBy(['gId' => $globalLayer['ident']]);
        foreach ($listOfUpdated as $update) {
            static::assertEquals($update->getType(), $data['type']);
            static::assertEquals($update->getName(), $data['name']);
            static::assertEquals($update->getUrl(), $data['url']);
            static::assertNotEquals(
                $update->getProcedureId(),
                $data['procedureId']
            );
            static::assertNotEquals(
                $update->getGlobalLayerId(),
                $data['globalGisId']
            );
        }
    }

    public function testUpdateGis()
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');

        $data = [];
        $data['name'] = 'updatedName';
        $data['legend'] = '';
        $data['layers'] = '';
        $data['opacity'] = 100;
        $data['order'] = 0;
        $data['procedureId'] = $testProcedure2->getId();
        $data['type'] = 'overlay';
        $data['url'] = 'http://www.landschaft.de';
        $data['gId'] = null;
        $data['visible'] = false;
        $data['serviceType'] = 'wms';
        $data['capabilities'] = 'capabilities';
        $data['tileMatrixSet'] = 'tileMatrixSet';

        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);

        $updatedGisLayer = $this->sut->updateGis($data);
        $updatedGisLayer2 = $this->sut->getSingleGis($updatedGisLayer['ident']);

        static::assertEquals($this->countEntries(GisLayer::class), $numberOfEntriesBefore + 1);
        static::assertTrue(is_array($updatedGisLayer));
        $this->checkId($updatedGisLayer['ident']);
        static::assertTrue(is_array($updatedGisLayer2));
        static::assertArrayHasKey('name', $updatedGisLayer);
        static::assertArrayHasKey('name', $updatedGisLayer2);
        static::assertEquals($updatedGisLayer, $updatedGisLayer2);
        static::assertEquals($updatedGisLayer2['name'], $updatedGisLayer['name']);

        $this->checkArrayStructure($updatedGisLayer);

        static::assertTrue($this->isCurrentTimestamp($updatedGisLayer['createdate']));
        static::assertTrue($this->isCurrentTimestamp($updatedGisLayer['deletedate']));
        static::assertTrue($this->isCurrentTimestamp($updatedGisLayer['modifydate']));
        static::assertEquals($data['name'], $updatedGisLayer['name']);
        static::assertEquals($data['legend'], $updatedGisLayer['legend']);
        static::assertEquals($data['layers'], $updatedGisLayer['layers']);
        static::assertEquals($data['opacity'], $updatedGisLayer['opacity']);
        static::assertEquals($data['order'], $updatedGisLayer['order']);
        static::assertEquals($data['procedureId'], $updatedGisLayer['pId']);
        static::assertEquals($data['type'], $updatedGisLayer['type']);
        static::assertEquals($data['url'], $updatedGisLayer['url']);
        static::assertEquals($data['gId'], $updatedGisLayer['gId']);
        static::assertEquals($data['serviceType'], $updatedGisLayer['serviceType']);
        static::assertEquals($data['capabilities'], $updatedGisLayer['capabilities']);
        static::assertEquals($data['tileMatrixSet'], $updatedGisLayer['tileMatrixSet']);
        static::assertFalse($updatedGisLayer['print']);
        static::assertFalse($updatedGisLayer['scope']);
        static::assertEquals(0, $updatedGisLayer['globalLayer']);
        static::assertFalse($updatedGisLayer['xplan']);
        static::assertFalse($updatedGisLayer['bplan']);
        static::assertFalse($updatedGisLayer['default']);
        static::assertFalse($updatedGisLayer['deleted']);
        static::assertFalse($updatedGisLayer['visible']);

        $referenceGis = $this->fixtures->getReference('testGisLayer1');
        $data['id'] = $referenceGis->getIdent();
        unset($data['opacity']);
        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);
        $updatedGisLayer = $this->sut->updateGis($data);
        static::assertNotNull($updatedGisLayer);
        static::assertTrue(is_array($updatedGisLayer));

        static::assertEquals($numberOfEntriesBefore, $this->countEntries(GisLayer::class));
        static::assertEquals($referenceGis->getIdent(), $updatedGisLayer['ident']);
        static::assertEquals($referenceGis->getOpacity(), $updatedGisLayer['opacity']);

        $this->checkArrayStructure($updatedGisLayer);

        static::assertNotEquals($updatedGisLayer['createdate'], $updatedGisLayer['modifydate']);
        static::assertEquals($updatedGisLayer['createdate'], $updatedGisLayer['deletedate']);
        static::assertTrue($this->isCurrentTimestamp($updatedGisLayer['modifydate']));

        static::assertEquals($data['name'], $updatedGisLayer['name']);
        static::assertEquals($data['legend'], $updatedGisLayer['legend']);
        static::assertEquals($data['layers'], $updatedGisLayer['layers']);
        static::assertEquals($data['order'], $updatedGisLayer['order']);
        static::assertEquals($data['procedureId'], $updatedGisLayer['pId']);
        static::assertEquals($data['type'], $updatedGisLayer['type']);
        static::assertEquals($data['url'], $updatedGisLayer['url']);
        static::assertEquals($data['gId'], $updatedGisLayer['gId']);

        static::assertFalse($updatedGisLayer['print']);
        static::assertFalse($updatedGisLayer['scope']);
        static::assertEquals(0, $updatedGisLayer['globalLayer']);
        static::assertFalse($updatedGisLayer['xplan']);
        static::assertFalse($updatedGisLayer['bplan']);
        static::assertFalse($updatedGisLayer['default']);
        static::assertFalse($updatedGisLayer['deleted']);
        static::assertFalse($updatedGisLayer['visible']);
    }

    /**
     * @param array $layer
     */
    protected function checkArrayStructure($layer)
    {
        static::assertArrayHasKey('ident', $layer);
        $this->checkId($layer['ident']);
        static::assertArrayHasKey('name', $layer);
        static::assertTrue(is_string($layer['name']));
        static::assertArrayHasKey('type', $layer);
        static::assertTrue(is_string($layer['type']));
        static::assertArrayHasKey('url', $layer);
        static::assertTrue(is_string($layer['url']));
        static::assertArrayHasKey('layers', $layer);
        static::assertTrue(is_string($layer['layers']));
        static::assertArrayHasKey('legend', $layer);
        static::assertArrayHasKey('opacity', $layer);
        static::assertTrue(is_int($layer['opacity']));
        static::assertArrayHasKey('bplan', $layer);
        static::assertTrue(is_bool($layer['bplan']));
        static::assertArrayHasKey('xplan', $layer);
        static::assertTrue(is_bool($layer['xplan']));
        static::assertArrayHasKey('print', $layer);
        static::assertTrue(is_bool($layer['print']));
        static::assertArrayHasKey('deleted', $layer);
        static::assertTrue(is_bool($layer['deleted']));
        static::assertArrayHasKey('visible', $layer);
        static::assertTrue(is_bool($layer['visible']));
        static::assertArrayHasKey('createdate', $layer);
        static::assertTrue(is_numeric($layer['createdate']));
        static::assertTrue(0 < $layer['createdate']);
        static::assertArrayHasKey('modifydate', $layer);
        static::assertTrue(is_numeric($layer['modifydate']));
        static::assertTrue(0 < $layer['modifydate']);
        static::assertArrayHasKey('order', $layer);
        static::assertTrue(is_int($layer['order']));
        static::assertArrayHasKey('scope', $layer);
        static::assertTrue(is_bool($layer['scope']));
        static::assertArrayHasKey('default', $layer);
        static::assertTrue(is_bool($layer['default']));
        static::assertArrayHasKey('pId', $layer);
        $this->checkId($layer['pId']);
        static::assertArrayHasKey('serviceType', $layer);
        static::assertTrue(is_string($layer['serviceType']));
        static::assertArrayHasKey('capabilities', $layer);
        static::assertTrue(
            is_string($layer['capabilities']) || is_null($layer['capabilities'])
        );
        static::assertArrayHasKey('tileMatrixSet', $layer);
        static::assertTrue(
            is_string($layer['tileMatrixSet']) || is_null(
                $layer['tileMatrixSet']
            )
        );
    }
}
