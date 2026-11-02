<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\ExportScheduleFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class ScheduledExportResourceApiTest extends AbstractApiTest
{
    private const PERMISSION = 'feature_admin_scheduled_xlsx_export';

    /**
     * /api/3.0/* routes sit behind the `api_platform` firewall (session-based), see {@see TagResourceApiTest}.
     */
    private function loginUserForApiPlatform(User $user): void
    {
        $this->client->loginUser($user, 'main');
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = [];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    public function testGetCollectionIsSortedByNextRunAtAscendingByDefault(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $late = $this->createSchedule($user, $procedure, '+30 days');
        $early = $this->createSchedule($user, $procedure, '+1 day');
        $middle = $this->createSchedule($user, $procedure, '+10 days');
        $this->enablePermissions([self::PERMISSION]);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest('/api/3.0/ScheduledExport', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [$early, $middle, $late],
            $this->extractIds($response)
        );
    }

    public function testGetCollectionCanBeSortedByNextRunAtDescending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $late = $this->createSchedule($user, $procedure, '+30 days');
        $early = $this->createSchedule($user, $procedure, '+1 day');
        $middle = $this->createSchedule($user, $procedure, '+10 days');
        $this->enablePermissions([self::PERMISSION]);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest('/api/3.0/ScheduledExport?sort=-nextRunAt', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [$late, $middle, $early],
            $this->extractIds($response)
        );
    }

    public function testGetCollectionOnlyContainsOwnSchedulesOfCurrentProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $otherUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $own = $this->createSchedule($user, $procedure, '+1 day');
        $this->createSchedule($otherUser, $procedure, '+2 days');
        $this->createSchedule($user, $otherProcedure, '+3 days');
        $this->enablePermissions([self::PERMISSION]);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest('/api/3.0/ScheduledExport', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([$own], $this->extractIds($response));
    }

    public function testGetCollectionIsForbiddenWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->createSchedule($user, $procedure, '+1 day');
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest('/api/3.0/ScheduledExport', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createSchedule(User $user, Procedure $procedure, string $nextRunAt): string
    {
        return ExportScheduleFactory::createOne([
            'userId'      => $user->getId(),
            'procedureId' => $procedure->getId(),
            'nextRunAt'   => new DateTime($nextRunAt),
        ])->getId();
    }

    /**
     * @return list<string>
     */
    private function extractIds(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return array_column(Json::decodeToArray($content)['data'], 'id');
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
