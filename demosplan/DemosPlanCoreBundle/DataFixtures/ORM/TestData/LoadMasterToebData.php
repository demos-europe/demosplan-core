<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadMasterToebData extends TestFixture
{
    public function load(ObjectManager $manager): void
    {
        $masterToeb = new MasterToeb();
        $masterToeb->setGatewayGroup('G-D-BOP-Bezirksamt-Nord');
        $masterToeb->setOrga($this->getReference('testOrgaInvitableInstitution'));
        $masterToeb->setOrgaName('Functional Test Toeb Orga');
        $masterToeb->setDepartment($this->getReference('testDepartmentMasterToeb'));
        $masterToeb->setDepartmentName('TestDepartment');
        $masterToeb->setSign('N');
        $masterToeb->setEmail('emailmasterToeb1');
        $masterToeb->setContactPerson('masterToeb1');
        $masterToeb->setRegistered(true);
        $masterToeb->setDistrictHHMitte(1);
        $masterToeb->setDistrictEimsbuettel(2);
        $masterToeb->setDocumentRoughAgreement(1);

        $manager->persist($masterToeb);

        $masterToeb2 = new MasterToeb();
        $masterToeb2->setOrga($this->getReference('testOrgaPB'));
        $masterToeb2->setOrgaName('testOrgaName');
        $masterToeb2->setDepartmentName('testDepartmentName');
        $masterToeb2->setEmail('emailmasterToeb2');
        $masterToeb2->setContactPerson('masterToeb2');
        $masterToeb2->setRegistered(false);
        $masterToeb2->setDistrictWandsbek(1);
        $masterToeb2->setDistrictAltona(2);
        $masterToeb2->setDocumentAgreement(1);

        $manager->persist($masterToeb2);

        $masterToeb3 = new MasterToeb();
        $masterToeb3->setOrgaName('testOrgaInvitableInstitutionName');
        $masterToeb3->setGatewayGroup('G-D-BOP-FHHintern-TestToeB');
        $masterToeb3->setDepartmentName('testDepartmentName');
        $masterToeb3->setDepartment($this->getReference('testDepartmentMasterToeb'));
        $masterToeb3->setEmail('emailmasterToeb3');
        $masterToeb3->setContactPerson('masterToeb3');
        $masterToeb3->setRegistered(false);
        $masterToeb3->setDistrictWandsbek(1);
        $masterToeb3->setDistrictAltona(2);
        $masterToeb3->setDocumentAgreement(1);
        $manager->persist($masterToeb3);

        $masterToeb4 = new MasterToeb();
        $masterToeb4->setGatewayGroup('G-D-BOP-Bezirksamt-Nord-Einhoerner');
        $masterToeb4->setOrgaName('Functional Test Toeb Orga');
        $masterToeb4->setDepartment($this->getReference('testDepartmentMasterToebEinhoerner'));
        $masterToeb4->setDepartmentName('TestDepartment Einhörner');
        $masterToeb4->setSign('N');
        $masterToeb4->setEmail('emailmasterToeb1');
        $masterToeb4->setContactPerson('masterToeb1');
        $masterToeb4->setRegistered(true);
        $masterToeb4->setDistrictHHMitte(1);
        $masterToeb4->setDistrictEimsbuettel(2);
        $masterToeb4->setDocumentRoughAgreement(1);

        $manager->persist($masterToeb4);

        $this->setReference('testMasterToeb', $masterToeb);
        $this->setReference('testMasterToeb2', $masterToeb2);
        $this->setReference('testMasterToebGwGroup', $masterToeb3);
        $this->setReference('testMasterToebEinhoerner', $masterToeb4);

        $manager->flush();
    }
}
