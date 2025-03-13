<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Functional;

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadElementsData;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\HiddenElementUpdateException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Tests\Base\FunctionalTestCase;

class ElementsServiceTest extends FunctionalTestCase
{
    /** @var ElementsService */
    protected $sut;

    /** @var Elements */
    protected $testElement;

    /** @var string */
    protected $testProcedureId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ElementsService::class);
        $this->testElement = $this->fixtures->getReference('testElement1');
        $this->testProcedureId = $this->fixtures->getReference('testProcedure')->getId();
    }

    private function checkElementArray($elementArray)
    {
        static::assertArrayHasKey('ident', $elementArray);
        $this->checkId($elementArray['ident']);
        static::assertArrayHasKey('title', $elementArray);
        static::assertIsString($elementArray['title']);
        static::assertArrayHasKey('text', $elementArray);
        static::assertIsString($elementArray['text']);
        static::assertArrayHasKey('icon', $elementArray);
        static::assertIsString($elementArray['icon']);
        static::assertArrayHasKey('category', $elementArray);
        static::assertIsString($elementArray['category']);
        static::assertArrayHasKey('order', $elementArray);
        static::assertTrue(is_integer($elementArray['order']));
        static::assertArrayHasKey('pId', $elementArray);
        $this->checkId($elementArray['pId']);
        static::assertArrayHasKey('documents', $elementArray);
        static::assertIsArray($elementArray['documents']);
        static::assertArrayHasKey('children', $elementArray);
        static::assertArrayHasKey('enabled', $elementArray);
        static::assertIsBool($elementArray['enabled']);
        static::assertArrayHasKey('deleted', $elementArray);
        static::assertIsBool($elementArray['deleted']);
        static::assertArrayHasKey('createdate', $elementArray);
        static::assertIsNumeric($elementArray['createdate']);
        static::assertArrayHasKey('organisation', $elementArray);
        static::assertIsArray($elementArray['organisation']);
    }

    public function testGetElementsListForOrga()
    {
        self::markSkippedForCIIntervention();

        /** @var Orga $testOrga */
        $testOrga = $this->fixtures->getReference('testOrgaPB');
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $result = $this->sut->getElementsListObjects($testProcedure->getId(), $testOrga->getIdent());
        $result = array_map([$this->sut, 'convertElementToArray'], $result);
        foreach ($result as $element) {
            self::assertSame(false, $element['deleted']);
            $organisations = $element['organisation'];
            if (0 !== count($organisations)) {
                self::assertIsIterable($organisations);
                self::assertNotEmpty($organisations);
                $organisationIds = array_column($organisations, 'id');
                self::assertNotEmpty($organisationIds);
                self::assertContains(
                    $testOrga->getIdent(),
                    $organisationIds,
                    '',
                    false,
                    true,
                    true
                );
            }
            self::assertSame($testProcedure->getId(), $element['pId']);
        }

        // dynamic way to get reference number of results:
        $referenceElements = collect($this->getEntries(Elements::class, ['pId' => $testProcedure]));
        self::assertNotEmpty($referenceElements);
        $filteredReferenceElements = $referenceElements->filter(
            function (Elements $element) use ($testOrga): bool {
                return collect($element->getOrganisations())->contains($testOrga->getId()) || 0 === count($element->getOrganisations());
            }
        );
        self::assertNotEmpty($filteredReferenceElements, 'Unfiltered count: '.count($referenceElements));
        self::assertCount(count($filteredReferenceElements), $result);
    }

    public function testGetElementsList()
    {
        self::markSkippedForCIIntervention();

        /** @var Orga $testOrga */
        $testOrga = $this->fixtures->getReference('testOrgaPB');
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $result = $this->sut->getElementsListObjects($testProcedure->getId(), $testOrga->getIdent());
        $result = array_map([$this->sut, 'convertElementToArray'], $result);

        // dynamic way to get reference number of results:
        $referenceElements = collect($this->getEntries(Elements::class, ['procedure' => $testProcedure]));
        $referenceElements = $referenceElements->filter(
            function (Elements $element) use ($testOrga) {
                $orgasOfElement = collect($element->getOrganisations());

                return $orgasOfElement->contains($testOrga) || $orgasOfElement->isEmpty();
            }
        );

        static::assertCount($referenceElements->count(), $result);
        $this->checkElementArray($result[0]);

        $firstResult = $result[0];
        static::assertEquals($this->testElement->getIdent(), $firstResult['ident']);

        // dynamic way to get reference Element:
        $foundReferenceElement = $this->getEntries(Elements::class, ['id' => $firstResult['ident']]);
        $children = $firstResult['children']->toArray();
        static::assertEquals(sizeof($foundReferenceElement[0]->getChildren()), sizeof($children));
    }

    public function testGetElementsAdminList()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $result = $this->sut->getElementsAdminList($testProcedure->getId());
        $referenceElements = collect($this->getEntries(Elements::class, ['procedure' => $testProcedure->getId()]));

        static::assertIsArray($result);
        static::assertEquals($referenceElements->count(), sizeof($result));

        static::assertArrayHasKey(0, $result);
        $firstElement = $result[0];
        static::assertInstanceOf(Elements::class, $firstElement);
        $this->checkId($firstElement->getId());
        $this->checkId($firstElement->getPId());
    }

    public function testGetElements()
    {
        /** @var Elements $testElement2 */
        $testElement2 = $this->fixtures->getReference('testElement2');
        /** @var Elements $testElement5 */
        $testElement5 = $this->fixtures->getReference('testElement5');
        /** @var Elements $testElement3 */
        $testElement3 = $this->fixtures->getReference('testElement3');
        /** @var Elements $testElement4 */
        $testElement4 = $this->fixtures->getReference('testElement4');
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');

        $result = $this->sut->getElement($this->testElement->getIdent());

        $referenceElements = collect($this->getEntries(Elements::class, ['procedure' => $testProcedure->getId(), 'id' => $this->testElement->getIdent()]));
        static::assertCount(1, $referenceElements);
        /** @var Elements $referenceElement */
        $referenceElement = $referenceElements->first();

        static::assertIsArray($result);
        $this->checkElementArray($result);
        static::assertArrayHasKey('children', $result);
        $children = collect($result['children']->toArray());

        static::assertCount($referenceElement->getChildren()->count(), $children);
        static::assertTrue($children->contains($testElement2));
        static::assertTrue($children->contains($testElement5));
        /** @var Elements[] $children2 */
        $children2 = $children[0]->getChildren()->toArray();
        static::assertCount(1, $children2);
        static::assertEquals($testElement3->getIdent(), $children2[0]->getIdent());
        /** @var Elements[] $children3 */
        $children3 = $children2[0]->getChildren()->toArray();
        static::assertCount(1, $children3);
        static::assertEquals($testElement4->getIdent(), $children3[0]->getIdent());
        /** @var Elements[] $children4 */
        $children4 = $children3[0]->getChildren()->toArray();
        static::assertCount(0, $children4);

        $this->checkElementArray($result);

        $this->isCurrentTimestamp($result['createdate']);
        static::assertArrayHasKey('modifydate', $result);
        $this->isCurrentTimestamp($result['modifydate']);
        static::assertArrayHasKey('deletedate', $result);
        $this->isCurrentTimestamp($result['deletedate']);
    }

    public function testGetNegativeReportElement()
    {
        $result = $this->sut->getNegativeReportElement($this->fixtures->getReference('testProcedure')->getId());
        static::assertInstanceOf('\demosplan\DemosPlanCoreBundle\Entity\Document\Elements', $result);
    }

    public function testGetNegativeReportElementNullReturn()
    {
        $this->loginTestUser();
        $this->enablePermissions(['feature_admin_element_invitable_institution_or_public_authorisations']);
        $result = $this->sut->getNegativeReportElement($this->fixtures->getReference('testProcedure')->getId());
        static::assertNull($result);
    }

    public function testGetNegativeReportElementHasNoElement()
    {
        $this->expectException(StatementElementNotFoundException::class);

        $this->sut->getNegativeReportElement($this->fixtures->getReference('testProcedure2')->getId());
    }

    public function testHasNegativeReportElement()
    {
        $result = $this->sut->hasNegativeReportElement($this->fixtures->getReference('testProcedure')->getId());
        static::assertTrue($result);
    }

    public function testHasNoNegativeReportElement()
    {
        $result = $this->sut->hasNegativeReportElement($this->fixtures->getReference('testProcedure2')->getId());
        static::assertFalse($result);
    }

    public function testCalculateNextElementOrder()
    {
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $referenceElements = collect($this->getEntries(Elements::class, ['id' => $this->testElement->getIdent()]));
        static::assertCount(1, $referenceElements);
        $referenceParentElement = $referenceElements->first();

        $data = [
            'pId'           => $testProcedure->getId(),
            'category'      => 'category',
            'title'         => 'title',
            'icon'          => 'icon',
            'text'          => 'text',
            'documents'     => [],
            'parent'        => $this->testElement->getIdent(),
            'organisations' => [],
        ];

        $parentElement = $this->sut->getElementObject($this->testElement->getIdent());
        $numberOfChildrenBefore = $parentElement->getChildren()->count();
        static::assertEquals($referenceParentElement->getChildren()->count(), $numberOfChildrenBefore);

        $elementsOfProcedure = $this->sut->getElementsListObjects($testProcedure->getId());

        // get highest order number:
        $highestOrder = 1;
        foreach ($elementsOfProcedure as $element) {
            if (is_numeric($element->getOrder()) && $element->getOrder() > $highestOrder) {
                $highestOrder = $element->getOrder();
            }
        }
        $calculatedOrderNumber = $highestOrder + 1;

        $addedElement = $this->sut->addElement($data);
        static::assertEquals($calculatedOrderNumber, $addedElement['order']);
    }

    public function testAddElement()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $referenceElements = collect($this->getEntries(Elements::class, ['id' => $this->testElement->getIdent()]));
        static::assertCount(1, $referenceElements);
        /** @var Elements $referenceParentElement */
        $referenceParentElement = $referenceElements->first();

        $data = [
            'pId'           => $testProcedure->getId(),
            'category'      => 'category',
            'title'         => 'title',
            'icon'          => 'icon',
            'text'          => 'text',
            'documents'     => [],
            'parent'        => $this->testElement->getIdent(),
            'organisations' => [],
        ];

        $parentElement = $this->sut->getElementObject($this->testElement->getIdent());
        $numberOfChildrenBefore = $parentElement->getChildren()->count();
        static::assertEquals($referenceParentElement->getChildren()->count(), $numberOfChildrenBefore);

        $addedElement = $this->sut->addElement($data);

        $parent = $this->sut->getElementObject($this->testElement->getIdent());

        $this->checkElementArray($addedElement);
        static::assertArrayHasKey('ident', $addedElement);
        $this->checkId($addedElement['ident']);
        // order of new created element is checked with testCalculateNextElementOrder()
        static::assertEquals($data['title'], $addedElement['title']);
        static::assertEquals($data['text'], $addedElement['text']);
        static::assertEquals($data['icon'], $addedElement['icon']);
        static::assertEquals($data['category'], $addedElement['category']);
        static::assertEquals($data['pId'], $addedElement['pId']);
        static::assertEquals($data['documents'], $addedElement['documents']);
        static::assertArrayNotHasKey('parents', $addedElement);
        static::assertArrayHasKey('children', $addedElement);

        $children = collect($parent->getChildren());
        // parent do not know about the new child!?:
        static::assertEquals($numberOfChildrenBefore, $children->count());
        $resultElement = $this->sut->getElementObject($addedElement['ident']);
        static::assertFalse($children->contains($resultElement));

        static::assertEquals(0, sizeof($addedElement['children']));
        static::assertTrue($addedElement['enabled']);
        static::assertFalse($addedElement['deleted']);
        $this->isCurrentTimestamp($addedElement['createdate']);
        static::assertEquals($data['organisations'], $addedElement['organisation']);
    }

    public function testDeleteElement()
    {
        $elementId = $this->fixtures->getReference('testElement5')->getId();

        $entriesBefore = $this->countEntries(Elements::class);
        $result = $this->sut->deleteElement($elementId);
        static::assertTrue($result); // has to be true but is null
        static::assertEquals($entriesBefore - 1, $this->countEntries(Elements::class));
    }

    public function testDeleteElementWithChildElements()
    {
        self::markSkippedForCIIntervention();

        /** @var Elements $testElement */
        $testElement = $this->fixtures->getReference('testElement1');
        $numberOfChildren = $testElement->countChildrenRecursively();
        $entriesBefore = $this->countEntries(Elements::class);
        $result = $this->sut->deleteElement($testElement->getId());
        static::assertTrue($result);
        // Alle Children sind rekursiv ebenso gelöscht
        static::assertEquals($entriesBefore - $numberOfChildren - 1, $this->countEntries(Elements::class));
    }

    public function testUpdateElement()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'ident'         => $this->testElement->getIdent(),
            'pId'           => $this->fixtures->getReference('testProcedure2')->getId(),
            'parent'        => 'updatedparent',
            'category'      => 'updatedCategory',
            'title'         => 'updatedtitle',
            'icon'          => 'updatedicon',
            'text'          => 'updatedtext',
            'order'         => 5,
            'documents'     => [],
            'organisations' => [],
        ];

        $result = $this->sut->updateElementArray($data);

        $this->checkElementArray($result);
        static::assertEquals($data['pId'], $result['pId']);
        static::assertEquals($data['title'], $result['title']);
        static::assertEquals($data['icon'], $result['icon']);
        static::assertEquals($data['text'], $result['text']);
        static::assertEquals($data['documents'], $result['documents']);
        static::assertEquals($data['organisations'], $result['organisation']);
        static::assertEquals(5, $result['order']);
        $this->isCurrentTimestamp($result['createdate']);
        static::assertTrue($result['enabled']);
        static::assertFalse($result['deleted']);
    }

    public function testUpdateElementObject()
    {
        $data = [
            'ident'         => $this->testElement->getIdent(),
            'pId'           => $this->fixtures->getReference('testProcedure2')->getId(),
            'parent'        => 'updatedparent',
            'category'      => 'updatedCategory',
            'title'         => 'updatedtitle',
            'icon'          => 'updatedicon',
            'text'          => 'updatedtext',
            'order'         => 5,
            'documents'     => [],
            'organisations' => [],
        ];

        $this->testElement->setPId($this->fixtures->getReference('testProcedure2')->getId());
        $this->testElement->setParent($this->fixtures->getReference('testElement6'));
        $this->testElement->setCategory($data['category']);
        $this->testElement->setTitle($data['title']);
        $this->testElement->setIcon($data['icon']);
        $this->testElement->setText($data['text']);
        $this->testElement->setOrder($data['order']);
        $this->testElement->setDocuments(new ArrayCollection($data['documents']));
        $this->testElement->setOrganisations(new ArrayCollection($data['organisations']));

        $result = $this->sut->updateElementObject($this->testElement);

        static::assertInstanceOf(Elements::class, $result);
        static::assertEquals($data['pId'], $result->getPId());
        static::assertEquals($data['title'], $result->getTitle());
        static::assertEquals($data['icon'], $result->getIcon());
        static::assertEquals($data['text'], $result->getText());
        static::assertEquals($data['documents'], $result->getDocuments()->toArray());
        static::assertEquals($data['organisations'], $result->getOrganisations()->toArray());
        static::assertEquals(5, $result->getOrder());
        $this->isCurrentTimestamp($result->getCreateDate()->getTimestamp());
        static::assertTrue($result->getEnabled());
        static::assertFalse($result->getDeleted());
    }

    public function testUpdateMapElementObject()
    {
        $data = [
            'enabled'       => true,
            'title'         => ElementsInterface::ELEMENT_TITLES['planzeichnung'],
            'category'      => ElementsInterface::ELEMENT_CATEGORIES['map'],
        ];

        $this->testElement->setEnabled($data['enabled']);
        $this->testElement->setTitle($data['title']);
        $this->testElement->setCategory($data['category']);

        $result = $this->sut->updateElementObject($this->testElement);

        static::assertEquals($data['enabled'], $result->getEnabled());
        static::assertEquals($data['title'], $result->getTitle());
        static::assertEquals($data['category'], $result->getCategory());
    }

    public function testUpdateNotAllowedElementObject()
    {
        $this->expectException(HiddenElementUpdateException::class);

        $data = [
            'enabled'       => true,
            'title'         => ElementsInterface::ELEMENT_TITLES['fehlanzeige'],
            'category'      => ElementsInterface::ELEMENT_CATEGORIES['statement'],
        ];

        $this->testElement->setEnabled($data['enabled']);
        $this->testElement->setTitle($data['title']);
        $this->testElement->setCategory($data['category']);

        $this->sut->updateElementObject($this->testElement);
    }

    public function testAddAuthorisationToOrga()
    {
        $result = $this->sut->getElement($this->testElement->getIdent());
        static::assertCount(0, $result['organisations']);

        $added = $this->sut->addAuthorisationToOrga($result['ident'], $this->fixtures->getReference('testOrgaInvitableInstitution')->getId());
        static::assertTrue($added);
        $result2 = $this->sut->getElement($this->testElement->getIdent());
        static::assertCount(1, $result2['organisations']);

        // test delete
        $deleted = $this->sut->deleteAuthorisationOfOrga($result['ident'], $this->fixtures->getReference('testOrgaInvitableInstitution')->getId());
        static::assertTrue($deleted);
        $result3 = $this->sut->getElement($this->testElement->getIdent());
        static::assertCount(0, $result3['organisations']);

        // test add with array
        $orgas = [
            $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            $this->fixtures->getReference('testOrgaFP')->getId(),
        ];
        $added = $this->sut->addAuthorisationToOrga($result['ident'], $orgas);
        static::assertTrue($added);
        $result4 = $this->sut->getElement($this->testElement->getIdent());
        static::assertCount(2, $result4['organisations']);
    }

    public function testAutoSwitchElementsState()
    {
        $elementA = $this->getElementReference('testElement6');
        $elementB = $this->getElementReference('testElement14');
        $elementC = $this->getElementReference('testElement15');
        self::assertFalse($elementA->getEnabled());
        self::assertTrue($elementB->getEnabled());
        self::assertTrue($elementC->getEnabled());
        self::assertNotNull($elementA->getDesignatedSwitchDate());
        self::assertNotNull($elementB->getDesignatedSwitchDate());
        self::assertNotNull($elementC->getDesignatedSwitchDate());
        self::assertFalse($elementA->getDeleted());
        self::assertFalse($elementB->getDeleted());
        self::assertFalse($elementC->getDeleted());

        $affectedElementsCount = $this->sut->autoSwitchElementsState();
        self::assertEquals(2, $affectedElementsCount);

        $affectedElementsCount = $this->sut->autoSwitchElementsState();
        self::assertEquals(0, $affectedElementsCount);

        $this->getEntityManager()->refresh($elementA);
        $this->getEntityManager()->refresh($elementB);
        $this->getEntityManager()->refresh($elementC);

        self::assertTrue($elementA->getEnabled());
        self::assertFalse($elementB->getEnabled());
        self::assertTrue($elementC->getEnabled());
        self::assertNull($elementA->getDesignatedSwitchDate());
        self::assertNull($elementB->getDesignatedSwitchDate());
        self::assertNotNull($elementC->getDesignatedSwitchDate());
    }

    public function testGetElementsObject()
    {
        $dbElement = $this->fixtures->getReference('testElement1');
        $element = $this->sut->getElementObject($dbElement->getId());
        $this->assertInstanceOf(Elements::class, $element);
    }

    public function testGetTopElementsByProcedureId()
    {
        $notWhere = [
            'category' => ['map'], // elements must not be in the 'map' category
            'deleted'  => [true], // elements must not be deleted
        ];
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $topElements = $this->sut->getTopElementsByProcedureId($testProcedure->getId(), $notWhere);

        /** @var Elements[] $elementsWithoutParents */
        $elementsWithoutParents = $this->getEntries(
            Elements::class,
            [
                'pId'             => $testProcedure->getId(),
                'elementParentId' => null,
                'deleted'         => false,
            ],
            ['order' => 'ASC']
        );

        $notMapElementIdsWithoutParents = [];
        // remove elements with category 'map'
        foreach ($elementsWithoutParents as $element) {
            if ('map' !== $element->getCategory()) {
                $notMapElementIdsWithoutParents[] = $element->getId();
            }
        }

        $topElementIds = [];
        foreach ($topElements as $topElement) {
            $topElementIds[] = $topElement->getId();
        }

        static::assertEquals($notMapElementIdsWithoutParents, $topElementIds);
    }

    public function testGetElementsListObjects()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $elements = $this->sut->getElementsListObjects($testProcedure->getId());

        /** @var Elements[] $expectedResult */
        $expectedResult = $this->getEntries(
            Elements::class,
            [
                'pId'     => $testProcedure->getId(),
                'enabled' => true,
            ],
            ['order' => 'ASC']
        );

        static::assertEquals($expectedResult, $elements);
    }

    public function testGetElementsOfOrganisation()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        /** @var Orga $testOrganisation */
        $testOrganisation = $this->fixtures->getReference('testOrgaFP');
        $foundElements = $this->sut->getElementsListObjects($testProcedure->getId(), $testOrganisation->getId());

        /** @var Elements[] $elementsOfProcedure */
        $elementsOfProcedure = $this->getEntries(
            Elements::class,
            [
                'pId'     => $testProcedure->getId(),
                'enabled' => true,
            ],
            ['order' => 'ASC']
        );

        // getElementsListObjects() with given organisationId, should also return, elements, without specific
        $expectedElementIdsOfOrganisation = [];
        foreach ($elementsOfProcedure as $element) {
            if (true === in_array($testOrganisation, $element->getOrganisations()->toArray()) || 0 === count($element->getOrganisations())) {
                $expectedElementIdsOfOrganisation[] = $element->getId();
            }
        }

        foreach ($foundElements as $element) {
            $foundElementIds[] = $element->getId();
        }

        static::assertEquals($expectedElementIdsOfOrganisation, $foundElementIds);
    }

    /**
     * @throws Exception
     */
    public function testGetEnabledFileAndParagraphElements(): void
    {
        $testOrga = $this->getOrgaReference('testOrgaPB');
        $testProcedure = $this->getProcedureReference('testProcedure2');

        $elements = $this->sut->getEnabledFileAndParagraphElements($testProcedure->getId(), $testOrga->getId());

        // testElement1 --> category : paragraph , no chapters (no parent)
        $expectedParagraphElement = $this->getElementReference('testElement1');
        // testFileElement --> category : file , no document (no parent)
        $expectedFileElement = $this->getElementReference(LoadElementsData::ELEMENT_CATEGORY_FILE);

        $foundParagraphElement = false;
        $foundFileElement = false;

        foreach ($elements as $element) {
            if ($expectedParagraphElement->getId() === $element['id']) {
                $foundParagraphElement = true;
            }
            if ($expectedFileElement->getId() === $element['id']) {
                $foundFileElement = true;
            }

            self::assertNotEquals(ElementsInterface::ELEMENT_CATEGORIES['category'], $element['category']);
            self::assertNotEquals(ElementsInterface::ELEMENT_CATEGORIES['map'], $element['category']);
            self::assertNotEquals(ElementsInterface::ELEMENT_CATEGORIES['statement'], $element['category']);
            self::assertEquals(1, $element['enabled']);
        }

        self::assertTrue($foundParagraphElement);
        self::assertTrue($foundFileElement);
    }

    /**
     * In case of $isOwner is true, filtering for given organisation should be ignored.
     *
     * @throws Exception
     */
    public function testGetOwnElements()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        /** @var Orga $testOrganisation */
        $testOrganisation = $this->fixtures->getReference('testOrgaFP');

        $elements = $this->sut->getElementsListObjects($testProcedure->getId(), $testOrganisation->getId(), true);

        /** @var Elements[] $referenceResult */
        $referenceResult = $this->getEntries(
            Elements::class,
            [
                'pId'     => $testProcedure->getId(),
                'enabled' => true,
            ],
            ['order' => 'ASC']
        );

        static::assertEquals($referenceResult, $elements);
    }

    public function testGetAlsoDisabledElements()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $elements = $this->sut->getElementsListObjects($testProcedure->getId(), null, true, true);

        /** @var Elements[] $referenceResult */
        $referenceResult = $this->getEntries(
            Elements::class,
            [
                'pId' => $testProcedure->getId(),
            ],
            ['order' => 'ASC']
        );

        static::assertEquals($referenceResult, $elements);
    }

    public function testGetElementsWithoutParagraphs()
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $elementIds = $this->sut->getElementsIdsWithoutParagraphsAndDocuments($testProcedure->getId());

        $elementsOfProcedure = $this->getEntries(Elements::class, ['pId' => $testProcedure->getId()]);

        $expectedElementIds = [];
        foreach ($elementsOfProcedure as $element) {
            $paragraphIds = $this->sut->getElementsRepository()->getParagraphIds($element->getId());
            if (0 === count($paragraphIds)) {
                $expectedElements[] = $element;
                $expectedElementIds[] = $element->getId();
            }
        }

        // expect some data, otherwise, the test data setup is not meaningful
        static::assertNotEmpty($expectedElementIds);
        static::assertNotEmpty($elementIds);

        static::assertEquals($expectedElementIds, $elementIds);
    }

    public function testGetStatementElement()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure2');
        $statementElement = $this->sut->getStatementElement($testProcedure->getId());

        static::assertNotNull($statementElement);
    }
}
