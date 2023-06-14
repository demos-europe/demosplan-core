<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Documentation;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

use function array_flip;
use function array_map;
use function collect;

class ProjectPermissionsDocumentationCommand extends CoreCommand
{
    /**
     * @var PermissionsInterface
     */
    private $permissions;

    protected static $defaultName = 'dplan:documentation:project-permissions';
    protected static $defaultDescription = 'Extend the permissions documentation with project information';

    public function __construct(ParameterBagInterface $parameterBag, PermissionsInterface $permissions, string $name = null)
    {
        parent::__construct($parameterBag, $name);

        $this->permissions = $permissions;
    }

    public function configure(): void
    {
        $this->addOption(
            'yaml',
            '',
            InputOption::VALUE_NONE,
            'Output information in machine processable format'
        );

        $this->addOption(
            'role',
            'r',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Limit the output to the role(s) given',
            [Role::GUEST]
        );

        $this->addOption(
            'list-roles',
            '',
            InputOption::VALUE_NONE,
            'Output a list of availble roles'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('list-roles')) {
            return $this->renderRoleList($input, $output);
        }

        return $this->renderPermissionList($input, $output);
    }

    protected function renderPermissionList(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->createUser($input->getOption('role'));

        $this->permissions->initPermissions($user);
        $enabledPermissions = collect($this->permissions->getPermissions())
            ->filter->isEnabled()
            ->map(
                static function (Permission $permission) {
                    return [
                        'name'          => $permission->getName(),
                        'label'         => $permission->getLabel(),
                        'expose'        => $permission->isExposed(),
                        'loginRequired' => $permission->isLoginRequired(),
                    ];
                }
            )
            ->toArray();

        if ($input->getOption('yaml')) {
            $output->writeln(Yaml::dump($enabledPermissions));

            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Label', 'Exposed', 'Login required']);
        $table->setRows($enabledPermissions);

        $table->render();

        return 0;
    }

    /**
     * @param array<int,string> $roleCodes
     */
    private function createUser(array $roleCodes): User
    {
        $user = new FunctionalUser();

        $roles = collect($roleCodes)->map(
            static function (string $roleCode) {
                $role = new Role();
                $role->setCode($roleCode);

                return $role;
            }
        )->toArray();

        $user->setDplanroles($roles);

        return $user;
    }

    private function renderRoleList(InputInterface $input, OutputInterface $output): int
    {
        $classReflection = new ReflectionClass(Role::class);
        $constantsMap = array_flip($classReflection->getConstants());
        $docBlockParser = DocBlockFactory::createInstance();

        $allowedRoles = array_map(
            static function ($roleConstant) use ($classReflection, $constantsMap, $docBlockParser) {
                $constantReflection = $classReflection->getReflectionConstant(
                    $constantsMap[$roleConstant]
                );

                return [
                    'constant'    => $roleConstant,
                    'description' => $docBlockParser->create(
                        $constantReflection->getDocComment()
                    )->getSummary(),
                ];
            },
            $this->parameterBag->get('roles_allowed')
        );

        if ($input->getOption('yaml')) {
            $dump = [
                'project' => $this->parameterBag->get('demosplan.project_name'),
                'roles'   => $allowedRoles,
            ];

            $output->writeln(Yaml::dump($dump));

            return 0;
        }

        $table = new Table($output);

        $table->setHeaders(['Role Constant', 'Description']);
        $table->addRows($allowedRoles);
        $table->render();

        return 0;
    }
}
