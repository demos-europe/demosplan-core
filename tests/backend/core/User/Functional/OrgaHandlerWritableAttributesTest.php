<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use Tests\Base\FunctionalTestCase;

class OrgaHandlerWritableAttributesTest extends FunctionalTestCase
{
    protected ?OrgaHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(OrgaHandler::class);
        $this->loginTestUser();
    }

    public function testTwoFactorRequiredIsNotWritableWithoutPermission(): void
    {
        $this->disablePermissions(['field_organisation_two_factor_required']);

        static::assertArrayNotHasKey(Orga::TWO_FACTOR_REQUIRED, $this->sut->getWritableAttributes());

        $this->expectException(AccessDeniedException::class);
        $this->sut->checkWritabilityOfAttributes([Orga::TWO_FACTOR_REQUIRED => true]);
    }

    public function testTwoFactorRequiredIsWritableWithPermission(): void
    {
        $this->enablePermissions(['field_organisation_two_factor_required']);

        static::assertArrayHasKey(Orga::TWO_FACTOR_REQUIRED, $this->sut->getWritableAttributes());
        $this->sut->checkWritabilityOfAttributes([Orga::TWO_FACTOR_REQUIRED => true]);
    }
}
