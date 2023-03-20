<?php declare(strict_types=1);


namespace Tests\Core\Security\Authenticator;


use demosplan\DemosPlanCoreBundle\Logic\OzgKeycloakUserLogin;
use demosplan\DemosPlanCoreBundle\ValueObject\BasicKeycloakResponse;
use Tests\Base\FunctionalTestCase;

class OzgKeycloakUserLoginTest extends FunctionalTestCase
{
    /** @var OzgKeycloakUserLogin */
    protected $sut;

    private BasicKeycloakResponse $testBasicKeyCloakResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(OzgKeycloakUserLogin::class);
        $this->testBasicKeyCloakResponse = new BasicKeycloakResponse();
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
