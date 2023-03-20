<?php declare(strict_types=1);


namespace Tests\Core\Security\Authenticator;


use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserDataMapper;
use demosplan\DemosPlanCoreBundle\ValueObject\BasicKeycloakUserData;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserLoginTest extends FunctionalTestCase
{
    /** @var OzgKeycloakUserDataMapper */
    protected $sut;

    private BasicKeycloakUserData $testBasicKeyCloakResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(OzgKeycloakUserDataMapper::class);
        $this->testBasicKeyCloakResponse = new BasicKeycloakUserData();
    }

    public function testMapKeycloakDataToUser(): void
    {
        $user = $this->sut->mapKeycloakDataToUser($this->testBasicKeyCloakResponse);
        static::assertSame('Walter Westi', $user->getName());
        static::assertSame('Walter Westi', $user->getName());
        static::assertSame('Walter Westi', $user->getName());

        //count orgas before and after. cover creating new orga, change existing one, and find but no change orga
    }
}
