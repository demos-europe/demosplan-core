<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperDataportGateway;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperDataportGatewaySH;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class UserMapperDataportGatewayOrgaNameTest extends TestCase
{
    private ?UserMapperDataportGateway $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The rule under test lives in the abstract base class and does not need any collaborators.
        $this->sut = (new ReflectionClass(UserMapperDataportGatewaySH::class))->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $this->sut = null;

        parent::tearDown();
    }

    public function testNothingToUpdateWhenGatewayNameIsAlreadyStored(): void
    {
        $orga = $this->orga('Gateway name', 'Gateway name');

        self::assertSame([], $this->getOrgaNameUpdate($orga, 'Gateway name'));
    }

    public function testOrgaWithoutTechnicalNameGetsBothNamesFromGateway(): void
    {
        $orga = $this->orga('Old name', null);

        self::assertSame(
            ['technicalName' => 'New name', 'name' => 'New name'],
            $this->getOrgaNameUpdate($orga, 'New name')
        );
    }

    public function testOrgaWithoutTechnicalNameStoresTechnicalNameOnly(): void
    {
        $orga = $this->orga('Gateway name', null);

        self::assertSame(['technicalName' => 'Gateway name'], $this->getOrgaNameUpdate($orga, 'Gateway name'));
    }

    public function testDisplayNameFollowsGatewayWhileItWasNotCustomised(): void
    {
        $orga = $this->orga('Old name', 'Old name');

        self::assertSame(
            ['technicalName' => 'New name', 'name' => 'New name'],
            $this->getOrgaNameUpdate($orga, 'New name')
        );
    }

    public function testCustomisedDisplayNameIsNeverOverwritten(): void
    {
        $orga = $this->orga('Edited display name', 'Old name');

        self::assertSame(['technicalName' => 'New name'], $this->getOrgaNameUpdate($orga, 'New name'));
    }

    private function orga(?string $name, ?string $technicalName): Orga
    {
        return (new Orga())->setName($name)->setTechnicalName($technicalName);
    }

    private function getOrgaNameUpdate(Orga $orga, string $gatewayName): array
    {
        $method = new ReflectionMethod(UserMapperDataportGateway::class, 'getOrgaNameUpdate');

        return $method->invoke($this->sut, $orga, $gatewayName);
    }
}
