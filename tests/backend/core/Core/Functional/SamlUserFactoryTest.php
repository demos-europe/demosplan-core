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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Security\User\SamlUserFactory;
use Psr\Log\NullLogger;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class SamlUserFactoryTest extends FunctionalTestCase
{
    /** @var SamlUserFactory */
    protected $sut;

    /**
     * @var User
     */
    protected $citizenUser;

    protected function setUp(): void
    {
        parent::setUp();

        $customerMockMethods = [
            new MockMethodDefinition('getCurrentCustomer', $this->fixtures->getReference(LoadCustomerData::BRANDENBURG)),
        ];
        $customerService = $this->getMock(CustomerService::class, $customerMockMethods);

        $eventDispatcher = $this->getContainer()->get(EventDispatcherPostInterface::class);

        $this->citizenUser = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);

        $orgaService = $this->getContainer()->get(OrgaService::class);

        $roleHandler = $this->getContainer()->get(RoleHandler::class);

        $userService = $this->getContainer()->get(UserService::class);

        $this->sut = new SamlUserFactory($customerService, $eventDispatcher, new NullLogger(), $orgaService, $roleHandler, $userService);
    }

    public function testCreateNewUserFromSAML(): void
    {
        $attributes = [
            'country'       => [''],
            'givenName'     => ['Hannah'],
            'houseNumber'   => [''],
            'ID'            => ['886227c04d14045c16c42d706427d8392fd64417'],
            'localityName'  => [''],
            'email'         => ['mail.needs@citiz.en'],
            'orgaMail'      => [''],
            'orgaName'      => [''],
            'orgaType'      => [''],
            'postalCode'    => [''],
            'street'        => [''],
            'surname'       => ['Lotta'],
        ];

        $user = $this->sut->createUser($attributes['ID'][0], $attributes);
        self::assertInstanceOf(User::class, $user);
        $anonymousUser = new AnonymousUser();
        self::assertEquals($anonymousUser->getOrga()->getId(), $user->getOrga()->getId());
        self::assertEquals($attributes['ID'][0], $user->getLogin());
    }

    public function testCreateNewUserFromSAMLServicekonto(): void
    {
        $attributes = [
            'email'       => ['Sarah@connell.de'],
            'givenName'   => ['Sarah'],
            'bPK'         => ['VjEuQkI6OmRlLmFrZGIuYnBrLnNzb0Bsb2dpbi1zdGFnZS5iYXVsZWl0cGxhbnVuZy1vbmxpbmUuZGU6OmxvUlF1c25qTXQ0bVN4eUFtNExQZkZPbTdjd1JiWjRQc3FFNTltcERWbnc6OjIwMjItMDktMjNUMTU6MTQ6NTM='],
            'surname'     => ['Connell'],
            'ID'          => ['loRQusnjMt4mSxyAm4LPfFOm7cwRbZ4PsqE59mpDVnw'],
            'sessionIndex'=> '54204e12-af28-4b20-806b-e21fced6ad8a::30065ff8-40b7-42b7-82c4-80c26d993959',
        ];

        $user = $this->sut->createUser($attributes['ID'][0], $attributes);
        self::assertInstanceOf(User::class, $user);
        $anonymousUser = new AnonymousUser();
        self::assertEquals($anonymousUser->getOrga()->getId(), $user->getOrga()->getId());
        self::assertEquals($attributes['ID'][0], $user->getLogin());
    }

    public function testCreateNewOrgaNoUserFromSAML(): void
    {
        $attributes = $this->getOrgaLoginAttributes();

        $user = $this->sut->createUser($attributes['ID'][0], $attributes);
        self::assertInstanceOf(User::class, $user);
        $orga = $user->getOrga();
        self::assertEquals($attributes['orgaName'][0], $orga->getName());
        self::assertEquals($attributes['houseNumber'][0], $orga->getHouseNumber());
        self::assertEquals($attributes['localityName'][0], $orga->getCity());
        self::assertEquals($attributes['postalCode'][0], $orga->getPostalcode());
        self::assertEquals($attributes['street'][0], $orga->getStreet());
        self::assertEquals($attributes['ID'][0], $orga->getGatewayName());

        self::assertEquals($attributes['ID'][0], $user->getLogin());
    }

    public function testUpdateOrgaFromSAML(): void
    {
        $attributes = $this->getOrgaLoginAttributes();

        $user = $this->sut->createUser($attributes['ID'][0], $attributes);

        $attributes['localityName'] = ['Neustadt'];
        self::assertInstanceOf(User::class, $user);
        $user = $this->sut->createUser($attributes['ID'][0], $attributes);

        $orga = $user->getOrga();
        self::assertEquals($attributes['orgaName'][0], $orga->getName());
        self::assertEquals($attributes['houseNumber'][0], $orga->getHouseNumber());
        self::assertEquals($attributes['localityName'][0], $orga->getCity());
        self::assertEquals($attributes['postalCode'][0], $orga->getPostalcode());
        self::assertEquals($attributes['street'][0], $orga->getStreet());
        self::assertEquals($attributes['ID'][0], $orga->getGatewayName());

        self::assertEquals($attributes['ID'][0], $user->getLogin());
    }

    private function getOrgaLoginAttributes(): array
    {
        return [
            'country'       => ['de'],
            'givenName'     => [''],
            'houseNumber'   => ['14'],
            'ID'            => ['du-886227c04d14045c16c42d706427d8392fd64417'],
            'localityName'  => ['Erlangen'],
            'email'         => ['mail.needs@tobes.et'],
            'orgaMail'      => ['orgaMailneeds@tobes.et'],
            'orgaName'      => ['Franken Plus GmbH & Co. KGaA'],
            'orgaType'      => ['NNatPers'],
            'postalCode'    => ['91058'],
            'street'        => ['Frauenweiherstr.'],
            'surname'       => [''],
        ];
    }
}
