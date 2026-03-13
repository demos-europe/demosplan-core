<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\OzgKeycloakDataMapper;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakDataMapper\OrganisationAffiliationMapper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrganisationAffiliationMapperTest extends TestCase
{
    private OrganisationAffiliationMapper $sut;
    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->sut = new OrganisationAffiliationMapper($this->entityManager);
    }

    public function testSyncRemovesStaleOrgOnReLoginWithFewerOrgs(): void
    {
        // Arrange: user currently linked to orgA, orgB, orgC
        $orgA = $this->createOrgaMock('orga-a');
        $orgB = $this->createOrgaMock('orga-b');
        $orgC = $this->createOrgaMock('orga-c');

        $userOrgas = new ArrayCollection([$orgA, $orgB, $orgC]);
        $user = $this->createUserMockWithOrgas($userOrgas);

        $oldOrgas = [$orgA, $orgB, $orgC];
        // Re-login token only contains orgA and orgB
        $targetOrganisations = [$orgA, $orgB];

        // orgC should be unlinked
        $orgC->expects(self::once())->method('unlinkUser')->with($user)->willReturnSelf();

        // orgA and orgB should NOT be unlinked
        $orgA->expects(self::never())->method('unlinkUser');
        $orgB->expects(self::never())->method('unlinkUser');

        // EntityManager persists: orgC (removal) + user
        $persisted = [];
        $this->entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        // Act
        $this->sut->syncUserOrganisations($user, $oldOrgas, $targetOrganisations);

        // Assert: orgC removed from user's collection
        self::assertFalse($userOrgas->contains($orgC), 'Stale org should be removed from user');
        self::assertTrue($userOrgas->contains($orgA), 'Retained org A should still be linked');
        self::assertTrue($userOrgas->contains($orgB), 'Retained org B should still be linked');
        self::assertCount(2, $userOrgas);
        self::assertSame($orgC, $persisted[0], 'Removed org should be persisted');
        self::assertSame($user, $persisted[1], 'User should be persisted');
    }

    public function testSyncAddsNewOrgAndRemovesStaleOrg(): void
    {
        // Arrange: user has orgA, orgB, orgC; re-login with orgA, orgB, orgD
        $orgA = $this->createOrgaMock('orga-a');
        $orgB = $this->createOrgaMock('orga-b');
        $orgC = $this->createOrgaMock('orga-c');
        $orgD = $this->createOrgaMock('orga-d');

        $userOrgas = new ArrayCollection([$orgA, $orgB, $orgC]);
        $user = $this->createUserMockWithOrgas($userOrgas);

        $oldOrgas = [$orgA, $orgB, $orgC];
        $targetOrganisations = [$orgA, $orgB, $orgD];

        // orgC should be unlinked
        $orgC->expects(self::once())->method('unlinkUser')->with($user)->willReturnSelf();

        // orgD should be linked (new org)
        $orgD->expects(self::once())->method('linkUser')->with($user)->willReturnSelf();

        // Act
        $this->sut->syncUserOrganisations($user, $oldOrgas, $targetOrganisations);

        // Assert
        self::assertFalse($userOrgas->contains($orgC), 'Stale org C should be removed');
        self::assertTrue($userOrgas->contains($orgD), 'New org D should be added');
        self::assertTrue($userOrgas->contains($orgA), 'Retained org A should still be linked');
        self::assertTrue($userOrgas->contains($orgB), 'Retained org B should still be linked');
        self::assertCount(3, $userOrgas);
    }

    public function testSyncDoesNothingWhenOrgsUnchanged(): void
    {
        // Arrange: user has orgA, orgB; re-login with same orgA, orgB
        $orgA = $this->createOrgaMock('orga-a');
        $orgB = $this->createOrgaMock('orga-b');

        $userOrgas = new ArrayCollection([$orgA, $orgB]);
        $user = $this->createUserMockWithOrgas($userOrgas);

        $oldOrgas = [$orgA, $orgB];
        $targetOrganisations = [$orgA, $orgB];

        // No unlink should happen
        $orgA->expects(self::never())->method('unlinkUser');
        $orgB->expects(self::never())->method('unlinkUser');

        // Only user persist, no org persists for add/remove
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with($user);

        // Act
        $this->sut->syncUserOrganisations($user, $oldOrgas, $targetOrganisations);

        // Assert
        self::assertCount(2, $userOrgas);
    }

    /**
     * @return MockObject&Orga
     */
    private function createOrgaMock(string $id): MockObject
    {
        $orga = $this->createMock(Orga::class);
        $orga->method('getId')->willReturn($id);

        return $orga;
    }

    /**
     * @return MockObject&User
     */
    private function createUserMockWithOrgas(ArrayCollection $orgas): MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getOrganisations')->willReturn($orgas);

        $user->method('addOrganisation')
            ->willReturnCallback(static function ($org) use ($orgas): void {
                if (!$orgas->contains($org)) {
                    $orgas->add($org);
                }
            });

        $user->method('removeOrganisation')
            ->willReturnCallback(static function ($org) use ($orgas): void {
                $orgas->removeElement($org);
            });

        return $user;
    }
}
