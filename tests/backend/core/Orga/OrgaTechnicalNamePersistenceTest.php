<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Orga;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Tests\Base\FunctionalTestCase;

class OrgaTechnicalNamePersistenceTest extends FunctionalTestCase
{
    protected ?UserService $userService = null;
    protected ?UserHandler $userHandler = null;
    protected ?OrgaRepository $orgaRepository = null;
    protected ?Orga $orga = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = $this->getContainer()->get(UserService::class);
        $this->userHandler = $this->getContainer()->get(UserHandler::class);
        $this->orgaRepository = $this->getContainer()->get(OrgaRepository::class);
        $this->orga = $this->fixtures->getReference(LoadUserData::TEST_ORGA_FP);
    }

    protected function tearDown(): void
    {
        $this->userService = null;
        $this->userHandler = null;
        $this->orgaRepository = null;
        $this->orga = null;

        parent::tearDown();
    }

    public function testServiceUpdateWritesTechnicalNameAndKeepsDisplayName(): void
    {
        $this->orga->setName('Display name');
        $this->getEntityManager()->flush();

        $updated = $this->userService->updateOrga($this->orga->getId(), ['technicalName' => 'Official name']);

        self::assertSame('Official name', $updated->getTechnicalName());
        self::assertSame('Display name', $updated->getName());
    }

    public function testUserHandlerIgnoresTechnicalNameFromUserInput(): void
    {
        // area_manage_orgadata lets all posted keys pass the permission check, so only the explicit guard protects
        $this->loginTestUser();
        $this->enablePermissions(['area_manage_orgadata']);
        $this->orga->setTechnicalName('Official name');
        $this->getEntityManager()->flush();

        $result = $this->userHandler->updateOrga($this->orga->getId(), [
            'name'          => 'New display name',
            'technicalName' => 'Manipulated',
        ]);

        self::assertNotNull($result, 'The update must have been executed');
        $this->getEntityManager()->clear();
        $reloaded = $this->orgaRepository->find($this->orga->getId());
        self::assertSame('New display name', $reloaded->getName());
        self::assertSame('Official name', $reloaded->getTechnicalName());
    }

    public function testWipeClearsTechnicalName(): void
    {
        $this->orga->setName('Display name');
        $this->orga->setTechnicalName('Official name');
        $this->getEntityManager()->flush();

        $this->orgaRepository->wipe($this->orga->getId());

        $this->getEntityManager()->clear();
        $reloaded = $this->orgaRepository->find($this->orga->getId());
        self::assertNull($reloaded->getName());
        self::assertNull($reloaded->getTechnicalName());
    }
}
