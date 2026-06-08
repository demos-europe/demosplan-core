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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Command\Data\ModifyUserCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Tests\Base\FunctionalTestCase;

class ModifyUserCommandTest extends FunctionalTestCase
{
    /**
     * @var ModifyUserCommand
     */
    protected $sut;

    /**
     * @var InputInterface
     */
    private $stringInput;

    /**
     * @var OutputInterface
     */
    private $streamOutput;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    /**
     * @var GlobalConfig|object|null
     */
    private $globalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        /* @var GlobalConfigInterface|GlobalConfig $globalConfig */
        $this->globalConfig = self::getContainer()->get(GlobalConfigInterface::class);
        $this->parameterBag = new ParameterBag(
            [
                'alternative_login_testuser_defaultpass' => 'testpassword',
                'organisationIds_user_to_modify'         => [],
                'roles_allowed'                          => $this->globalConfig->getRolesAllowed(),
            ]
        );
        $userService = self::getContainer()->get(UserService::class);
        $this->sut = new ModifyUserCommand($this->parameterBag, $userService);

        $this->streamOutput = $this->getMockBuilder(StreamOutput::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stringInput = $this->getMockBuilder(StringInput::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testResetUserPasswords(): void
    {
        self::markSkippedForCIIntervention();

        $returnValue = $this->sut->execute($this->stringInput, $this->streamOutput);
        static::assertSame(0, $returnValue);
        $setPassword = $this->parameterBag->get('alternative_login_testuser_defaultpass');
        $amountOfUsersWithResetPasswords = $this->getEntries(
            User::class,
            ['password' => md5($setPassword)]
        );
        $count = $this->dynamicCountOfUsersToChangesPasswords();
        static::assertCount($count, $amountOfUsersWithResetPasswords);
    }

    private function dynamicCountOfUsersToChangesPasswords(): int
    {
        $allUsers = collect($this->getEntries(User::class, ['deleted' => false]));
        $count = 0;
        $usersOfRoles = collect([]);

        // get Users per Role
        foreach ($this->globalConfig->getRolesAllowed() as $roleString) {
            $usersOfRoles[$roleString] = $allUsers->filter(function (User $user) use ($roleString) {
                return $user->hasRole($roleString);
            });
        }

        // guest user have to be ignored:
        $usersOfRoles = $usersOfRoles->forget(Role::GUEST);

        $countOfUsersPerRole = $usersOfRoles->map(static function (Collection $users) {
            return $users->count() > 3 ? 3 : $users->count();
        });

        return $countOfUsersPerRole->sum();
    }
}
