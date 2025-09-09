<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\MasterToebService;
use Exception;
use Tests\Base\FunctionalTestCase;

class MasterToebServiceTest extends FunctionalTestCase
{
    /**
     * @var MasterToebService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(MasterToebService::class);
    }

    public function testGetMasterToebsStructure()
    {
        $result = $this->sut->getMasterToebs();

        static::assertTrue(is_array($result));
        foreach ($result as $entry) {
            static::assertTrue($entry instanceof MasterToeb);
        }
    }

    public function testGetMasterToebsByGroupName()
    {
        $groupName = $this->fixtures->getReference('testMasterToeb')->getGatewayGroup();
        $result = $this->sut->getMasterToebByGroupName($groupName);

        static::assertTrue(is_array($result));
        foreach ($result as $entry) {
            static::assertTrue($entry instanceof MasterToeb);
        }
    }

    public function testGetSingleMasterToeb()
    {
        $ident = $this->fixtures->getReference('testMasterToeb')->getIdent();

        $result = $this->sut->getMasterToeb($ident);

        static::assertTrue($result instanceof MasterToeb);
    }

    public function testGetSingleMasterToebWithNotExistingId()
    {
        $this->expectException(Exception::class);

        $this->sut->getMasterToeb('');
    }

    public function testDeleteMasterToeb()
    {
        $ident = $this->fixtures->getReference('testMasterToeb2')->getIdent();

        $numberOfEntriesBefore = $this->countEntries(MasterToeb::class);

        $result = $this->sut->deleteMasterToeb($ident);

        $numberOfEntriesAfter = $this->countEntries(MasterToeb::class);
        static::assertEquals($numberOfEntriesAfter + 1, $numberOfEntriesBefore);

        static::assertTrue($result);
    }

    public function testDeleteSingleMasterToebWithNotExistingId()
    {
        $this->expectException(Exception::class);

        $this->sut->deleteMasterToeb('');
    }

    public function testAddMasterToebStructure()
    {
        $data = ['orgaName' => 'TestOrga'];
        $result = $this->sut->addMasterToeb($data);

        static::assertInstanceOf(MasterToeb::class, $result);
    }

    public function testAddMasterToebWithEmptyDataArray()
    {
        $this->expectException(Exception::class);

        $this->sut->addMasterToeb([]);
    }

    public function testUpdateSingleMasterToeb()
    {
        self::markSkippedForCIIntervention();

        $masterToebBeforeUpdate = clone $this->fixtures->getReference('testMasterToeb2');
        $ident = $masterToebBeforeUpdate->getIdent();
        $data = ['departmentName' => 'Abteilung'];
        $numberOfEntriesBefore = $this->countEntries(MasterToeb::class);

        $this->sut->updateMasterToeb($ident, $data);
        $numberOfEntriesAfter = $this->countEntries(MasterToeb::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check entry
        $masterToeb = $this->fixtures->getReference('testMasterToeb2');
        $updatedMasterToeb = $this->sut->getMasterToeb($masterToeb->getIdent());
        static::assertEquals($data['departmentName'], $updatedMasterToeb->getDepartmentName());
        static::assertEquals($masterToeb->getIdent(), $updatedMasterToeb->getIdent());
        static::assertEquals($masterToeb->getCreatedDate(), $updatedMasterToeb->getCreatedDate());
        static::assertTrue($this->isCurrentDateTime($updatedMasterToeb->getModifiedDate()->format('Y-m-d H:i:s')));

        $masterToebVersions = $this->sut->getVersions($ident);

        $masterToebVersion = $masterToebVersions[0];
        static::assertTrue($this->isCurrentDateTime($masterToebVersion->getVersionDate()->format('Y-m-d H:i:s')));
        static::assertEquals($masterToebBeforeUpdate->getIdent(), $masterToebVersion->getMasterToebId());
        static::assertEquals($masterToebBeforeUpdate->getCcEmail(), $masterToebVersion->getCcEmail());
        static::assertEquals($masterToebBeforeUpdate->getComment(), $masterToebVersion->getComment());
        static::assertEquals($masterToebBeforeUpdate->getDepartmentName(), $masterToebVersion->getDepartmentName());
        static::assertEquals($masterToebBeforeUpdate->getContactPerson(), $masterToebVersion->getContactPerson());
        static::assertEquals($masterToebBeforeUpdate->getDocumentAssessment(), $masterToebVersion->getDocumentAssessment());
        static::assertEquals($masterToebBeforeUpdate->getDId(), $masterToebVersion->getDId());
        static::assertEquals($masterToebBeforeUpdate->getCreatedDate(), $masterToebVersion->getCreatedDate());
        static::assertEquals($masterToebBeforeUpdate->getModifiedDate(), $masterToebVersion->getModifiedDate());
        static::assertEquals($masterToebBeforeUpdate->getDistrictAltona(), $masterToebVersion->getDistrictAltona());
        static::assertEquals($masterToebBeforeUpdate->getDistrictBergedorf(), $masterToebVersion->getDistrictBergedorf());
        static::assertEquals($masterToebBeforeUpdate->getDistrictHHNord(), $masterToebVersion->getDistrictHHNord());
        static::assertEquals($masterToebBeforeUpdate->getDistrictBsu(), $masterToebVersion->getDistrictBsu());
        static::assertEquals($masterToebBeforeUpdate->getDistrictEimsbuettel(), $masterToebVersion->getDistrictEimsbuettel());
        static::assertEquals($masterToebBeforeUpdate->getDistrictHarburg(), $masterToebVersion->getDistrictHarburg());
        static::assertEquals($masterToebBeforeUpdate->getDistrictHHMitte(), $masterToebVersion->getDistrictHHMitte());
        static::assertEquals($masterToebBeforeUpdate->getDistrictWandsbek(), $masterToebVersion->getDistrictWandsbek());
        static::assertEquals($masterToebBeforeUpdate->getDocumentAgreement(), $masterToebVersion->getDocumentAgreement());
        static::assertEquals($masterToebBeforeUpdate->getDocumentNotice(), $masterToebVersion->getDocumentNotice());
        static::assertEquals($masterToebBeforeUpdate->getDocumentRoughAgreement(), $masterToebVersion->getDocumentRoughAgreement());
        static::assertEquals($masterToebBeforeUpdate->getOId(), $masterToebVersion->getOId());
        static::assertEquals($masterToebBeforeUpdate->getOrgaName(), $masterToebVersion->getOrgaName());
        static::assertEquals($masterToebBeforeUpdate->getMemo(), $masterToebVersion->getMemo());
        static::assertEquals($masterToebBeforeUpdate->getRegistered(), $masterToebVersion->getRegistered());
        static::assertEquals($masterToebBeforeUpdate->getSign(), $masterToebVersion->getSign());
        static::assertEquals($masterToebBeforeUpdate->getGatewayGroup(), $masterToebVersion->getGatewayGroup());
    }

    public function testUpdateOrgaOnMasterToebUpdate()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');

        $masterToebBeforeUpdate = clone $this->fixtures->getReference('testMasterToeb');
        $ident = $masterToebBeforeUpdate->getId();
        $data = [
            'departmentName' => 'newAbteilung',
            'orgaName'       => 'newOrganame',
            'gatewayGroup'   => 'newGatewayGroup',
            'email'          => 'newEmail',
            'ccEmail'        => 'newCcEmail',
            'contactPerson'  => 'newContactPerson',
        ];

        static::assertNotEquals($data['gatewayGroup'], $masterToebBeforeUpdate->getGatewayGroup());
        static::assertNotEquals($data['email'], $masterToebBeforeUpdate->getEmail());
        static::assertNotEquals($data['ccEmail'], $masterToebBeforeUpdate->getCcEmail());
        static::assertNotEquals($data['contactPerson'], $masterToebBeforeUpdate->getContactPerson());
        static::assertNotEquals($data['departmentName'], $masterToebBeforeUpdate->getDepartment()->getName());
        static::assertNotEquals($data['orgaName'], $masterToebBeforeUpdate->getOrga()->getName());

        $this->sut->updateMasterToeb($ident, $data);
        $masterToeb = $this->sut->getMasterToeb($ident);
        static::assertEquals($data['gatewayGroup'], $masterToeb->getGatewayGroup());
        static::assertEquals($data['email'], $masterToeb->getEmail());
        static::assertEquals($data['ccEmail'], $masterToeb->getCcEmail());
        static::assertEquals($data['contactPerson'], $masterToeb->getContactPerson());
        static::assertEquals($data['departmentName'], $masterToeb->getDepartmentName());
        static::assertEquals($data['orgaName'], $masterToeb->getOrgaName());

        // Prüfe, ob die Orga und Departmententities auch den neuen Namen haben
        static::assertEquals($data['departmentName'], $masterToeb->getDepartment()->getName());
        static::assertEquals($data['orgaName'], $masterToeb->getOrga()->getName());
    }

    public function testUpdateMasterToebWithEmptyDataArray()
    {
        $this->expectException(Exception::class);

        $this->sut->updateMasterToeb('', []);
    }

    public function testGetMasterToebsReportValueStructure()
    {
        $results = $this->sut->getMasterToebsReport();

        static::assertIsArray($results);
        $entity = $results[0];
        $testReportEntry3 = $this->fixtures->getReference('testReportEntry3');

        static::assertCount(16, $entity);
        static::assertArrayHasKey('category', $entity);
        static::assertEquals($testReportEntry3->getCategory(), $entity['category']);
        static::assertArrayHasKey('message', $entity);
        static::assertEquals($testReportEntry3->getMessage(), $entity['message']);
        static::assertArrayHasKey('createDate', $entity);
        static::assertEquals($testReportEntry3->getMessage(), $entity['message']);
        static::assertArrayHasKey('createdDate', $entity);
        $this->isTimestamp($entity['createdDate']);
        static::assertEquals($testReportEntry3->getCreateDate()->getTimestamp() * 1000, $entity['createdDate']);
        static::assertArrayHasKey('id', $entity);
        static::assertEquals($testReportEntry3->getId(), $entity['id']);
        static::assertArrayHasKey('category', $entity);
        static::assertEquals($testReportEntry3->getCategory(), $entity['category']);
        static::assertArrayHasKey('group', $entity);
        static::assertEquals($testReportEntry3->getGroup(), $entity['group']);
        static::assertArrayHasKey('level', $entity);
        static::assertEquals($testReportEntry3->getlevel(), $entity['level']);
        static::assertArrayHasKey('userId', $entity);
        static::assertEquals($testReportEntry3->getUserId(), $entity['userId']);
        static::assertArrayHasKey('userName', $entity);
        static::assertEquals($testReportEntry3->getUserName(), $entity['userName']);
        static::assertArrayHasKey('sessionId', $entity);
        static::assertEquals($testReportEntry3->getSessionId(), $entity['sessionId']);
        static::assertArrayHasKey('identifierType', $entity);
        static::assertEquals($testReportEntry3->getIdentifierType(), $entity['identifierType']);
        static::assertArrayHasKey('identifier', $entity);
        static::assertEquals($testReportEntry3->getIdentifier(), $entity['identifier']);
        static::assertArrayHasKey('mimeType', $entity);
        static::assertEquals($testReportEntry3->getMimeType(), $entity['mimeType']);
        static::assertArrayHasKey('incoming', $entity);
        static::assertEquals($testReportEntry3->getIncoming(), $entity['incoming']);
    }

    public function testGetOrganisationsUnassignedToebOnly()
    {
        self::markSkippedForCIIntervention();

        $result = $this->sut->getOrganisations();

        static::assertIsArray($result);
        static::assertEquals(1, sizeof($result));
        static::assertEquals('Functional Test Toeb Orga', $result[0]['name']);
    }

    public function testGetOrganisationsOfMasterToebs()
    {
        self::markSkippedForCIIntervention();

        $result = $this->sut->getOrganisationsOfMasterToeb();
        static::assertIsArray($result);
        static::assertEquals(4, sizeof($result));
        $entry = $result[0];

        static::assertTrue(18 <= count($entry));
        static::assertArrayHasKey('ident', $entry);
        $this->checkId($entry['ident']);
        static::assertArrayHasKey('orgaName', $entry);
        static::assertIsString($entry['orgaName']);
        static::assertArrayHasKey('departmentName', $entry);
        static::assertIsString($entry['departmentName']);
        static::assertArrayHasKey('districtHHMitte', $entry);
        static::assertIsInt($entry['districtHHMitte']);
        static::assertArrayHasKey('districtAltona', $entry);
        static::assertIsInt($entry['districtAltona']);
        static::assertArrayHasKey('districtEimsbuettel', $entry);
        static::assertIsInt($entry['districtEimsbuettel']);
        static::assertArrayHasKey('districtHHNord', $entry);
        static::assertIsInt($entry['districtHHNord']);
        static::assertArrayHasKey('districtWandsbek', $entry);
        static::assertIsInt($entry['districtWandsbek']);
        static::assertArrayHasKey('districtBergedorf', $entry);
        static::assertIsInt($entry['districtBergedorf']);
        static::assertArrayHasKey('districtHarburg', $entry);
        static::assertIsInt($entry['districtHarburg']);
        static::assertArrayHasKey('districtBsu', $entry);
        static::assertIsInt($entry['districtBsu']);
        static::assertArrayHasKey('documentRoughAgreement', $entry);
        static::assertIsBool($entry['documentRoughAgreement']);
        static::assertArrayHasKey('documentAgreement', $entry);
        static::assertIsBool($entry['documentAgreement']);
        static::assertArrayHasKey('documentNotice', $entry);
        static::assertIsBool($entry['documentNotice']);
        static::assertArrayHasKey('documentAssessment', $entry);
        static::assertIsBool($entry['documentAssessment']);
        static::assertArrayHasKey('registered', $entry);
        static::assertIsBool($entry['registered']);
        static::assertArrayHasKey('oId', $entry);
        $this->checkId($entry['oId']);
        static::assertArrayHasKey('dId', $entry);
        $this->checkId($entry['dId']);
    }

    public function testMergeOrganisation()
    {
        self::markSkippedForCIIntervention();

        $this->logIn($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        /** @var MasterToeb $masterToeb */
        $masterToeb = $this->fixtures->getReference('testMasterToeb');
        $masterToebOrga = $masterToeb->getOrga();
        /** @var Orga $sourceOrga */
        $sourceOrga = $this->fixtures->getReference('testOrgaFP');
        $gwId = $sourceOrga->getGwId();
        $usersOfMasterToebBefore = $masterToebOrga->getUsers();
        $usersOfSourceOrgaBefore = $sourceOrga->getUsers();
        $sourceOrgaTestUser = clone $usersOfSourceOrgaBefore[0];
        $sourceOrgaId = $sourceOrga->getId();

