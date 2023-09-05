<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadMapData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $gisLayerCategoryRoot = new GisLayerCategory();
        $gisLayerCategoryRoot->setName('testGisLayerCategoryRoot');
        $gisLayerCategoryRoot->setProcedure($this->getReference('testProcedure2'));
        $manager->persist($gisLayerCategoryRoot);
        $manager->flush();
        $this->setReference('testGisLayerCategoryRoot', $gisLayerCategoryRoot);

        $gisLayer1 = new GisLayer();
        $gisLayer1->setBplan(false);
        $gisLayer1->setDefaultVisibility(false);
        $gisLayer1->setDeleted(false);
        $gisLayer1->setLegend('legende_2015.pdf:8299a35b-5414-4739-9024-1aa1ab4f62f5:481309:application/pdf');
        $gisLayer1->setLayers('0,1,2,3,4,5,6');
        $gisLayer1->setName('TestKarte1');
        $gisLayer1->setOpacity('100');
        $gisLayer1->setOrder(0);
        $gisLayer1->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer1->setCategory($gisLayerCategoryRoot);
        $gisLayer1->setPrint(false);
        $gisLayer1->setScope(false);
        $gisLayer1->setType('base');
        $gisLayer1->setUrl('http://www.testurl.de');
        $gisLayer1->setEnabled(true);
        $gisLayer1->setGlobalLayer(false);
        $gisLayer1->setXplan(false);
        $manager->persist($gisLayer1);

        $gisLayer2 = new GisLayer();
        $gisLayer2->setBplan(false);
        $gisLayer2->setDefaultVisibility(false);
        $gisLayer2->setDeleted(false);
        $gisLayer2->setLegend('');
        $gisLayer2->setLayers('0');
        $gisLayer2->setName('TestKarte2');
        $gisLayer2->setOpacity('50');
        $gisLayer2->setOrder(1);
        $gisLayer2->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer2->setCategory($gisLayerCategoryRoot);
        $gisLayer2->setPrint(false);
        $gisLayer2->setScope(false);
        $gisLayer2->setType('overlay');
        $gisLayer2->setUrl('http://www.testurl.de');
        $gisLayer2->setEnabled(true);
        $gisLayer2->setGlobalLayer(false);
        $gisLayer2->setXplan(false);
        $manager->persist($gisLayer2);

        $gisLayer3 = new GisLayer();
        $gisLayer3->setBplan(false);
        $gisLayer3->setDefaultVisibility(false);
        $gisLayer3->setDeleted(false);
        $gisLayer3->setLegend('');
        $gisLayer3->setLayers('0');
        $gisLayer3->setName('TestKarte3');
        $gisLayer3->setOpacity('50');
        $gisLayer3->setOrder(0);
        $gisLayer3->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer3->setCategory($gisLayerCategoryRoot);
        $gisLayer3->setPrint(false);
        $gisLayer3->setScope(false);
        $gisLayer3->setType('overlay');
        $gisLayer3->setUrl('http://www.testurl.de');
        $gisLayer3->setEnabled(true);
        $gisLayer3->setXplan(false);
        $manager->persist($gisLayer3);

        $globalGisLayer1 = new GisLayer();
        $globalGisLayer1->isGlobalLayer();
        $globalGisLayer1->setBplan(false);
        $globalGisLayer1->setDefaultVisibility(false);
        $globalGisLayer1->setDeleted(false);
        $globalGisLayer1->setLegend('');
        $globalGisLayer1->setLayers('0');
        $globalGisLayer1->setName('TestGlobal');
        $globalGisLayer1->setOpacity('100');
        $globalGisLayer1->setOrder(2);
        $globalGisLayer1->setPrint(false);
        $globalGisLayer1->setScope(false);
        $globalGisLayer1->setType('global');
        $globalGisLayer1->setUrl('http://www.testurl.de');
        $globalGisLayer1->setEnabled(true);
        $globalGisLayer1->setXplan(false);
        $manager->persist($globalGisLayer1);

        $gisLayer4 = new GisLayer();
        $gisLayer4->setBplan(false);
        $gisLayer4->setDefaultVisibility(false);
        $gisLayer4->setDeleted(false);
        $gisLayer4->setLegend('');
        $gisLayer4->setLayers('0');
        $gisLayer4->setName('TestHasGlobal');
        $gisLayer4->setCategory($gisLayerCategoryRoot);
        $gisLayer4->setOpacity('100');
        $gisLayer4->setOrder(2);
        $gisLayer4->setPrint(false);
        $gisLayer4->setScope(false);
        $gisLayer4->setType('overlay');
        $gisLayer4->setUrl('http://www.testurl.de');
        $gisLayer4->setGlobalLayerId($globalGisLayer1->getId());
        $gisLayer4->setEnabled(true);
        $gisLayer4->setXplan(false);
        $gisLayer4->setProcedureId($this->getReference('testProcedure2')->getId());
        $manager->persist($gisLayer4);

        $gisLayer5 = new GisLayer();
        $gisLayer5->setBplan(false);
        $gisLayer5->setDefaultVisibility(false);
        $gisLayer5->setDeleted(false);
        $gisLayer5->setLegend('');
        $gisLayer5->setLayers('0');
        $gisLayer5->setName('TestKarte5');
        $gisLayer5->setOpacity('50');
        $gisLayer5->setOrder(1);
        $gisLayer5->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer5->setCategory($gisLayerCategoryRoot);
        $gisLayer5->setPrint(false);
        $gisLayer5->setScope(false);
        $gisLayer5->setType('overlay');
        $gisLayer5->setUrl('http://www.testurl.de');
        $gisLayer5->setEnabled(true);
        $gisLayer5->setGId($gisLayer4->getIdent());
        $gisLayer5->setGlobalLayer(false);
        $gisLayer5->setXplan(false);
        $gisLayer5->setContextualHelp($this->getReference('testContextualHelp'));
        $manager->persist($gisLayer5);

        $gisLayer6 = new GisLayer();
        $gisLayer6->setBplan(false);
        $gisLayer6->setDefaultVisibility(false);
        $gisLayer6->setDeleted(false);
        $gisLayer6->setLegend('');
        $gisLayer6->setLayers('0');
        $gisLayer6->setName('TestKarte6');
        $gisLayer6->setOpacity('50');
        $gisLayer6->setOrder(1);
        $gisLayer6->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer6->setCategory($gisLayerCategoryRoot);
        $gisLayer6->setPrint(false);
        $gisLayer6->setScope(false);
        $gisLayer6->setType('overlay');
        $gisLayer6->setUrl('http://www.testurl.de');
        $gisLayer6->setEnabled(true);
        $gisLayer6->setGId($gisLayer4->getIdent());
        $gisLayer6->setGlobalLayer(false);
        $gisLayer6->setXplan(false);
        $manager->persist($gisLayer6);

        $gisLayer7 = new GisLayer();
        $gisLayer7->setBplan(false);
        $gisLayer7->setDefaultVisibility(false);
        $gisLayer7->setDeleted(false);
        $gisLayer7->setLegend('');
        $gisLayer7->setLayers('0');
        $gisLayer7->setName('TestKarte7');
        $gisLayer7->setOpacity('70');
        $gisLayer7->setOrder(1);
        $gisLayer7->setProcedureId($this->getReference('testProcedure2')->getId());
        $gisLayer7->setCategory($gisLayerCategoryRoot);
        $gisLayer7->setPrint(false);
        $gisLayer7->setScope(false);
        $gisLayer7->setType('overlay');
        $gisLayer7->setUrl('http://www.testurl.de');
        $gisLayer7->setEnabled(true);
        $gisLayer7->setGId($gisLayer4->getIdent());
        $gisLayer7->setGlobalLayer(false);
        $gisLayer7->setXplan(false);
        $manager->persist($gisLayer7);

        $gisLayerCategory1 = new GisLayerCategory();
        $gisLayerCategory1->setName('testGisLayerCategory1');
        $gisLayerCategory1->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory1->setParent($gisLayerCategoryRoot);
        $gisLayerCategory1->setGisLayers([$gisLayer5, $gisLayer7]);
        $gisLayer5->setCategory($gisLayerCategory1);
        $gisLayer7->setCategory($gisLayerCategory1);

        $manager->persist($gisLayerCategory1);
        $manager->persist($gisLayer7);
        $manager->persist($gisLayer5);

        $gisLayerCategory2 = new GisLayerCategory();
        $gisLayerCategory2->setName('testGisLayerCategory2');
        $gisLayerCategory2->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory2->setParent($gisLayerCategoryRoot);
        $manager->persist($gisLayerCategory2);

        $gisLayerCategory3 = new GisLayerCategory();
        $gisLayerCategory3->setName('testGisLayerCategory3');
        $gisLayerCategory3->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory3->setParent($gisLayerCategoryRoot);
        $manager->persist($gisLayerCategory3);

        $gisLayerCategory4 = new GisLayerCategory();
        $gisLayerCategory4->setName('testGisLayerCategory4');
        $gisLayerCategory4->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory4->setParent($gisLayerCategoryRoot);
        $manager->persist($gisLayerCategory4);

        $gisLayerCategory5 = new GisLayerCategory();
        $gisLayerCategory5->setName('testGisLayerCategory5');
        $gisLayerCategory5->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory5->setParent($gisLayerCategoryRoot);
        $manager->persist($gisLayerCategory5);

        $gisLayerCategory6 = new GisLayerCategory();
        $gisLayerCategory6->setName('testGisLayerCategory6');
        $gisLayerCategory6->setProcedure($this->getReference('testProcedure2'));
        $gisLayerCategory6->setParent($gisLayerCategory5);
        $manager->persist($gisLayerCategory6);

        $this->loadDataForTestVisibilityGroup($manager);
        $this->loadDataForTestCopyGisLayerCategoriesOnCreateProcedure($manager);

        $manager->flush();
        $this->setReference('testGisLayer1', $gisLayer1);
        $this->setReference('testGisLayer2', $gisLayer2);
        $this->setReference('testGisLayer3', $gisLayer3);
        $this->setReference('testGisLayer4', $gisLayer4);
        $this->setReference('testGisLayer5', $gisLayer5);
        $this->setReference('testGisLayer6', $gisLayer6);
        $this->setReference('testGisLayer7', $gisLayer7);
        $this->setReference('testGisLayerCategoryRoot', $gisLayerCategoryRoot);
        $this->setReference('testGisLayerCategory1', $gisLayerCategory1);
        $this->setReference('testGisLayerCategory2', $gisLayerCategory2);
        $this->setReference('testGisLayerCategory3', $gisLayerCategory3);
        $this->setReference('testGisLayerCategory4', $gisLayerCategory4);
        $this->setReference('testGisLayerCategory5', $gisLayerCategory5);
        $this->setReference('testGisLayerCategory6', $gisLayerCategory6);
        $this->setReference('testGlobalGisLayer1', $globalGisLayer1);
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }

    private function loadDataForTestVisibilityGroup(ObjectManager $manager)
    {
        $testProcedure2Id = $this->getReference('testProcedure2')->getId();
        $gisLayerCategoryRoot = $this->getReference('testGisLayerCategoryRoot');

        $invisibleGisLayer1 = new GisLayer();
        $invisibleGisLayer1->setBplan(false);
        $invisibleGisLayer1->setDefaultVisibility(false);
        $invisibleGisLayer1->setDeleted(false);
        $invisibleGisLayer1->setLegend('');
        $invisibleGisLayer1->setLayers('0');
        $invisibleGisLayer1->setName('testInvisibleGisLayer1');
        $invisibleGisLayer1->setOpacity('50');
        $invisibleGisLayer1->setOrder(0);
        $invisibleGisLayer1->setProcedureId($testProcedure2Id);
        $invisibleGisLayer1->setCategory($gisLayerCategoryRoot);
        $invisibleGisLayer1->setPrint(false);
        $invisibleGisLayer1->setScope(false);
        $invisibleGisLayer1->setType('overlay');
        $invisibleGisLayer1->setUrl('http://www.testurl.de');
        $invisibleGisLayer1->setEnabled(false);
        $invisibleGisLayer1->setXplan(false);
        $invisibleGisLayer1->setVisibilityGroupId('b7f0ad69-b2f5-4045-8c1a-111111111111');
        $manager->persist($invisibleGisLayer1);

        $invisibleGisLayer2 = new GisLayer();
        $invisibleGisLayer2->setBplan(false);
        $invisibleGisLayer2->setDefaultVisibility(false);
        $invisibleGisLayer2->setDeleted(false);
        $invisibleGisLayer2->setLegend('');
        $invisibleGisLayer2->setLayers('0');
        $invisibleGisLayer2->setName('testInvisibleGisLayer2');
        $invisibleGisLayer2->setOpacity('100');
        $invisibleGisLayer2->setOrder(2);
        $invisibleGisLayer2->setProcedureId($this->getReference('testProcedure2')->getId());
        $invisibleGisLayer2->setCategory($gisLayerCategoryRoot);
        $invisibleGisLayer2->setPrint(false);
        $invisibleGisLayer2->setScope(false);
        $invisibleGisLayer2->setType('overlay');
        $invisibleGisLayer2->setUrl('http://www.testurl.de');
        $invisibleGisLayer2->setEnabled(false);
        $invisibleGisLayer2->setXplan(false);
        $invisibleGisLayer2->setVisibilityGroupId('b7f0ad69-b2f5-4045-8c1a-111111111111');
        $manager->persist($invisibleGisLayer2);

        $visibleGisLayer1 = new GisLayer();
        $visibleGisLayer1->setBplan(false);
        $visibleGisLayer1->setDefaultVisibility(false);
        $visibleGisLayer1->setDeleted(false);
        $visibleGisLayer1->setLegend('');
        $visibleGisLayer1->setLayers('0');
        $visibleGisLayer1->setName('testVisibleGisLayer1');
        $visibleGisLayer1->setOpacity('50');
        $visibleGisLayer1->setOrder(0);
        $visibleGisLayer1->setProcedureId($this->getReference('testProcedure2')->getId());
        $visibleGisLayer1->setCategory($gisLayerCategoryRoot);
        $visibleGisLayer1->setPrint(false);
        $visibleGisLayer1->setScope(false);
        $visibleGisLayer1->setType('overlay');
        $visibleGisLayer1->setUrl('http://www.testurl.de');
        $visibleGisLayer1->setEnabled(true);
        $visibleGisLayer1->setXplan(false);
        $visibleGisLayer1->setVisibilityGroupId('b7f0ad69-b2f5-4045-8c1a-111111111112');
        $manager->persist($visibleGisLayer1);

        $visibleGisLayer2 = new GisLayer();
        $visibleGisLayer2->setBplan(false);
        $visibleGisLayer2->setDefaultVisibility(false);
        $visibleGisLayer2->setDeleted(false);
        $visibleGisLayer2->setLegend('');
        $visibleGisLayer2->setLayers('0');
        $visibleGisLayer2->setName('testVisibibleGisLayer2');
        $visibleGisLayer2->setOpacity('100');
        $visibleGisLayer2->setOrder(2);
        $visibleGisLayer2->setProcedureId($this->getReference('testProcedure2')->getId());
        $visibleGisLayer2->setCategory($gisLayerCategoryRoot);
        $visibleGisLayer2->setPrint(false);
        $visibleGisLayer2->setScope(false);
        $visibleGisLayer2->setType('overlay');
        $visibleGisLayer2->setUrl('http://www.testurl.de');
        $visibleGisLayer2->setEnabled(true);
        $visibleGisLayer2->setXplan(false);
        $visibleGisLayer2->setVisibilityGroupId('b7f0ad69-b2f5-4045-8c1a-111111111112');
        $manager->persist($visibleGisLayer2);

        $manager->flush();

        $this->setReference('invisibleGisLayer1', $invisibleGisLayer1);
        $this->setReference('invisibleGisLayer2', $invisibleGisLayer2);

        $this->setReference('visibleGisLayer1', $visibleGisLayer1);
        $this->setReference('visibleGisLayer2', $visibleGisLayer2);
    }

    private function loadDataForTestCopyGisLayerCategoriesOnCreateProcedure(ObjectManager $manager)
    {
        /** @var Procedure $masterBlueprint2 */
        $masterBlueprint2 = $this->getReference('masterBlaupause2');

        // lvl0: rootCategory:
        $gisLayerCategory0 = new GisLayerCategory();
        $gisLayerCategory0->setName('root testgisLayerCategory0 for Blaupause2');
        $gisLayerCategory0->setProcedure($masterBlueprint2);
        $manager->persist($gisLayerCategory0);

        // lvl1
        $gisLayerCategory1 = new GisLayerCategory();
        $gisLayerCategory1->setName('testgisLayerCategory1 for Blaupause2');
        $gisLayerCategory1->setProcedure($masterBlueprint2);
        $gisLayerCategory1->setParent($gisLayerCategory0);
        $manager->persist($gisLayerCategory1);

        // lvl2
        $gisLayerCategory2 = new GisLayerCategory();
        $gisLayerCategory2->setName('testgisLayerCategory2 for Blaupause2');
        $gisLayerCategory2->setProcedure($masterBlueprint2);
        $gisLayerCategory2->setParent($gisLayerCategory1);
        $manager->persist($gisLayerCategory2);

        // lvl3
        $gisLayerCategory3 = new GisLayerCategory();
        $gisLayerCategory3->setName('testgisLayerCategory3 for Blaupause2');
        $gisLayerCategory3->setProcedure($masterBlueprint2);
        $gisLayerCategory3->setParent($gisLayerCategory2);
        $manager->persist($gisLayerCategory3);

        // in lvl2:
        $gisLayer8 = new GisLayer();
        $gisLayer8->setBplan(false);
        $gisLayer8->setDefaultVisibility(false);
        $gisLayer8->setDeleted(false);
        $gisLayer8->setLegend('');
        $gisLayer8->setLayers('0');
        $gisLayer8->setName('TestKarte8');
        $gisLayer8->setOpacity('50');
        $gisLayer8->setOrder(1);
        $gisLayer8->setProcedureId($masterBlueprint2->getId());
        $gisLayer8->setPrint(false);
        $gisLayer8->setScope(false);
        $gisLayer8->setType('overlay');
        $gisLayer8->setUrl('http://www.testurl.de');
        $gisLayer8->setEnabled(true);
        $gisLayer8->setGlobalLayer(false);
        $gisLayer8->setXplan(false);
        $gisLayer8->setCategory($gisLayerCategory2);
        $manager->persist($gisLayer8);

        // in lvl3:
        $gisLayer9 = new GisLayer();
        $gisLayer9->setBplan(false);
        $gisLayer9->setDefaultVisibility(false);
        $gisLayer9->setDeleted(false);
        $gisLayer9->setLegend('');
        $gisLayer9->setLayers('0');
        $gisLayer9->setName('TestKarte9');
        $gisLayer9->setOpacity('50');
        $gisLayer9->setOrder(1);
        $gisLayer9->setProcedureId($masterBlueprint2->getId());
        $gisLayer9->setPrint(false);
        $gisLayer9->setScope(false);
        $gisLayer9->setType('overlay');
        $gisLayer9->setUrl('http://www.testurl.de');
        $gisLayer9->setEnabled(true);
        $gisLayer9->setGlobalLayer(false);
        $gisLayer9->setXplan(false);
        $gisLayer9->setCategory($gisLayerCategory3);
        $manager->persist($gisLayer9);

        $manager->flush();

        $this->setReference('testGisLayerCategory7', $gisLayerCategory0);
        $this->setReference('testGisLayerCategory8', $gisLayerCategory1);
        $this->setReference('testGisLayerCategory9', $gisLayerCategory2);
        $this->setReference('testGisLayerCategory10', $gisLayerCategory3);
        $this->setReference('testGisLayer8', $gisLayer8);
        $this->setReference('testGisLayer9', $gisLayer9);
    }
}
