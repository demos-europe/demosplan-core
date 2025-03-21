<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadElementsData extends TestFixture implements DependentFixtureInterface
{
    final public const TEST_ELEMENT_1 = 'testElement1';
    final public const ELEMENT_CATEGORY_FILE = 'testFileElement';
    final public const TEST_ELEMENT_2 = 'testElement2';

    public function load(ObjectManager $manager): void
    {
        $element1 = new Elements();
        $element1->setTitle('Title of elementfixture1');
        $element1->setText('Text of elementfixture1');
        $element1->setIcon('icon-home1');
        $element1->setCategory('paragraph');
        $element1->setOrder(1);
        $element1->setProcedure($this->getReference('testProcedure2'));
        $element1->setEnabled(true);
        $element1->setDeleted(false);

        $manager->persist($element1);
        $this->setReference(self::TEST_ELEMENT_1, $element1);

        $element2 = new Elements();
        $element2->setTitle('Title of elementfixture2');
        $element2->setText('Text of elementfixture2');
        $element2->setIcon('icon-home2');
        $element2->setCategory('paragraph');
        $element2->setOrder(2);
        $element2->setProcedure($this->getReference('testProcedure2'));
        $element2->setEnabled(true);
        $element2->setDeleted(false);
        $element2->setParent($element1);

        $manager->persist($element2);
        $this->setReference(self::TEST_ELEMENT_2, $element2);

        $element3 = new Elements();
        $element3->setTitle('Title of elementfixture2');
        $element3->setText('Text of elementfixture2');
        $element3->setIcon('icon-home2');
        $element3->setCategory('paragraph');
        $element3->setOrder(3);
        $element3->setProcedure($this->getReference('testProcedure2'));
        $element3->setEnabled(true);
        $element3->setDeleted(false);
        $element3->setParent($element2);

        $manager->persist($element3);
        $this->setReference('testElement3', $element3);

        $element4 = new Elements();
        $element4->setTitle('Title of elementfixture2');
        $element4->setText('Text of elementfixture2');
        $element4->setIcon('icon-home2');
        $element4->setCategory('paragraph');
        $element4->setOrder(4);
        $element4->setProcedure($this->getReference('testProcedure2'));
        $element4->setEnabled(true);
        $element4->setDeleted(false);
        $element4->setParent($element3);

        $manager->persist($element4);
        $this->setReference('testElement4', $element4);

        $element5 = new Elements();
        $element5->setTitle('Title of elementfixture2');
        $element5->setText('Text of elementfixture2');
        $element5->setIcon('icon-home2');
        $element5->setCategory('paragraph');
        $element5->setOrder(5);
        $element5->setProcedure($this->getReference('testProcedure2'));
        $element5->setEnabled(true);
        $element5->setDeleted(false);
        $element5->setParent($element1);

        $manager->persist($element5);
        $this->setReference('testElement5', $element5);

        $element6 = new Elements();
        $element6->setTitle('Title of elementFixture6');
        $element6->setText('Text of elementFixture6');
        $element6->setIcon('icon-home3');
        $element6->setCategory('paragraph');
        $element6->setOrder(6);
        $element6->setProcedure($this->getReference('testProcedure2'));
        $element6->setEnabled(false);
        $element6->setDesignatedSwitchDate(new DateTime());
        $element6->setDeleted(false);
        $element6->setParent($element1);

        $manager->persist($element6);
        $this->setReference('testElement6', $element6);

        $element7 = new Elements();
        $element7->setTitle('Gesamtstellungnahme');
        $element7->setText('Gesamtstellungnahme');
        $element7->setIcon('icon-home1');
        $element7->setCategory('statement');
        $element7->setOrder(1);
        $element7->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element7->setEnabled(true);
        $element7->setDeleted(false);

        $manager->persist($element7);
        $this->setReference('testElement7', $element7);

        $element8 = new Elements();
        $element8->setTitle('Fehlanzeige');
        $element8->setText('Fehlanzeige');
        $element8->setIcon('icon-home1');
        $element8->setCategory('statement');
        $element8->setOrder(4);
        $element8->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element8->setEnabled(true);
        $element8->setDeleted(false);

        $manager->persist($element8);
        $this->setReference('testelement8', $element8);

        $element9 = new Elements();
        $element9->setTitle('Title of elementfixture1');
        $element9->setText('Text of elementfixture1');
        $element9->setIcon('icon-home1');
        $element9->setCategory('paragraph');
        $element9->setOrder(5);
        $element9->setProcedure($this->getReference('procedureToDelete'));
        $element9->setEnabled(true);
        $element9->setDeleted(false);

        $manager->persist($element9);
        $this->setReference('testelement9', $element9);

        $mapElement = new Elements();
        $mapElement->setTitle('Planzeichnung');
        $mapElement->setText('Planzeichnung');
        $mapElement->setIcon('icon-map');
        $mapElement->setCategory('map');
        $mapElement->setOrder(2);
        $mapElement->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $mapElement->setEnabled(true);
        $mapElement->setDeleted(false);

        $manager->persist($mapElement);
        $this->setReference('testMapElement', $mapElement);

        $reasonElement = new Elements();
        $reasonElement->setTitle('Begründung');
        $reasonElement->setText('Begründung');
        $reasonElement->setIcon('icon-home1');
        $reasonElement->setCategory('paragraph');
        $reasonElement->setOrder(2);
        $reasonElement->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $reasonElement->setEnabled(true);
        $reasonElement->setDeleted(false);

        $manager->persist($reasonElement);
        $this->setReference('testReasonElement', $reasonElement);

        $regulationElement = new Elements();
        $regulationElement->setTitle('Verordnung');
        $regulationElement->setText('Verordnung');
        $regulationElement->setIcon('icon-home1');
        $regulationElement->setCategory('paragraph');
        $regulationElement->setOrder(9);
        $regulationElement->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $regulationElement->setEnabled(true);
        $regulationElement->setDeleted(false);

        $manager->persist($regulationElement);
        $this->setReference('testRegulationElement', $regulationElement);

        $element9 = new Elements();
        $element9->setTitle('Element9');
        $element9->setText('Text des Element9');
        $element9->setIcon('icon-home1');
        $element9->setCategory('statement');
        $element9->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element9->setEnabled(true);
        $element9->setDeleted(false);
        $element9->setOrder(10);
        $element9->addOrganisation($this->getReference('testOrgaFP'));

        $manager->persist($element9);
        $this->setReference('testelement9', $element9);

        $element10 = new Elements();
        $element10->setTitle('element10');
        $element10->setText('Text des element10');
        $element10->setIcon('icon-home1');
        $element10->setCategory('statement');
        $element10->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element10->setEnabled(true);
        $element10->setDeleted(false);
        $element10->setOrder(11);
        $element10->addOrganisation($this->getReference('testOrgaInvitableInstitution'));

        $manager->persist($element10);
        $this->setReference('testelement10', $element10);

        $element11 = new Elements();
        $element11->setTitle('disabeld Element');
        $element11->setText('Text des element11');
        $element11->setIcon('icon-home1');
        $element11->setCategory('statement');
        $element11->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element11->setEnabled(false);
        $element11->setDeleted(false);
        $element11->setOrder(10);
        $element11->addOrganisation($this->getReference('testOrgaFP'));

        $manager->persist($element11);
        $this->setReference('testelement11', $element11);

        $element12 = new Elements();
        $element12->setTitle('Gesamtstellungnahme');
        $element12->setText('Text of elementfixture12');
        $element12->setIcon('icon-home2');
        $element12->setCategory('statement');
        $element12->setOrder(12);
        $element12->setProcedure($this->getReference('testProcedure2'));
        $element12->setEnabled(true);
        $element12->setDeleted(false);

        $manager->persist($element12);
        $this->setReference('testElement12', $element12);

        $manager->flush();

        $paragraph1 = new Paragraph();
        $paragraph1->setElement($this->getReference('testElement1'));
        $paragraph1->setCategory('begruendung');
        $paragraph1->setVisible(1);
        $paragraph1->setDeleted(false);
        $paragraph1->setOrder(0);
        $paragraph1->setProcedure($this->getReference('testProcedure2'));
        $paragraph1->setTitle('testParagraph1');
        $paragraph1->setText('The text of the testparagraph1');

        $manager->persist($paragraph1);
        $this->setReference('testParagraph1', $paragraph1);

        $paragraph2 = new Paragraph();
        $paragraph2->setElement($this->getReference('testElement1'));
        $paragraph2->setCategory('begruendung');
        $paragraph2->setVisible(1);
        $paragraph2->setDeleted(false);
        $paragraph2->setOrder(1);
        $paragraph2->setProcedure($this->getReference('testProcedure2'));
        $paragraph2->setTitle('testParagraph2');
        $paragraph2->setText('The text of the testparagraph2');

        $manager->persist($paragraph2);
        $this->setReference('testParagraph2', $paragraph2);

        $paragraph2Version = new ParagraphVersion();
        $paragraph2Version->setElement($this->getReference('testElement1'));
        $paragraph2Version->setCategory('begruendung');
        $paragraph2Version->setVisible(true);
        $paragraph2Version->setDeleted(false);
        $paragraph2Version->setProcedure($this->getReference('testProcedure2'));
        $paragraph2Version->setTitle('testParagraph2');
        $paragraph2Version->setText('The text of the testparagraph2');
        $paragraph2Version->setCreateDate(new DateTime());
        $paragraph2Version->setOrder(1);
        $paragraph2Version->setDeleteDate(new DateTime());
        $paragraph2Version->setModifyDate(new DateTime());
        $paragraph2Version->setParagraph($this->getReference('testParagraph2'));

        $manager->persist($paragraph2Version);
        $this->setReference('testParagraph2Version', $paragraph2Version);

        $paragraph3Version = new ParagraphVersion();
        $paragraph3Version->setElement($this->getReference('testElement1'));
        $paragraph3Version->setCategory('begruendung');
        $paragraph3Version->setVisible(true);
        $paragraph3Version->setDeleted(false);
        $paragraph3Version->setProcedure($this->getReference('testProcedure2'));
        $paragraph3Version->setTitle('testParagraph3');
        $paragraph3Version->setText('The text of the testparagraph3');
        $paragraph3Version->setOrder(1);
        $paragraph3Version->setCreateDate(new DateTime());
        $paragraph3Version->setDeleteDate(new DateTime());
        $paragraph3Version->setModifyDate(new DateTime());
        $paragraph3Version->setParagraph($this->getReference('testParagraph2'));

        $manager->persist($paragraph3Version);
        $this->setReference('testParagraph3Version', $paragraph3Version);

        $paragraph4Version = new ParagraphVersion();
        $paragraph4Version->setElement($this->getReference('testElement4'));
        $paragraph4Version->setCategory('begruendung');
        $paragraph4Version->setVisible(true);
        $paragraph4Version->setDeleted(false);
        $paragraph4Version->setProcedure($this->getReference('procedureToDelete'));
        $paragraph4Version->setTitle('testParagraph2');
        $paragraph4Version->setText('The text of the testparagraph2');
        $paragraph4Version->setCreateDate(new DateTime());
        $paragraph4Version->setOrder(1);
        $paragraph4Version->setDeleteDate(new DateTime());
        $paragraph4Version->setModifyDate(new DateTime());
        $paragraph4Version->setParagraph($this->getReference('testParagraph2'));

        $manager->persist($paragraph4Version);
        $this->setReference('testparagraph4Version', $paragraph4Version);

        $paragraph3 = new Paragraph();
        $paragraph3->setElement($this->getReference('testElement1'));
        $paragraph3->setCategory('without');
        $paragraph3->setVisible(1);
        $paragraph3->setDeleted(false);
        $paragraph3->setOrder(2);
        $paragraph3->setProcedure($this->getReference('testProcedure2'));
        $paragraph3->setTitle('testParagraph3');
        $paragraph3->setText('The text of the testparagraph3');

        $manager->persist($paragraph3);
        $this->setReference('testParagraph3', $paragraph3);

        $testFileElement = new Elements();
        $testFileElement->setTitle('Title of elementfixture2');
        $testFileElement->setText('Text of elementfixture2');
        $testFileElement->setIcon('icon-home2');
        $testFileElement->setOrder(5);
        $testFileElement->setCategory('file');
        $testFileElement->setProcedure($this->getReference('testProcedure2'));
        $testFileElement->setEnabled(true);
        $testFileElement->setDeleted(false);

        $manager->persist($testFileElement);
        $this->setReference('testFileElement', $testFileElement);

        // Paragraphs with tree structure
        // paragraphs in the tree may be obtained with
        // (for example of 1_1):
        // $this->getReference('testParagraph_tree_1_1');
        $paragraphTree = [
            '1' => [
                '1_1' => [
                    '1_1_1' => [],
                    '1_1_2' => [],
                    '1_1_3' => [],
                ],
                '1_2' => [
                    '1_2_1' => [],
                ],
                '1_3' => [
                    '1_3_1' => [],
                    '1_3_2' => [],
                ],
            ],
            '2' => [
                '2_1' => [],
            ],
        ];

        $this->createParagraphTree($manager, $paragraphTree);

        $manager->flush();

        $element = new Elements();
        $element->setTitle('Title of element relatet to SingleDocument');
        $element->setText('Text of element relatet to SingleDocument');
        $element->setIcon('icon-home');
        $element->setCategory('paragraph');
        $element->setOrder(2);
        $element->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $element->setEnabled(true);
        $element->setDeleted(false);
        $element->setElementParentId('f7a2863c-9457-43dc-9bef-400d09d6e9ce');

        $manager->persist($element);
        $manager->flush();
        $this->setReference('testSingleDocumentElement', $element);

        $singleDocument1 = new SingleDocument();
        $singleDocument1->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $singleDocument1->setElement($element);
        $singleDocument1->setCategory('informationen2');
        $singleDocument1->setOrder(0);
        $singleDocument1->setTitle('testFixtureDocument1');
        $singleDocument1->setText('the text1');
        $singleDocument1->setSymbol('');
        $singleDocument1->setDocument('nice_Document1.pdf');
        $singleDocument1->setStatementEnabled(true);
        $singleDocument1->setVisible(true);
        $singleDocument1->setDeleted(false);

        $manager->persist($singleDocument1);
        $this->setReference('testSingleDocument1', $singleDocument1);

        $singleDocumentVersion = new SingleDocumentVersion();
        $singleDocumentVersion->setSingleDocument($singleDocument1);
        $singleDocumentVersion->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $singleDocumentVersion->setElement($element);
        $singleDocumentVersion->setCategory('informationen2');
        $singleDocumentVersion->setOrder(0);
        $singleDocumentVersion->setTitle('testFixtureDocument1');
        $singleDocumentVersion->setText('the text1');
        $singleDocumentVersion->setSymbol('');
        $singleDocumentVersion->setDocument('nice_Document1.pdf');
        $singleDocumentVersion->setStatementEnabled(true);
        $singleDocumentVersion->setVisible(true);
        $singleDocumentVersion->setDeleted(false);
        $singleDocumentVersion->setCreateDate($singleDocument1->getCreateDate());
        $singleDocumentVersion->setModifyDate($singleDocument1->getModifyDate());
        $singleDocumentVersion->setDeleteDate($singleDocument1->getDeleteDate());

        $manager->persist($singleDocumentVersion);
        $this->setReference('testSingleDocumentVersion1', $singleDocumentVersion);

        $singleDocument2 = new SingleDocument();
        $singleDocument2->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $singleDocument2->setElement($element);
        $singleDocument2->setCategory('informationen2');
        $singleDocument2->setOrder(0);
        $singleDocument2->setTitle('testFixtureDocument2');
        $singleDocument2->setText('the text2');
        $singleDocument2->setSymbol('');
        $singleDocument2->setDocument('nice_Document2.pdf');
        $singleDocument2->setStatementEnabled(true);
        $singleDocument2->setVisible(true);
        $singleDocument2->setDeleted(false);

        $manager->persist($singleDocument2);
        $this->setReference('testSingleDocument2', $singleDocument2);

        $singleDocument3 = new SingleDocument();
        $singleDocument3->setProcedure($this->getReference(LoadProcedureData::TESTPROCEDURE));
        $singleDocument3->setElement($element);
        $singleDocument3->setCategory('informationen3');
        $singleDocument3->setOrder(0);
        $singleDocument3->setTitle('testFixtureDocumen3');
        $singleDocument3->setText('the text3');
        $singleDocument3->setSymbol('');
        $singleDocument3->setDocument('nice_Document3.pdf');
        $singleDocument3->setStatementEnabled(true);
        $singleDocument3->setVisible(true);
        $singleDocument3->setDeleted(false);

        $manager->persist($singleDocument3);
        $this->setReference('testSingleDocument3', $singleDocument3);

        $element13 = new Elements();
        $element13->setTitle('Gesamtstellungnahme');
        $element13->setText('Text of elementfixture13');
        $element13->setIcon('icon-home1');
        $element13->setCategory('statement');
        $element13->setOrder(5);
        $element13->setProcedure($this->getReference('testProcedure3'));
        $element13->setEnabled(true);
        $element13->setDeleted(false);

        $manager->persist($element13);
        $this->setReference('testelement13', $element13);

        $elementToSwitch = new Elements();
        $elementToSwitch->setTitle('Title of elementFixture14');
        $elementToSwitch->setText('Text of elementFixture14');
        $elementToSwitch->setIcon('icon-home3');
        $elementToSwitch->setCategory('paragraph');
        $elementToSwitch->setOrder(6);
        $elementToSwitch->setProcedure($this->getReference('testProcedure2'));
        $elementToSwitch->setEnabled(true);
        $elementToSwitch->setDesignatedSwitchDate(Carbon::now()); // should be found
        $elementToSwitch->setDeleted(false);
        $elementToSwitch->setParent($element1);

        $manager->persist($elementToSwitch);
        $this->setReference('testElement14', $elementToSwitch);

        $elementToNotSwitch = new Elements();
        $elementToNotSwitch->setTitle('Title of elementFixture15');
        $elementToNotSwitch->setText('Text of elementFixture15');
        $elementToNotSwitch->setIcon('icon-home3');
        $elementToNotSwitch->setCategory('paragraph');
        $elementToNotSwitch->setOrder(6);
        $elementToNotSwitch->setProcedure($this->getReference('testProcedure2'));
        $elementToNotSwitch->setEnabled(true);
        $elementToNotSwitch->setDesignatedSwitchDate(Carbon::now()->addDays(17)); // should not be found
        $elementToNotSwitch->setDeleted(false);
        $elementToNotSwitch->setParent($element1);

        $manager->persist($elementToNotSwitch);
        $this->setReference('testElement15', $elementToNotSwitch);

        $manager->flush();
    }

    protected function createParagraphTree($manager, $tree, $order = 0, $parent = null)
    {
        foreach ($tree as $key => $sub) {
            $paragraph = new Paragraph();
            $paragraph->setElement($this->getReference('testElement2'));
            $paragraph->setCategory('without');
            $paragraph->setVisible(1);
            $paragraph->setDeleted(false);
            $paragraph->setOrder($order);
            $paragraph->setProcedure($this->getReference('testProcedure2'));
            $paragraph->setTitle($key);
            $paragraph->setText("The text of the leaf $key in the paragraph tree");
            if (null != $parent) {
                $paragraph->setParent($parent);
                $parent->addChild($paragraph);
                $manager->persist($parent);
            }
            $manager->persist($paragraph);
            $this->setReference('testParagraph_tree_'.$key, $paragraph);
            $order = $this->createParagraphTree($manager, $sub, ++$order, $paragraph);
        }

        return $order;
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
