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
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\User\AddressBookEntryService;
use demosplan\DemosPlanCoreBundle\ValueObject\User\AddressBookEntryVO;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Tests\Base\FunctionalTestCase;

class AddressBookEntryServiceTest extends FunctionalTestCase
{
    /**
     * @var AddressBookEntryService
     */
    protected $sut;

    /**
     * @var User
     */
    protected $testUser;

    /**
     * @var Orga
     */
    protected $testOrgaFp;

    /**
     * @var Department
     */
    protected $testDepartment;

    /**
     * @var Address
     */
    protected $testAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(AddressBookEntryService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->testOrgaFp = $this->fixtures->getReference('testOrgaFP');
        $this->testDepartment = $this->fixtures->getReference('testDepartment');
        $this->testAddress = $this->fixtures->getReference('testAddress');
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testAddAddressBookEntry()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('dataInputOrga');
        $result = $this->getEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );
        static::assertEmpty($result);

        $addressBookVO = new AddressBookEntryVO(
            'an Invitable Institution name',
            'adsfsfadfeinerInvitable@InstitutionMails.de',
            $organisation
        );

        $this->sut->createAddressBookEntry($addressBookVO);

        $result = $this->getEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );
        static::assertCount(1, $result);
    }

    /**
     * @throws MessageBagException
     */
    public function testRemoveAddressBookEntry()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('testOrgaFP');
        $numberOfEntriesBefore = $this->countEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );
        static::assertNotSame(0, $numberOfEntriesBefore);

        $successful = $this->sut->deleteAddressBookEntry($organisation->getAddressBookEntries()[0]->getId());
        static::assertTrue($successful);

        $numberOfEntriesAfter = $this->countEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );

        static::assertSame($numberOfEntriesBefore - 1, $numberOfEntriesAfter);
    }

    public function testGetFilledAddressBookEntiesOfOrganisation()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('testOrgaFP');
        $expectedResult = $this->getEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );
        static::assertNotEmpty($expectedResult);

        $actualResult = $this->sut->getAddressBookEntriesOfOrganisation($organisation->getId());
        static::assertNotEmpty($actualResult);

        static::assertEquals($expectedResult, $actualResult);
    }

    public function testGetEmptyAddressBookEnties()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('dataInputOrga');
        $actualResult = $this->sut->getAddressBookEntriesOfOrganisation($organisation->getId());
        static::assertEmpty($actualResult);
    }
}