        $masterToebDepartments = $masterToebOrga->getDepartments();
        $usersOfMasterToebDepartmentBefore = [];
        foreach ($masterToebDepartments as $department) {
            $users = $department->getUsers();
            foreach ($users as $user) {
                $usersOfMasterToebDepartmentBefore[] = $user;
            }
        }

        $sourceOrgaDepartments = $sourceOrga->getDepartments();
        $usersOfsourceOrgaDepartmentsBefore = [];
        foreach ($sourceOrgaDepartments as $department) {
            $users = $department->getUsers();
            foreach ($users as $user) {
                $usersOfsourceOrgaDepartmentsBefore[] = $user;
            }
        }

        $result = $this->sut->mergeOrganisations($sourceOrga->getIdent(), $masterToeb->getIdent());
        static::assertTrue($result);

        $usersOfMasterToebAfter = $masterToebOrga->getUsers();

        $masterToebDepartments = $masterToebOrga->getDepartments();
        $usersOfMasterToebDepartmentAfter = [];
        foreach ($masterToebDepartments as $department) {
            $users = $department->getUsers();
            foreach ($users as $user) {
                $usersOfMasterToebDepartmentAfter[] = $user;
            }
        }

        static::assertNotEquals($usersOfMasterToebBefore, $usersOfMasterToebAfter);
        static::assertEquals(sizeof($usersOfMasterToebBefore) + 1, sizeof($usersOfMasterToebAfter));

