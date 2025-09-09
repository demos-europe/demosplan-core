<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PrepareReportFromProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use ReflectionException;
use Tests\Base\FunctionalTestCase;

class PrepareReportFromProcedureServiceTest extends FunctionalTestCase
{
    /**
     * @var PrepareReportFromProcedureService
     */
    protected $sut;

    /**
     * @var ReportService
     */
    protected $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);

        $this->sut = self::getContainer()->get(PrepareReportFromProcedureService::class);
        $this->reportService = self::getContainer()->get(ReportService::class);
    }

    /**
     * The methods creates two reports, so there are two test:
     * - check if there are two more reports after method execution
     * - check if the reports have the correct type (group and category).
     *
     * @throws CustomerNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function testAddReportOnProcedureCreate(): void
    {
        $procedure = $this->getProcedureReference('testProcedure');

        // method execution
        $procedureArray = [
            'ident'                 => $procedure->getId(),
            'otherProcedureContent' => '12345',
        ];
        $this->sut->addReportOnProcedureCreate($procedureArray, $procedure);

        // count reports after method execution
        $reports = $this->getEntityManager()->createQueryBuilder()
            ->select('report.category')
            ->where('report.identifier = :procedureId')
            ->setParameter('procedureId', $procedure->getId())
            ->from(ReportEntry::class, 'report')
            ->andWhere('report.group = :group')
            ->setParameter('group', 'procedure')
            // exclude test report fixture
            ->andWhere('report.message <> :message')
            ->setParameter('message', '{"stripped":"content"}')
            ->getQuery()
            ->getResult();

        $reports = array_column($reports, 'category');
        sort($reports);

        static::assertCount(2, $reports);
        static::assertSame('add', $reports[0]);
        static::assertSame('update', $reports[1]);
    }
}
