<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Report\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use Tests\Base\FunctionalTestCase;

class ReportServiceTest extends FunctionalTestCase
{
    /**
     * @var ReportService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ReportService::class);
    }

    public function testSetCurruptReportEntry()
    {
        $this->expectException(ViolationsException::class);

        $data = new ReportEntry();
        $this->sut->persistAndFlushReportEntry($data);
    }

    public function testAddReportEntry()
    {
        // report entry darf nicht überschrieben werden: es soll immer ein neuer Report entriy erzeugt werden.
        $amountOfReportEntriesBefore = $this->countEntries(ReportEntry::class);
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $customer = $this->getCustomerReference('Brandenburg');

        $data = new ReportEntry();
        $data->setCategory('test');
        $data->setGroup('test');
        $data->setUser($user);
        $data->setIdentifier('testingIdentifier4-211f-123456789d98');
        $data->setMessage('test');
        $data->setIncoming('tesst');
        $data->setIdentifierType('procedure');
        $data->setCustomer($customer);

        $this->sut->persistAndFlushReportEntry($data);

        static::assertEquals(36, strlen($data->getId()));
        static::assertEquals($user->getId(), $data->getUserId());
        static::assertEquals('testingIdentifier4-211f-123456789d98', $data->getIdentifier());
        static::assertEquals('test', $data->getCategory());
        static::assertEquals('INFO', $data->getLevel());
        static::assertEquals('test', $data->getGroup());
        static::assertEquals('test', $data->getGroup());
        static::assertEquals($user->getFullname(), $data->getUserName());
        static::assertEquals('procedure', $data->getIdentifierType());
        static::assertEquals('', $data->getMimeType());
        static::assertEquals('test', $data->getMessage());
        static::assertEquals('tesst', $data->getIncoming());
        static::assertEquals($customer->getId(), $data->getCustomer()->getId());
        static::assertInstanceOf(DateTime::class, $data->getCreated());
        $this->isCurrentTimestamp($data->getCreated());

        $amountOfReportEntriesAfter = $this->countEntries(ReportEntry::class);
        static::assertEquals($amountOfReportEntriesBefore + 1, $amountOfReportEntriesAfter);
    }
}
