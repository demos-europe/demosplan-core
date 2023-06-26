<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\SAML\SamlAttributesParser;
use Tests\Base\FunctionalTestCase;

class SamlAttributesParserTest extends FunctionalTestCase
{
    public function testUpdateUserAkdb()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN2);

        self::assertEquals('Klaas', $user->getFirstname());

        $attributes = [
            'mail'      => ['myNeq@mail.de'],
            'givenName' => ['Hannah'],
            'surname'   => ['Lotta'],
        ];

        $sut = new SamlAttributesParser($user, $attributes);
        $sut->parse();
        self::assertEquals($attributes['givenName'][0], $user->getFirstname());
        self::assertEquals($attributes['surname'][0], $user->getLastname());
        self::assertEquals($attributes['mail'][0], $user->getEmail());
    }
}