        $sourceOrga = $this->sut->getDoctrine()->getRepository(Orga::class)
            ->get($sourceOrga->getIdent());
        static::assertNull($sourceOrga);

        // shadoworga hat gwid von source orga
        static::assertEquals($gwId, $masterToeb->getOrga()->getGwId());

        // $masterToebOrga hat jetzt direkt die user von vorher + die user der source
        foreach ($usersOfMasterToebBefore as $user) {
            static::assertContains($user, $usersOfMasterToebAfter);
        }
        foreach ($usersOfSourceOrgaBefore as $user) {
            static::assertContains($user, $usersOfMasterToebAfter);
        }

        // mastertoeb hat jetzt im department, alle user: die von vorher + alle user aller departments der source orga
        foreach ($usersOfMasterToebDepartmentBefore as $user) {
            static::assertContains($user, $usersOfMasterToebDepartmentAfter);
        }
        foreach ($usersOfsourceOrgaDepartmentsBefore as $user) {
            static::assertContains($user, $usersOfMasterToebDepartmentAfter);
        }

        // es sind genauso viel user im department wie direkt der masterToebOrga zugeordnet
        static::assertEquals(sizeof($usersOfMasterToebDepartmentAfter), sizeof($usersOfMasterToebAfter));

        $usersOfsourceOrgaDepartmentAfter = [];
        foreach ($sourceOrgaDepartments as $department) {
            $users = $department->getUsers();
            foreach ($users as $user) {
                $usersOfsourceOrgaDepartmentAfter[] = $user->getId();
            }
        }
        static::assertNotContains(
            $sourceOrgaTestUser->getId(),
            $usersOfsourceOrgaDepartmentAfter
        );

        // es ist kein department mehr der source orga zugeordnet
        $allDepartments = $this->sut->getDoctrine()->getRepository(Department::class)->findAll();
        foreach ($allDepartments as $department) {
            if (null === $department->getOrga()) {
                continue;
            }
            static::assertNotEquals($department->getOrga()->getIdent(), $sourceOrgaId);
        }

        // if newOrga exists with given MasterToebId
        $newOrgaCreated = false;
        $result = $this->sut->getDoctrine()->getRepository(Orga::class)->find($masterToeb->getIdent());
        if (0 === count($result)) {
            $newOrgaCreated = true;
        }
        static::assertTrue($newOrgaCreated);
    }
}
