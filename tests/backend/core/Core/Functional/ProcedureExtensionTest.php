<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ProcedureExtension;
use Exception;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

/**
 * @group UnitTest
 */
class ProcedureExtensionTest extends FunctionalTestCase
{
    /** @var ProcedureExtension */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->createSut($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    public function testGetPhase()
    {
        $poorPeoplesDataProvider = $this->getDataProviderProcedurePhases();

        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);

        foreach ($poorPeoplesDataProvider as $data) {
            $procedure->setPhase($data[0]['phase']);
            $procedure->setPublicParticipationPhase($data[0]['publicParticipationPhase']);
            $phase = $this->sut->getPhase($procedure);
            static::assertEquals($data[0]['assertedPhase'], $phase);

            $phase = $this->sut->getPhase($procedure, 'public');
            static::assertEquals($data[0]['assertedPublicParticipationPhase'], $phase);
        }
    }

    public function testGetPhaseKey()
    {
        $procedure = $this->getProcedure();
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_INVITABLE_INSTITUTION_ONLY);
        $this->createSut($user);
        $phase = $this->sut->getPhaseKey($procedure);
        static::assertEquals($procedure->getPhase(), $phase);

        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $this->createSut($user);
        $phase = $this->sut->getPhaseKey($procedure, 'public');
        static::assertEquals($procedure->getPublicParticipationPhase(), $phase);
    }

    /**
     * @dataProvider getDataProviderProcedureStartDate
     *
     * @throws Exception
     */
    public function testGetStartDate($procedure)
    {
        if (!array_key_exists('startDate', $procedure)) {
            $this->expectException(InvalidArgumentException::class);
            $this->sut->getStartDate($procedure);
        } else {
            $phase = $this->sut->getStartDate($procedure);
            if (is_numeric($procedure['startDate'])) {
                $date = Carbon::createFromTimestamp($procedure['startDate']);
            } elseif ($procedure['startDate'] instanceof DateTime) {
                $date = $procedure['startDate'];
            } else {
                $date = new DateTime($procedure['startDate']);
            }
            self::assertEquals($date->getTimestamp(), $phase);

            $phase = $this->sut->getStartDate($procedure, 'public');
            $date = null;
            if (is_numeric($procedure['publicParticipationStartDate'])) {
                $date = Carbon::createFromTimestamp($procedure['publicParticipationStartDate']);
            } elseif ($procedure['publicParticipationStartDate'] instanceof DateTime) {
                $date = $procedure['publicParticipationStartDate'];
            } else {
                $date = new DateTime($procedure['publicParticipationStartDate']);
            }
            self::assertEquals($date->getTimestamp(), $phase);
        }
    }

    /**
     * @throws Exception
     */
    public function testGetEndDate()
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference(LoadProcedureData::TEST_PROCEDURE_2);

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->createSut($user);
        $endDate = $this->sut->getEndDate($procedure);
        static::assertEquals($procedure->getEndDateTimestamp(), $endDate);

        $publicEndDate = $this->sut->getEndDate($procedure, 'public');
        static::assertEquals($procedure->getPublicParticipationEndDateTimestamp(), $publicEndDate);
    }

    /**
     * @dataProvider getDataProviderProcedureName
     */
    public function testGetNameFunction($providerData)
    {
        $procedure = $this->getProcedure();
        $procedure->setName($providerData['assertedName']);
        $procedure->setExternalName($providerData['assertedExternalName']);

        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_INVITABLE_INSTITUTION_ONLY);
        $this->createSut($user);

        $phase = $this->sut->getNameFunction($procedure);
        static::assertEquals($providerData['assertedName'], $phase);

        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $this->createSut($user);
        $phase = $this->sut->getNameFunction($procedure, 'public');
        static::assertEquals($providerData['assertedExternalName'], $phase);
    }

    /**
     * DataProvider.
     *
     * @return array
     */
    public function getDataProviderProcedureName()
    {
        return [
            [[
                'name'                 => 'Name',
                'assertedName'         => 'Name',
                'externalName'         => 'ExternalName',
                'assertedExternalName' => 'ExternalName',
            ]],
            [[
                'orgaId'               => 'orgaId',
                'name'                 => 'Name',
                'assertedName'         => 'Name (ExternalName)',
                'externalName'         => 'ExternalName',
                'assertedExternalName' => 'Name (ExternalName)',
            ]],
        ];
    }

    /**
     * DataProvider.
     *
     * @return array
     */
    public function getDataProviderProcedureStartDate()
    {
        return [
            [[
                'startDate'                    => '23.10.1995',
                'publicParticipationStartDate' => '12.06.2017',
            ]],
            [[
                'startDate'                    => '814406400',
                'publicParticipationStartDate' => '814507400',
            ]],
            [[
                'startDate'                    => 814406400,
                'publicParticipationStartDate' => 814507400,
            ]],
            [[
                'something' => 'to throw exception',
            ]],
            [[
                'startDate'                    => new DateTime('23.10.1995'),
                'publicParticipationStartDate' => new DateTime('22.01.2020'),
            ]],
        ];
    }

    /**
     * No real DataProvider as container is needed.
     *
     * @return array
     */
    public function getDataProviderProcedurePhases()
    {
        $globalConfig = self::getContainer()->get(GlobalConfigInterface::class);
        $internalPhases = $globalConfig->getInternalPhases();
        $externalPhases = $globalConfig->getExternalPhases();

        $phasesDataProvider = [];
        // internal phases
        foreach ($internalPhases as $internalPhase) {
            $phasesDataProvider[] = [[
                'phase'                            => $internalPhase['key'],
                'assertedPhase'                    => $internalPhase['name'],
                'publicParticipationPhase'         => $externalPhases[1]['key'],
                'assertedPublicParticipationPhase' => $externalPhases[1]['name'],
            ]];
        }
        // external phases
        foreach ($externalPhases as $externalPhase) {
            $phasesDataProvider[] = [[
                'phase'                            => $internalPhases[1]['key'],
                'assertedPhase'                    => $internalPhases[1]['name'],
                'publicParticipationPhase'         => $externalPhase['key'],
                'assertedPublicParticipationPhase' => $externalPhase['name'],
            ]];
        }

        return $phasesDataProvider;
    }

    protected function getProcedure(): Procedure
    {
        return $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);
    }

    public function testGetProcedureName()
    {
        /** @var User $anonymousUser */
        $anonymousUser = $this->fixtures->getReference(LoadUserData::TEST_USER_GUEST);
        /** @var User $plannerUser */
        $plannerUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var User $otherPlannerUser */
        $otherPlannerUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($anonymousUser);
        $procedure = $this->getProcedure();
        $result = $this->sut->getNameFunction($procedure);
        static::assertSame($procedure->getExternalName(), $result);

        $procedure = new Procedure();
        $procedure->setName('Name');
        $procedure->setExternalName('externalName');
        $result = $this->sut->getNameFunction($procedure);
        static::assertSame($procedure->getExternalName(), $result);

        $this->logIn($plannerUser, true);
        $procedure = $this->getProcedure();
        $result = $this->sut->getNameFunction($procedure);
        // user owns procedure, so he should see both names
        static::assertSame($procedure->getName().' ('.$procedure->getExternalName().')', $result);

        $this->logIn($otherPlannerUser);
        $procedure = $this->getProcedure();
        $result = $this->sut->getNameFunction($procedure);
        // user does not own procedure, so he should not see both names
        static::assertSame($procedure->getName(), $result);

        $this->logIn($plannerUser);
        $procedure = new Procedure();
        $procedure->setName('internalName');
        $result = $this->sut->getNameFunction($procedure);
        static::assertSame($procedure->getName(), $result);

        // invalid
        $this->logIn($anonymousUser);
        $result = $this->sut->getNameFunction([]);
        static::assertSame('', $result);

        // invalid
        $this->logIn($anonymousUser);
        $result = $this->sut->getNameFunction('invalidString');
        static::assertSame('', $result);

        // invalid
        $this->logIn($anonymousUser);
        $result = $this->sut->getNameFunction(123);
        static::assertSame('', $result);
    }

    /**
     * @dataProvider getDataProviderDaysLeft
     */
    public function testGetDaysLeft($providerData)
    {
        $daysLeft = $this->sut->getDaysLeftDays($providerData['endDate']);
        self::assertEquals($providerData['daysLeft'], $daysLeft);
    }

    public function getDataProviderDaysLeft()
    {
        $yesterday = Carbon::yesterday();
        $now = Carbon::now();
        $tomorrow = Carbon::tomorrow();

        return [
            [[
                'endDate'  => $tomorrow->timestamp,
                'daysLeft' => 2,
            ]],
            [[
                'endDate'  => $now->timestamp,
                'daysLeft' => 1,
            ]],
            [[
                'endDate'  => $yesterday->timestamp,
                'daysLeft' => 0,
            ]],
            [[
                'endDate'  => Carbon::createMidnightDate()->timestamp,
                'daysLeft' => 1,
            ]],
            [[
                'endDate'  => Carbon::createMidnightDate($yesterday->year, $yesterday->month, $yesterday->day)->timestamp,
                'daysLeft' => 0,
            ]],
            [[
                'endDate'  => Carbon::createMidnightDate($yesterday->year, $yesterday->month, $yesterday->day)->timestamp,
                'daysLeft' => 0,
            ]],
            [[
                'endDate'  => Carbon::now()->subDays(20)->timestamp,
                'daysLeft' => -18,
            ]],
            [[
                'endDate'  => Carbon::now()->addDays(20)->timestamp,
                'daysLeft' => 21,
            ]],
        ];
    }

    public function testName()
    {
        $result = $this->sut->getName();
        static::assertTrue('procedure_extension' === $result);
    }

    private function createSut(User $user, $ownsProcedure = false): void
    {
        $permissionMockMethods = [
            new MockMethodDefinition('hasPermission', fn ($permission) => 'area_public_participation' === $permission),
            new MockMethodDefinition('ownsProcedure', $ownsProcedure),
        ];
        $permissions = $this->getMock(Permissions::class, $permissionMockMethods);
        $mockMethods = [
            new MockMethodDefinition('getUser', $user),
        ];
        $currentUser = $this->getMock(CurrentUserInterface::class, $mockMethods);
        $this->sut = new ProcedureExtension(
            self::getContainer(),
            $currentUser,
            self::getContainer()->get(GlobalConfigInterface::class),
            new NullLogger(),
            $permissions,
            self::getContainer()->get(ProcedureService::class),
            self::getContainer()->get(TranslatorInterface::class)
        );
    }

    /**
     * Method needs to be overidden to automatically set up a new sut.
     */
    protected function logIn($user, $ownsProcedure = false): void
    {
        parent::logIn($user);
        $this->createSut($user, $ownsProcedure);
    }
}
